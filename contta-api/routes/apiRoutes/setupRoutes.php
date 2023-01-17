<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SetupController;

Route::get('/checktables', [SetupController::class, 'checkTables']);
Route::get('/database', [SetupController::class, 'setupDatabase']);