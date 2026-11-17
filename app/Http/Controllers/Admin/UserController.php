<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * STAFF & USERS — full user administration (Administrator only).
 */
class UserController extends Controller
{
    /** Paginated roster with search, role filter and status filter. */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->search($request->query('search'))
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role->value,
                'role_label' => $u->role->label(),
                'status' => $u->status->value,
                'status_label' => $u->status->label(),
                'last_login_at' => $u->last_login_at?->toDateTimeString(),
                'created_at' => $u->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
            'statuses' => collect(UserStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'filters' => $request->only('search', 'role', 'status'),
        ]);
    }

    /** Return the create-user form page with lookup data. */
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'showModal' => true,
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
            'statuses' => collect(UserStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'filters' => [],
            'users' => null,
        ]);
    }

    /** Persist a new user account. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(UserRole::values())],
            'status' => ['required', Rule::in(UserStatus::values())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /** Return the edit-user form page with lookup data. */
    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'editing' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'role' => $user->role->value,
                'status' => $user->status->value,
            ],
            'roles' => collect(UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
            'statuses' => collect(UserStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'filters' => [],
            'users' => null,
        ]);
    }

    /** Update an existing user account. */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(UserRole::values())],
            'status' => ['required', Rule::in(UserStatus::values())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $me = $request->user();

        if ($me->id === $user->id && $validated['role'] !== UserRole::Admin->value) {
            return back()->with('error', 'You cannot change your own role away from Administrator.');
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'status' => $validated['status'],
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /** Soft-delete a user (Administrator cannot delete themselves). */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($response = $this->guardSelf($request, $user, 'delete')) {
            return $response;
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /** Change a user's status (Administrator cannot change their own status). */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(UserStatus::values())],
        ]);

        if ($response = $this->guardSelf($request, $user, 'status')) {
            return $response;
        }

        $user->update(['status' => $validated['status']]);

        return back()->with('success', 'User status updated.');
    }

    /** Reset/change a user's password. */
    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    /** ------------------------------------------------------------------ */
    /** Guards                                                              */
    /** ------------------------------------------------------------------ */

    /**
     * Guards against self-targeting destructive or privileged actions.
     */
    private function guardSelf(Request $request, User $user, string $action): ?\Illuminate\Http\RedirectResponse
    {
        $me = $request->user();

        if ($me->id === $user->id) {
            $messages = [
                'delete' => 'You cannot delete your own account.',
                'status' => 'You cannot change your own status.',
            ];

            return back()->with('error', $messages[$action] ?? 'You cannot perform that action on your own account.');
        }

        return null;
    }
}
