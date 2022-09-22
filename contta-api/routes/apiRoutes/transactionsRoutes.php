<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::post('/', [TransactionController::class, 'create']);
});