<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes entries to the audit log.
 *
 * A problem while writing the log is reported to storage/logs/laravel.log and never
 * stops the action that was being logged.
 */
class AuditLogger
{
    /**
     * @param array<string, mixed> $changes  for example ['status' => ['pending', 'confirmed']]
     * @param User|null|false $actor  false (default) = whoever is signed in; null = the system
     */
    public static function record(
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $subjectLabel = null,
        array $changes = [],
        User|null|false $actor = false,
    ): void {
        try {
            if ($actor === false) {
                $actor = Auth::user();
            }

            $request = app()->runningInConsole() ? null : request();

            AuditLog::create([
                'user_id' => $actor?->getKey(),
                'user_name' => $actor ? mb_substr((string) $actor->name, 0, 255) : null,
                'user_role' => $actor ? (string) $actor->role : null,
                'action' => $action,
                'subject_type' => $subject ? strtolower(class_basename($subject)) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel !== null ? mb_substr($subjectLabel, 0, 255) : null,
                'description' => $description,
                'changes' => $changes === [] ? null : $changes,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning("Audit log entry '{$action}' could not be saved: " . $e->getMessage());
        }
    }
}
