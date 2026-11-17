<?php

namespace App\Http\Controllers\Homework;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HOMEWORK — Teachers post homework for their assigned classes.
 * Head of School and Admin view all homework school-wide.
 */
class HomeworkController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Homework::query()
            ->with(['schoolClass:id,name,code', 'subject:id,name,code', 'teacher:id,name']);

        if ($user->hasAnyRole([UserRole::Teacher])) {
            $classIds = $user->classesTaught()->pluck('classes.id')->all();
            $query->where(function ($q) use ($classIds, $user) {
                $q->whereIn('class_id', $classIds)
                    ->where('teacher_id', $user->id);
            });
        }

        $homeworks = $query->latest('due_on')->paginate(12)->withQueryString();

        return Inertia::render('Homework/Index', [
            'homeworks' => $homeworks->through(fn (Homework $h) => [
                'id' => $h->id,
                'title' => $h->title,
                'description' => $h->description,
                'type' => $h->type->value,
                'status' => $h->status,
                'assigned_on' => $h->assigned_on?->toDateString(),
                'due_on' => $h->due_on?->toDateString(),
                'class' => $h->schoolClass?->name,
                'class_code' => $h->schoolClass?->code,
                'subject' => $h->subject?->name,
                'teacher' => $h->teacher?->name,
                'attachments' => $h->attachments,
                'is_own' => $h->teacher_id === $user->id,
            ]),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code']),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $request->only('class_id', 'subject_id', 'status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $classIds = $user->hasAnyRole([UserRole::Teacher])
            ? $user->classesTaught()->pluck('classes.id')->all()
            : [];

        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'assigned_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:assigned_on'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        // Teachers can only create homework for their own classes
        if ($user->hasAnyRole([UserRole::Teacher])) {
            abort_unless(in_array($data['class_id'], $classIds, true), 403);
            $data['teacher_id'] = $user->id;
        } else {
            $data['teacher_id'] = $data['teacher_id'] ?? $user->id;
        }

        $homework = Homework::create($data);

        AuditLog::record(
            $user,
            'homework.created',
            "Created homework \"{$homework->title}\"",
            $homework
        );

        return redirect()->route('homework.index')
            ->with('success', 'Homework posted successfully.');
    }

    public function update(Request $request, Homework $homework): RedirectResponse
    {
        $user = $request->user();

        // Teachers can only edit their own homework
        if ($user->hasAnyRole([UserRole::Teacher])) {
            abort_unless($homework->teacher_id === $user->id, 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'required', 'string'],
            'due_on' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        $homework->update($data);

        return redirect()->route('homework.index')
            ->with('success', 'Homework updated.');
    }

    public function destroy(Request $request, Homework $homework): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasAnyRole([UserRole::Teacher])) {
            abort_unless($homework->teacher_id === $user->id, 403);
        }

        $homework->delete();

        return back()->with('success', 'Homework removed.');
    }
}
