<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function createGroup(){
        return response()->json(["message" => "Usuário editado com sucesso"], 200);
    }
}
