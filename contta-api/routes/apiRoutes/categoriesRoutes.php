<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GroupController;

// Protected routes
Route::group(['middleware' => ['protectedRoute']], function(){
  Route::post('/groups', [GroupController::class, 'createGroup']);
  Route::patch('/groups/{id}', [GroupController::class, 'editGroup']);
  Route::delete('/groups/{id}', [GroupController::class, 'deleteGroup']);
  Route::get('/groups/{id}', [GroupController::class, 'getGroupById']);
  Route::get('/groups', [GroupController::class, 'getAllGroups']);

  Route::post('/', [CategoryController::class, 'createCategory']);
  Route::patch('/{id}', [CategoryController::class, 'editCategory']);
  Route::delete('/{id}', [CategoryController::class, 'deleteCategory']);
  Route::get('/{id}', [CategoryController::class, 'getCategoryById']);
  Route::get('/', [CategoryController::class, 'getAllCategories']);
});