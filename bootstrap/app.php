<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TCPDF Font Path
|--------------------------------------------------------------------------
|
| TCPDF 7.x uses tc-lib-pdf-font for font handling. By default it resolves
| the font path relative to the tcpdf package directory, which is incorrect
| when installed as a Composer dependency. We override it to point to the
| correct location where font JSON files are shipped.
|
| The generated fonts are committed in resources/pdf/fonts and copied into
| the vendor tree at image build time. To regenerate after a tc-lib-pdf-font
| upgrade run:
|   vendor/tecnickcom/tc-lib-pdf-font/bin/build_fonts.sh (or `make fonts`)
| and update resources/pdf/fonts.
*/
if (!defined('K_PATH_FONTS')) {
    $fontsPath = realpath(__DIR__ . '/../vendor/tecnickcom/tc-lib-pdf-font/target/fonts/');
    define('K_PATH_FONTS', $fontsPath !== false ? $fontsPath . '/' : '');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->group(__DIR__.'/../routes/api.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );
    })->create();
