<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/store/list', [StoreController::class, 'list']);
Route::get('/item/list', [ItemController::class, 'list']);
