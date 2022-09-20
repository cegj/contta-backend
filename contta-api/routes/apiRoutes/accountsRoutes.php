<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::post('/', [AccountController::class, 'create']);
  Route::patch('/{id}', [AccountController::class, 'edit']);
  Route::delete('/{id}', [AccountController::class, 'delete']);
  Route::get('/', [AccountController::class, 'getAll']);
  Route::get('/{id}', [AccountController::class, 'getById']);
});