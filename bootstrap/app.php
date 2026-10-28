<?php

use App\Exceptions\Hapi\HapiException;
use App\Http\Middleware\EnsureInternalNetwork;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'portal' => EnsurePortalAccess::class,
            'portal.internal' => EnsureInternalNetwork::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Card details must never be written to the session as "old input".
        $exceptions->dontFlash(['card_number', 'security_code', 'expiry_date']);

        $exceptions->dontReport(HapiException::class);

        $exceptions->render(fn (HapiException $exception, Request $request) => Inertia::render('Message', [
            'tone' => 'unavailable',
            'heading' => 'Temporarily unavailable',
            'message' => 'We could not load your booking just now. Please try again in a few minutes.',
        ])->toResponse($request)->setStatusCode(503));

        // An expired session (e.g. a tab left open overnight) goes back with a notice instead of a 419 page.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() === 419) {
                return back()->with('warning', 'Your session expired. Please try again.');
            }

            return $response;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
