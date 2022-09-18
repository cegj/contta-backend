<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function teste(){
        $data = ['Olá' => 'Mundo'];

        return response()->json($data, 200);    
    }
}
