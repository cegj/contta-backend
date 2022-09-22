<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\A;
use App\Http\Controllers\AuthController;

Route::post('/create', [UserController::class, 'create']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::get('/', [AuthController::class, 'me']);
  Route::patch('/', [UserController::class, 'edit']);
  Route::delete('/', [UserController::class, 'delete']);
});