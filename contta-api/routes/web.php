<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(["message" => "Bem vindo à API do Contta"], 200);
});