<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('orders:auto-cancel')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Render guest OTP requests can arrive before the browser and session
        // are perfectly aligned, so we exempt only these OTP endpoints.
        $middleware->validateCsrfTokens(except: [
            'register/email-otp/send',
            'register/email-otp/verify',
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'agent.approved' => \App\Http\Middleware\CheckAgentApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
