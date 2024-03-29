<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::get('/', [TransactionController::class, 'getTransactions']);
  Route::get('/search', [TransactionController::class, 'searchTransactions']);
  Route::get('/{id}', [TransactionController::class, 'getTransactionById']);
  Route::post('/incomes', [TransactionController::class, 'storeIncome']);
  Route::post('/expenses', [TransactionController::class, 'storeExpense']);
  Route::post('/transfers', [TransactionController::class, 'storeTransfer']);
  Route::post('/initialbalances', [TransactionController::class, 'storeInitialBalance']);
  Route::patch('/incomes/{id}', [TransactionController::class, 'editIncome']);
  Route::patch('/expenses/{id}', [TransactionController::class, 'editExpense']);
  Route::patch('/transfers/{id}', [TransactionController::class, 'editTransfer']);
  Route::patch('/initialbalances/{accountId}', [TransactionController::class, 'editInitialBalance']);
  Route::delete('/incomes/{id}', [TransactionController::class, 'deleteIncomeExpense']);
  Route::delete('/expenses/{id}', [TransactionController::class, 'deleteIncomeExpense']);
  Route::delete('/transfers/{id}', [TransactionController::class, 'deleteTransfer']);
  Route::delete('/initialbalances/{accountId}', [TransactionController::class, 'deleteInitialBalance']);
});