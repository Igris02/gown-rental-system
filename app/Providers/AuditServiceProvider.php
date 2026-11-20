<?php

namespace App\Providers;

use App\Audit\AuthEventSubscriber;
use App\Models\Gown;
use App\Models\Reservation;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gown::observe(AuditObserver::class);
        Reservation::observe(AuditObserver::class);
        User::observe(AuditObserver::class);

        Event::subscribe(AuthEventSubscriber::class);
    }
}
