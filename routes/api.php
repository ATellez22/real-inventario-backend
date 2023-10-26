<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\{
    InventarioController,
    ArticuloController
};

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


    // Route::resource('inventory', InventarioController::class);
    // Route::resource('article', ArticuloController::class);

 
