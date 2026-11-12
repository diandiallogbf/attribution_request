<?php

use App\Exceptions\Domain\DomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Psr\Log\LogLevel;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Erreurs métier attendues : journalisées en avertissement (avec leur contexte), jamais en erreur.
        $exceptions->level(DomainException::class, LogLevel::WARNING);

        // Hors Livewire (qui affiche un toast via HandlesDomainExceptions) : message lisible, jamais de page 500.
        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['title' => $exception->title(), 'message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        });
    })->create();
