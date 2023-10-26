<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\{
    InventarioController,
    ArticuloController
};

Route::get('/', function () {
    return view('welcome');
});

Route::resource('inventory', InventarioController::class);
Route::resource('article', ArticuloController::class);

Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});