<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function(){
  return response()->json(["message" => "Bem vindo à API do Contta"], 200);
});

Route::prefix('users')->group(base_path('routes/apiRoutes/usersRoutes.php'));
Route::prefix('accounts')->group(base_path('routes/apiRoutes/accountsRoutes.php'));
Route::prefix('categories')->group(base_path('routes/apiRoutes/categoriesRoutes.php'));