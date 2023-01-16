<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;

class AccountController extends Controller
{
    public function create(Request $request){

        /**
         * name: string
         * type: string
         * initial_balance: integer
         * show: boolean
         */


        try {

        // Check if fields are not empty
        if (!$request->name) {
            return response()->json(['message' => 'O nome da conta é obrigatório'], 422);
        }

        if (!$request->type) {
            return response()->json(['message' => 'O tipo de conta é obrigatório'], 422);
        }

        if (strtolower($request->type) !== 'conta bancária'
        && strtolower($request->type) !== 'cartão de crédito'
        && strtolower($request->type) !== 'carteira'
        && strtolower($request->type) !== 'investimentos') {
            return response()->json(['message' => "O tipo de conta deve ser: conta bancária, cartão de crédito, carteira ou investimentos. O valor informado foi: {$request->type}"], 422);
        }

        if (!$request->initial_balance) {
            $request->initial_balance = 0;
        }

        if (gettype($request->show) !== 'boolean') {
            $request->show = true;
        }

        //Create account at database
        $account = new Account;
        $account->name = $request->name;
        $account->type = $request->type;
        $account->initial_balance = $request->initial_balance;
        $account->show = $request->show;
        $account->save();

        return response()->json(["message" => "Conta criada com sucesso", "createdAccount" => $account], 201);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function edit(Request $request, $id){

        /**
         * name: string
         * type: string
         * initial_balance: integer
         * show: boolean
         */


        try {

    
            if ($request->type) {
                if (strtolower($request->type) !== 'conta bancária'
                && strtolower($request->type) !== 'cartão de crédito'
                && strtolower($request->type) !== 'carteira'
                && strtolower($request->type) !== 'investimentos') {
                    return response()->json(['message' => "O tipo de conta deve ser: conta bancária, cartão de crédito, carteira ou investimentos. O valor informado foi: {$request->type}"], 422);
                }
            }

            if ($request->initial_balance) {
                if (gettype($request->initial_balance) !== 'integer')
                return response()->json(['message' => "O saldo inicial da conta deve ser um número do tipo inteiro"], 422);
            }

            if ($request->show) {
                if (gettype($request->show) !== 'boolean')
                return response()->json(['message' => "A opção de exibir a conta deve ser booleana (true ou false)"], 422);
            }
        
            $account = Account::find($id);
    
            $account->name = $request->name ?? $account->name;
            $account->type = $request->type ?? $account->type;
            $account->initial_balance = $request->initial_balance ?? $account->initial_balance;

            if (gettype($request->show) == 'boolean') {
                $account->show = $request->show;
            } else {
                $account->show = $account->show;
            }

            $account->save();
        
            return response()->json(["message" => "Conta editada com sucesso", "editedAccount" => $account], 200);

            } catch (\Throwable $th) {
                return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function delete(Request $request, $id){
    
        try {

            $account = Account::find($id);

            if (!$account){
                return response()->json(["message" => "A conta não foi localizado (id inválido)"], 422);
            }

            $account->delete();
        
            return response()->json(["message" => "Conta {$account->name} removida com sucesso"], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function getAll(Request $request){

        try {

            $ignorehidden = $request->query('ignorehidden');

            //If query param ignorehidden = true, do not get hidden accounts
            if ($ignorehidden === "true"){
                $accounts = Account::where('show', 1)->get();
            } else {
                $accounts = Account::all();
            }

            $unset = ["id" => 0,
            "name" => "Sem conta",
            "type" => "Sem conta",
            "initial_balance" => null,
            "show" => null,
            "created_at" => null,
            "updated_at"=> null
            ];

            $accounts->push($unset);

            return response()->json(["message" => "Contas recuperadas com sucesso", "accounts" => $accounts], 200);
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }

    }

    public function getById(Request $request, $id){

        try {

            $account = Account::find($id);

            if ($account){
                return response()->json(["message" => "Conta recuperada com sucesso", "account" => $account], 200);
            } else {
                return response()->json(["message" => "Conta não localizada", "account" => $account], 200);
            }

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }


}
