<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

    // 🎯 گارد پابلیک آی‌پورت مهندس کیسکا برای لاراول 11
        $middleware->validateCsrfTokens(except: [
            'api/*' // دور زدن توکن CSRF برای تمام روت‌های API
        ]);

        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        // تنظیم داینامیک اوریجین‌ها
        $middleware->statefulApi();
        
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
