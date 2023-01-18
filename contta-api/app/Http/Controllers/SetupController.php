<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Account;
use App\Models\Category;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\User;


class SetupController extends Controller
{

    public function setupDatabase(Request $request){

        try {

            function databaseExists(){
            $dbName = getenv('DB_DATABASE'); 
            $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  ?";
            $db = DB::select($query, [$dbName]);
            if (empty($db)) {
                return false;
            } else return true;
            };

            function tablesExists(){
                $checkResults = [
                    Schema::hasTable('accounts'),
                    Schema::hasTable('categories'),
                    Schema::hasTable('groups'),
                    Schema::hasTable('transactions'),
                    Schema::hasTable('users')    
                ];
                foreach ($checkResults as $result){
                    if ($result) return true;
                }
                return false;
            };

            if (!databaseExists()){
                $dbName = getenv('DB_DATABASE'); 
                return response()->json(["message" => "Não existe banco de dados com o nome informado."], 400);
            }

            if (tablesExists()){
                $dbName = getenv('DB_DATABASE'); 
                return response()->json(["message" => "Já existem tabelas no banco de dados."], 400);
            }

            Artisan::call('config:clear');
            Artisan::call('migrate:fresh --force');
            Artisan::call('config:clear');
            
            if (tablesExists()){
                return response()->json(["message" => "Banco de dados configurado com sucesso"], 200);
            } else {
                return response()->json(["message" => "Ocorreu um erro ao configurar o banco de dados."], 500);
            }
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }


    }

    public function checkTables(Request $request){

        try {

            $response = [];

            function tablesExists(){
                $checkResults = [
                    Schema::hasTable('accounts'),
                    Schema::hasTable('categories'),
                    Schema::hasTable('groups'),
                    Schema::hasTable('transactions'),
                    Schema::hasTable('users')    
                ];
                foreach ($checkResults as $result){
                    if ($result) return true;
                }
                return false;
            };

            if (tablesExists()){
                return response()->json(["message" => "Verificação de tabelas concluída com sucesso", 'hasNoTables' => false], 201);
            } else {
                return response()->json(["message" => "Verificação de tabelas concluída com sucesso", "hasNoTables" => true], 201);
            }

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }


    }
}
