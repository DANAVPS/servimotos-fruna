<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Excepción de CSRF para el webhook de WhatsApp
        $middleware->validateCsrfTokens(except: [
            'webhook/whatsapp',
        ]);

        // Aliases para middlewares
        $middleware->alias([
            'tenant' => \App\Http\Middleware\SetTenantContext::class,
            'role'   => \App\Http\Middleware\EnsureRole::class,
        ]);

        // Aplicar el contexto del tenant a todas las rutas web
        $middleware->appendToGroup('web', \App\Http\Middleware\SetTenantContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
