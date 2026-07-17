<?php

use Illuminate\Auth\AuthenticationException;
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
        // This app is API-only — there is no `login` web route to redirect to. By default,
        // Laravel's guest-redirect closure eagerly calls route('login') to build the redirect
        // target whenever a request doesn't explicitly send `Accept: application/json`, and
        // that call itself throws (RouteNotFoundException, a 500) before the clean 401 in
        // withExceptions() below ever gets a chance to run. Returning null here means "never
        // redirect" — always fall through to a thrown AuthenticationException instead.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })->create();
