<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'webhook/shopify/order-create',
            'webhook/shopify/order-paid',
            'webhook/shopify/order-cancelled',
            'webhook/shopify/returns-approve',
            'webhook/shopify/order-fulfilled',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
