<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

Route::post('/create', [UserController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::group(['middleware' => ['apiJwt']], function(){
  Route::get('/', function(){return "Oi";});
});