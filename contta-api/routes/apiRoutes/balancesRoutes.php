<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BalanceController;

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::get('/', [BalanceController::class, 'getBalance']);
  Route::get('/budget', [BalanceController::class, 'getBalanceForBudget']);
  Route::get('/month', [BalanceController::class, 'getAllDaysOfMonthBalance']);
  Route::get('/allcategories', [BalanceController::class, 'getAllCategoriesBalance']);

});