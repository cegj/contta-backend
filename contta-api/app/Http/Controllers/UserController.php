<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    public function store(Request $request){

        $user = new User;

        // Check if fields are not empty
        if (!$request->name) {
            return response()->json(['message' => 'O nome é obrigatório'], 422);
        }

        if (!$request->username) {
            return response()->json(['username' => 'O nome de usuário é obrigatório'], 422);
        }

        if (!$request->email) {
            return response()->json(['message' => 'O e-mail é obrigatório'], 422);
        }

        if (!$request->password) {
            return response()->json(['message' => 'A senha é obrigatória'], 422);
        }

        if (!$request->confirm_password) {
            return response()->json(['message' => 'A confirmação de senha é obrigatória'], 422);
        }

        // Check if password matches confirmpassword
        if ($request->password !== $request->confirm_password) {
            return response()->json(['message' => 'A senha e a confirmação de senha devem ser iguais'], 422);
        }

        //Define user has admin if there is no user yet
        if (!$user::exists()){
            $accessLevel = 1;
        } else {
            $accessLevel = 2;
        }

        //Create password hash
        $hashedPassword = Hash::make($request->password);

        //Create user at model
        $user->name = $request->name;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->password = $hashedPassword;
        $user->access_level = $accessLevel;
        $user->save();

        $createdUser = user::latest()->first(); 

        return response()->json(["message" => "Usuário criado com sucesso", "createdUser" => $createdUser], 200);}
}
