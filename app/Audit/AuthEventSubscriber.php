<?php

namespace App\Audit;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * Records sign-ins, sign-outs, and failed attempts. Passwords are never recorded.
 */
class AuthEventSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
            Lockout::class => 'onLockout',
        ];
    }

    public function onLogin(Login $event): void
    {
        AuditLogger::record('auth.login', "{$event->user->name} signed in.", $event->user, (string) $event->user->name, [], $event->user);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user) {
            AuditLogger::record('auth.logout', "{$event->user->name} signed out.", $event->user, (string) $event->user->name, [], $event->user);
        }
    }

    public function onFailed(Failed $event): void
    {
        $email = mb_substr((string) ($event->credentials['email'] ?? 'unknown'), 0, 255);

        AuditLogger::record('auth.failed', "Failed sign-in attempt for {$email}.", null, $email, [], null);
    }

    public function onLockout(Lockout $event): void
    {
        $email = mb_substr((string) $event->request->input('email', 'unknown'), 0, 255);

        AuditLogger::record('auth.lockout', "Too many sign-in attempts for {$email}. Sign-in is paused for a while.", null, $email, [], null);
    }
}
