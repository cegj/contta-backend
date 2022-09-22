<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::post('/groups', [CategoryController::class, 'createGroup']);
  Route::patch('/groups', [CategoryController::class, 'editGroup']);
  Route::delete('/groups', [CategoryController::class, 'deleteGroup']);
  Route::get('/groups', [CategoryController::class, 'getAllGroups']);
  Route::get('/groups/{id}', [CategoryController::class, 'getGroupById']);

  Route::post('/', [CategoryController::class, 'createCategory']);
  Route::patch('/', [CategoryController::class, 'editCategory']);
  Route::delete('/', [CategoryController::class, 'deleteCategory']);
  Route::get('/', [CategoryController::class, 'getAllCategorys']);
  Route::get('/{id}', [CategoryController::class, 'getCategoryById']);
});