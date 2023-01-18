<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SetupController;

Route::get('/checktables', [SetupController::class, 'checkTables']);
Route::post('/database', [SetupController::class, 'setupDatabase']);

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::post('/categories', [SetupController::class, 'createStandardCategories']);
});