<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\Sms\ProcessDeliveryStatusJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Inertia: share auth/flash/school props with every page ----------
        $middleware->web(append: HandleInertiaRequests::class);

        // RBAC: /dashboard/* routes only for the matching role -------------
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Poll DARSMS delivery status for pending SMS messages every 5 min.
        // withoutOverlapping() prevents concurrent duplicate polling runs.
        $schedule->job(new ProcessDeliveryStatusJob, 'sms')
            ->everyFiveMinutes()
            ->name('sms.delivery-status-poll')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
