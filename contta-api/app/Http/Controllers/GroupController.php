<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;

class GroupController extends Controller
{
    public function createGroup(Request $request){
        /**
         * name: string
         */
        try {
            if (!$request->name) {
                return response()->json(['message' => 'O nome do grupo é obrigatório'], 422);
            }
    
            $group = new Group;
            $group->name = $request->name;
            $group->save();
        
            return response()->json(["message" => "Grupo criado com sucesso", "group" => $group], 200);
            } catch (\Throwable $th) {
                return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
            }
    }

    public function editGroup(Request $request, $id){
        /**
         * name: string
         */
        try {

            $group = Group::find($id);
            if (!$group){
                return response()->json(["message" => "O grupo não foi localizado (id inválido)"], 400);
            }
            $group->name = $request->name ?? $group->name;
            $group->save();
        
            return response()->json(["message" => "Grupo editado com sucesso", "group" => $group], 200);
            } catch (\Throwable $th) {
                return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function deleteGroup(Request $request, $id){
    
        try {

            $group = Group::find($id);

            if (!$group){
                return response()->json(["message" => "O grupo não foi localizado (id inválido)"], 400);
            }

            $group->delete();
        
            return response()->json(["message" => "Grupo {$group->name} removido com sucesso"], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function getGroupById(Request $request, $id){

        try {

            $group = Group::find($id);
            if (!$group){
                return response()->json(["message" => "O grupo não foi localizado (id inválido)"], 400);
            }
            $group->categories;

            if ($group){
                return response()->json(["message" => "Grupo recuperado com sucesso", "group" => $group], 200);
            } else {
                return response()->json(["message" => "Grupo não localizado", "group" => $group], 200);
            }

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function getAllGroups(Request $request){

        try {
            $groups = Group::all();

            foreach ($groups as $group){
                $group->categories;
            }

            $unset = [
                "id" => 0,
                "name" => "Sem categoria",
                "created_at" => null,
                "updated_at" => null,
                "categories" => [
                    [
                        "id" => 0,
                        "name" => "Sem categoria",
                        "group_id" => 0,
                        "created_at" => null,
                        "updated_at" => null
                    ]]];

            $groups->push($unset);

            return response()->json(["message" => "Grupos e categorias recuperados com sucesso", "groups" => $groups], 200); 

        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
