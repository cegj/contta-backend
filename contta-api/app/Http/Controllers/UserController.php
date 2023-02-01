<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    public function create(Request $request){

        /**
         * name: string
         * email: string
         * password: string
         * confirm_password: string
         */

        try {
        // Check if fields are not empty
        if (!$request->name) {
            return response()->json(['message' => 'O nome é obrigatório'], 422);
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

        $emailTaken = User::where('email', $request->email)->get();

        // Check if password matches confirmpassword
        if (sizeof($emailTaken) !== 0) {
            return response()->json(['message' => 'O e-mail já está em uso', 'user' => $emailTaken], 422);
        }

        $user = new User;

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
        $user->email = $request->email;
        $user->password = $hashedPassword;
        $user->access_level = $accessLevel;
        $user->save();

        $user->password = null;

        return response()->json(["message" => "Usuário criado com sucesso", "createdUser" => $user], 201);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function edit(Request $request){

        /**
         * name: string
         * email: string
         * password: string
         * confirm_password: string
        */

        try {
            $authUser = JWTAuth::parseToken()->toUser();
    
            if ($request->password) {
                if (!$request->confirm_password) {
                    return response()->json(['message' => 'Para alterar a senha, a confirmação de senha é obrigatória'], 422);
                }
    
                if ($request->password !== $request->confirm_password) {
                    return response()->json(['message' => 'A senha e a confirmação de senha devem ser iguais'], 422);
                }
    
                //Create password hash
                $hashedPassword = Hash::make($request->password);
            }
    
            if ($request->email){
                $emailTaken = User::where('email', $request->email)->first();
    
                // Check if password matches confirmpassword
                if (!empty($emailTaken)) {
                    if ($emailTaken->id !== $authUser->id){
                        return response()->json(['message' => 'O e-mail já está em uso'], 422);
                    }
                }    
            }
    
            $user = User::find($authUser->id);
    
            $user->name = $request->name ?? $user->name;
            $user->email = $request->email ?? $user->email;
            $user->password = $request->password ? $hashedPassword : $user->password;
            $user->save();
    
            $user->password = null;
    
            return response()->json(["message" => "Usuário editado com sucesso", "editedUser" => $user], 200);

            } catch (\Throwable $th) {
                return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function delete(Request $request){
    
        try {

            $authUser = JWTAuth::parseToken()->toUser();

            User::destroy($authUser->id);
        
            return response()->json(["message" => "Usuário {$authUser->email} excluído com sucesso"], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }
}
