<?php

use App\Http\Controllers\CodeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LabelListController;
use App\Http\Controllers\LabelPresetController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\SearchController;
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

Route::get('/search', [SearchController::class, 'search']);

Route::prefix('code')->controller(CodeController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('search', 'search');
});

Route::prefix('operation')->controller(OperationController::class)->group(function (): void {
    Route::post('/', 'store');
});

Route::prefix('store')->controller(StoreController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
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

Route::prefix('label-preset')->controller(LabelPresetController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

Route::prefix('label-list')->controller(LabelListController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{labelList}/generate', 'generate');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
    Route::post('{labelList}/item/{item}', 'attachItem');
    Route::delete('{labelList}/item/{item}', 'detachItem');
    Route::post('{labelList}/store/{store}', 'attachStore');
    Route::delete('{labelList}/store/{store}', 'detachStore');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
