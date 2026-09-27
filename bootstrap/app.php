<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentClan;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The theme cookie is written by the browser (paper / lamplight), so it isn't encrypted.
        $middleware->encryptCookies(except: ['theme']);
        $middleware->web(append: [
            SetCurrentClan::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // A photo bigger than PHP accepts: say so in words, don't show an error page.
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => back()->withErrors([
            'file' => 'That image is larger than the upload limit ('.ini_get('post_max_size').'). Start the app with start.bat, which raises it, or use a smaller copy.',
        ]));
    })->create();
