<?php

use App\Http\Controllers\CodeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'version' => '1.0.0',
]));

Route::prefix('code')->controller(CodeController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('search', 'search');
});

Route::prefix('store')->controller(StoreController::class)->group(function (): void {
    Route::get('/', 'index');
});

Route::prefix('item')->controller(ItemController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

Route::prefix('label')->controller(LabelController::class)->group(function (): void {
    Route::post('generate', 'generate');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
