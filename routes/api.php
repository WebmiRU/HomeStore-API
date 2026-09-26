<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LabelListController;
use App\Http\Controllers\LabelPresetController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyGroupController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ThumbnailController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WarehouseController;
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

Route::get('/image/{thumb}/{hash}', [ThumbnailController::class, 'show'])
    ->where('thumb', '[A-Za-z0-9_\-]+')
    ->where('hash', '[0-9a-f]{64}');

Route::post('/login', [UserAuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth.token')->group(function (): void {

Route::post('/logout', [UserAuthController::class, 'logout']);

Route::get('/search', [SearchController::class, 'search']);

Route::prefix('access')->controller(AccessController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('warehouse/{model}', 'forWarehouse');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

Route::prefix('audit-log')->controller(AuditLogController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('stats', 'stats');
    Route::get('balance', 'balance');
});

// Каталог предметов. Набор свойств у категории вычисляется по уже
// заполненным значениям, поэтому отдельный маршрут и возвращает его
// по требованию, а не лежит в теле категории.
Route::prefix('category')->controller(CategoryController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::get('{model}/properties', 'properties');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

Route::prefix('property')->controller(PropertyController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

Route::prefix('property-group')->controller(PropertyGroupController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

// Значения справочника живут под справочником: своего user_id у них нет,
// и top-level маршруты были бы путём к чужому справочнику.
Route::prefix('dictionary')->controller(DictionaryController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
    Route::get('{model}/values', 'values');
    Route::post('{model}/values', 'storeValue');
    Route::put('{model}/values/{value}', 'updateValue');
    Route::delete('{model}/values/{value}', 'deleteValue');
});

Route::prefix('unit')->controller(UnitController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

Route::prefix('code')->controller(CodeController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('search', 'search');
    Route::get('orphans', 'orphans');
    Route::get('orphans/preview', 'orphansPreview');
    Route::delete('orphans', 'destroyOrphans');
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

Route::prefix('image')->controller(ImageController::class)->group(function (): void {
    Route::post('item/{model}', 'storeForItem');
    Route::post('store/{model}', 'storeForStore');
    Route::patch('item/{model}/image/{image}/alt', 'updateAltForItem');
    Route::patch('store/{model}/image/{image}/alt', 'updateAltForStore');
    Route::post('item/{model}/image/reorder', 'reorderForItem');
    Route::post('store/{model}/image/reorder', 'reorderForStore');
    Route::delete('item/{model}/image/{image}', 'removeForItem');
    Route::delete('store/{model}/image/{image}', 'removeForStore');
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
    Route::post('/blank', 'blank');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
    Route::post('{labelList}/item/{item}', 'attachItem');
    Route::delete('{labelList}/item/{item}', 'detachItem');
    Route::post('{labelList}/store/{store}', 'attachStore');
    Route::delete('{labelList}/store/{store}', 'detachStore');
});

Route::prefix('user')->controller(UserProfileController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::post('{model}/avatar', 'updateAvatar');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

Route::prefix('warehouse')->controller(WarehouseController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('all', 'all');
    Route::get('{model}', 'get');
    Route::post('/', 'post');
    Route::put('{model}', 'put');
    Route::delete('{model}', 'delete');
});

});
