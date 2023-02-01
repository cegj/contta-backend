<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{

    public function createCategory(Request $request){
        /**
         * name: string
         * group_id: integer
         */

        try {
            if (!$request->name) {
                return response()->json(['message' => 'O nome da categoria é obrigatória'], 422);
            }

            if (!$request->group_id) {
                return response()->json(['message' => 'O id do grupo é obrigatório'], 422);
            }
    
            $category = new Category;
            $category->name = $request->name;
            $category->group_id = $request->group_id;
            $category->save();
        
            return response()->json(["message" => "Categoria com sucesso", "category" => $category], 200);
            } catch (\Throwable $th) {
                return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function editCategory(Request $request, $id){
        /**
         * name: string
         * group_id: integer
         */
        try {

            $category = Category::find($id);
            if (!$category){
                return response()->json(['message' => 'Categoria não encontrada (id inválido)'], 400);
            }
            $category->name = $request->name ?? $category->name;
            $category->group_id = $request->group_id ?? $category->group_id;
            $category->save();
        
            return response()->json(["message" => "Categoria editada com sucesso", "category" => $category], 200);
            } catch (\Throwable $th) {
                return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }


    public function deleteCategory(Request $request, $id){
    
        try {

            $category = Category::find($id);
            if (!$category){
                return response()->json(["message" => "A categoria não foi localizada (id inválido)"], 400);
            }
            $category->delete();
        
            return response()->json(["message" => "Categoria {$category->name} removida com sucesso"], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function getCategoryById(Request $request, $id){

        try {

            $category = Category::find($id);
            if (!$category){
                return response()->json(["message" => "A categoria não foi localizada (id inválido)"], 400);
            }
            $category->group;

            if ($category){
                return response()->json(["message" => "Categoria recuperada com sucesso", "category" => $category], 200);
            } else {
                return response()->json(["message" => "Categoria não localizada", "category" => $category], 200);
            }

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function getAllCategories(Request $request){

        try {
            $categories = Category::all();

            foreach ($categories as $category){
                $category->group;
            }

            return response()->json(["message" => "Categorias recuperadas com sucesso", "category" => $categories], 200); 

        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
