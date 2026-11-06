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
        // WebRTC session descriptions must keep their trailing CRLF.
        $middleware->trimStrings(except: ['sdp', 'offers.*.sdp']);

        // A tunnel on this machine (e.g. ngrok) serves HTTPS and forwards plain
        // HTTP. Trust its X-Forwarded-* headers so generated URLs are https://
        // (http:// ones are blocked as mixed content) and so each student's
        // real IP is used for rate limits instead of everyone being 127.0.0.1.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
