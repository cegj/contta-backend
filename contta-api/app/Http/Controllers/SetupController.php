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
                return response()->json(["message" => "Não existe banco de dados com o nome informado. Verifique se o banco de dados foi criado e se tem o mesmo nome infomado nas variáveis de ambiente."], 400);
            }

            if (tablesExists()){
                $dbName = getenv('DB_DATABASE'); 
                return response()->json(["message" => "Já existem tabelas no banco de dados. Para realizar uma instalação limpa, apague todas as tabelas por meio do software gerenciador de banco de dados."], 400);
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
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }


    }

    public function createStandardCategories(Request $request){
        try {
            $groupsToCreate = [
                "Automóvel" => ["Combustível", "Estacionamento", "IPVA", "Manutenção veicular", "Multas", "Pedágio", "Seguro veicular", "Outros (Automóvel)"],
                "Compras" => ["Presentes e doações", "Produtos em geral", "Supermercado", "Vestuário", "Outros (Compras)"],
                "Dívidas" => ["Cartões de crédito", "Empréstimos", "Financiamentos", "Outros (Dívidas)"],
                "Educação" => ["Cursos", "Faculdade", "Material escolar", "Outros (Educação)"],
                "Lazer" => ["Cinema e filmes", "Comida e bebida", "Gastos gerais com viagens", "Hospedagem", "Passagens", "Passeios", "Shows e teatro", "Outros (Lazer)"],
                "Moradia" => ["Água e gás", "Aluguel", "Condomínio", "Energia elétrica", "IPTU", "Manutenção residencial", "TV e internet", "Outros (Moradia)"],
                "Pets" => ["Acessórios e brinquedos pet", "Alimentação pet", "Hospedagem pet", "Saúde e higiene pet", "Outros (Pet)"],
                "Receitas" => ["Salário", "Outros (Receitas)"],
                "Saúde" => ["Academia e exercícios", "Consultas e exames", "Medicamentos e produtos", "Plano de saúde", "Outros (Saúde)"],
                "Serviços" => ["Burocracias", "Cabelereiro e estética", "Celular", "Lavanderia", "Seguros diversos", "Serviços online", "Outros (Serviços)"],
                "Transporte" => ["Transporte público", "Táxi/Uber", "Outros (Transporte)"]];
    
            $createdGroups = [];

            foreach ($groupsToCreate as $groupName => $categories){
                $group = new Group;
                $group->name = $groupName;
                $group->save();
                
                foreach ($categories as $categoryName){
                    $category = new Category;
                    $category->name = $categoryName;
                    $category->group_id = $group->id;
                    $category->save();
                }
            };

            return response()->json(["message" => "Grupos e categorias criados com sucesso"], 200);

            } catch (\Throwable $th) {
                return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
            }



    }
}
