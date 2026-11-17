<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_if(! $user?->isAdmin(), 403);

        $search = trim((string) $request->query('search', ''));

        $logs = AuditLog::query()
            ->with(['user:id,name,email,role', 'auditable'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $filters = [
            'search' => $search ?: null,
        ];

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): Response
    {
        $user = $request->user();

        abort_if(! $user?->isAdmin(), 403);

        $auditLog->load(['user:id,name,email,role', 'auditable']);

        $safeProperties = $this->sanitizeProperties($auditLog->properties);

        return Inertia::render('Admin/AuditLogs/Show', [
            'log' => [
                'id' => $auditLog->id,
                'action' => $auditLog->action,
                'description' => $auditLog->description,
                'user' => $auditLog->user ? $auditLog->user->toAuthArray() : null,
                'auditable_type' => $auditLog->auditable_type,
                'auditable_id' => $auditLog->auditable_id,
                'ip_address' => $auditLog->ip_address,
                'user_agent' => $auditLog->user_agent,
                'properties' => $safeProperties,
                'created_at' => $auditLog->created_at,
            ],
        ]);
    }

    private function sanitizeProperties(?array $properties): ?array
    {
        if (! is_array($properties)) {
            return null;
        }

        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'password_hash',
            'remember_token',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'api_key',
            'session_id',
            'csrf_token',
        ];

        return collect($properties)->map(function ($value, $key) use ($sensitiveKeys) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, $sensitiveKeys, true)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                return $this->sanitizeProperties($value);
            }

            return $value;
        })->all();
    }
}
