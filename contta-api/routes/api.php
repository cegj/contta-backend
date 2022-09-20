<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->group(base_path('routes/apiRoutes/usersRoutes.php'));
Route::prefix('accounts')->group(base_path('routes/apiRoutes/accountsRoutes.php'));