<?php

use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\TransferNotAllowed;
use App\Http\Middleware\AuthenticateClient;
use App\Http\Middleware\CorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [CorrelationId::class]);
        $middleware->alias(['client.auth' => AuthenticateClient::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(fn (InsufficientFunds $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (IdempotencyConflict $e) => response()->json(['message' => $e->getMessage()], 409));
        $exceptions->render(fn (TransferNotAllowed $e) => response()->json(['message' => $e->getMessage()], 422));
    })->create();
