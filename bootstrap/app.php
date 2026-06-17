<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Don't redirect unauthenticated API requests to a 'login' route
        // (there isn't one) — let them surface as a 401 JSON response.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always render API errors as JSON (the mobile client may not send an
        // Accept: application/json header).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Unauthenticated API calls must return 401 JSON, not a redirect to a
        // non-existent 'login' route (which would 500).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login again.',
                    'data' => null,
                ], 401);
            }

            return null;
        });

        // Validation errors -> our standard envelope, with the FIRST specific
        // message (e.g. "Enter a valid 10-digit mobile number.") in `message`,
        // and all field errors in `data.errors`.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first(),
                    'data' => ['errors' => $e->errors()],
                ], 422);
            }

            return null;
        });
    })->create();
