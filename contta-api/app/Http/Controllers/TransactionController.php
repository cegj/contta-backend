<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Transaction;
use DateTime;

class TransactionController extends Controller
{
    private function validateDate($dateStr){
        $date = explode( '/', $dateStr );
        return checkdate($date[1], $date[0], $date[2]);
    }

    private function convertDate($dateStr){
        $date = explode( '/', $dateStr );
        return "{$date[2]}-{$date[1]}-{$date[0]}";
    }


    private function validateValue($value){

        $int = (int)$value;
        $float = (float)$value;

        if ($int == $float && (int)$value !== 0){
            return true;
        } else {
            return false;
        }
    }
    
    public function storeIncome(Request $request){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * date: string
         * value: integer
         * description: string
         * category_id: integer
         * account_id: integer
         * preview: string/boolean
         * usual: string/boolean
         * total_installments: integer
         */

        try {
            $date = $request->date;
            $value = $request->value;
            $description = $request->description;
            $category_id = $request->category_id;
            $account_id = $request->account_id;
            $preview = ($request->preview === true || $request->preview === "true") ? 1 : 0; //False (0) as default
            $usual =  ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
            $total_installments = (int)$request->total_installments > 0 ? (int)$request->total_installments : 1; //1 (one) as default
    
            if (!$this->validateDate($date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro diferente de zero"], 400);
            }
    
            if (!strlen($description)){
                return response()->json(["message" => "É necessário informar uma descrição para a transação"], 400);
            }
    
            if (!$category_id){
                return response()->json(["message" => "É necessário informar uma categoria para a transação"], 400);
            }  
            
            if (!$account_id){
                return response()->json(["message" => "É necessário informar uma conta para a transação"], 400);
            } 
    
            // Get user id from auth user
            $user_id = JWTAuth::parseToken()->toUser()->id;
    
            // Generate installment_key if there are more than one stallment
            $installments_key = ($total_installments > 1) ? random_int(1, 9) . time() . random_int(1, 9) : 0;
    
            //Values who changes according to the stallment
            $installment = 1;
            $date = new DateTime($request->date);
            $dateStr = $date->format('Y-m-d');
            $storedTransactions = [];
            
            while($installment <= $total_installments){
                $transaction = new Transaction;
                $transaction->date = $dateStr;
                $transaction->type = 'R';
                $transaction->value = (int)$value;
                $transaction->description = $description;
                $transaction->category_id = (int)$category_id;
                $transaction->account_id = (int)$account_id;
                $transaction->user_id = (int)$user_id;
                $transaction->preview = $preview;
                $transaction->usual = $usual;
                $transaction->installments_key = $installments_key;
                $transaction->installment = $installment;
                $transaction->total_installments = $total_installments;
                $transaction->save();  
                array_push($storedTransactions, $transaction);
    
                //Increment values for next stallment
                $dateStr = $date->modify("+1 month")->format('Y-m-d');
                $installment++;
            }
    
            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function storeExpense(Request $request){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * date: string
         * value: integer
         * description: string
         * category_id: integer
         * account_id: integer
         * preview: string/boolean
         * usual: string/boolean
         * total_installments: integer
         */

        try {
            $date = $request->date;
            $value = $request->value * -1;
            $description = $request->description;
            $category_id = $request->category_id;
            $account_id = $request->account_id;
            $preview = ($request->preview === true || $request->preview === "true") ? 1 : 0; //False (0) as default
            $usual =  ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
            $total_installments = (int)$request->total_installments > 0 ? (int)$request->total_installments : 1; //1 (one) as default
    
            if (!$this->validateDate($date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro diferente de zero"], 400);
            }
    
            if (!strlen($description)){
                return response()->json(["message" => "É necessário informar uma descrição para a transação"], 400);
            }
    
            if (!$category_id){
                return response()->json(["message" => "É necessário informar uma categoria para a transação"], 400);
            }  
            
            if (!$account_id){
                return response()->json(["message" => "É necessário informar uma conta para a transação"], 400);
            } 
    
            // Get user id from auth user
            $user_id = JWTAuth::parseToken()->toUser()->id;
    
            // Generate installment_key if there are more than one stallment
            $installments_key = ($total_installments > 1) ? random_int(1, 9) . time() . random_int(1, 9) : 0;
    
            //Values who changes according to the stallment
            $installment = 1;
            $date = new DateTime($this->convertDate($request->date));
            $dateStr = $date->format('Y-m-d');
            $storedTransactions = [];
            
            while($installment <= $total_installments){
                $transaction = new Transaction;
                $transaction->date = $dateStr;
                $transaction->type = 'D';
                $transaction->value = (int)$value;
                $transaction->description = $description;
                $transaction->category_id = (int)$category_id;
                $transaction->account_id = (int)$account_id;
                $transaction->user_id = (int)$user_id;
                $transaction->preview = $preview;
                $transaction->usual = $usual;
                $transaction->installments_key = $installments_key;
                $transaction->installment = $installment;
                $transaction->total_installments = $total_installments;
                $transaction->save();  
                array_push($storedTransactions, $transaction);
    
                //Increment values for next stallment
                $dateStr = $date->modify("+1 month")->format('Y-m-d');
                $installment++;
            }
    
            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function storeTransfer(Request $request){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * date: string
         * value: integer
         * description: string
         * account_id: integer
         * destination_account_id: integer
         * usual: string/boolean
         */

        try {
            $date = $request->date;
            $value = $request->value;
            $description = $request->description;
            $account_id = $request->account_id;
            $destination_account_id = $request->destination_account_id;
            $usual =  ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
    
            if (!$this->validateDate($date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro diferente de zero"], 400);
            }
    
            if (!strlen($description)){
                return response()->json(["message" => "É necessário informar uma descrição para a transação"], 400);
            }
            
            if (!$account_id){
                return response()->json(["message" => "É necessário informar uma conta para a transação"], 400);
            } 

            if (!$destination_account_id){
                return response()->json(["message" => "É necessário informar uma conta de destino para a transação"], 400);
            } 
    
            // Get user id from auth user
            $user_id = JWTAuth::parseToken()->toUser()->id;
    
            // Generate installment_key if there are more than one stallment
            $transfer_key = random_int(1, 9) . time() . random_int(1, 9);
    
            $date = new DateTime($this->convertDate($request->date));
            $dateStr = $date->format('Y-m-d');
            $storedTransactions = [];
            
            $origin = new Transaction;
            $origin->date = $dateStr;
            $origin->type = 'T';
            $origin->value = (int)$value * -1;
            $origin->description = $description;
            $origin->account_id = (int)$account_id;
            $origin->user_id = (int)$user_id;
            $origin->preview = 0;
            $origin->usual = $usual;
            $origin->transfer_key = $transfer_key;
            $origin->save();  
            array_push($storedTransactions, $origin);

            $destination = new Transaction;
            $destination->date = $dateStr;
            $destination->type = 'T';
            $destination->value = (int)$value;
            $destination->description = $description;
            $destination->account_id = (int)$destination_account_id;
            $destination->user_id = (int)$user_id;
            $destination->preview = 0;
            $destination->usual = $usual;
            $destination->transfer_key = $transfer_key;
            $destination->save();  
            array_push($storedTransactions, $destination);
    
            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function storeInitialBalance(Request $request){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * value: integer
         * account_id: integer
         */

        try {
            //Receipt values
            $value = $request->value;
            $account_id = $request->account_id;

            //Default values
            $date = date('Y-m-d');
            $description = "Saldo inicial";
            $preview = 0;
            $usual = 0;
            $installment = 1;
            $total_installments = 1;
            $installments_key = 0;
    
            if ($value !== 0 && $value !== "0"){
                if (!$this->validateValue($value)){
                    return response()->json(["message" => "O valor da transação deve ser informado como número inteiro"], 400);    
                }
            }
            
            if (!$account_id){
                return response()->json(["message" => "É necessário informar uma conta para o saldo inicial"], 400);
            } 
    
            // Get user id from auth user
            $user_id = JWTAuth::parseToken()->toUser()->id;
        
            $storedTransactions = [];
            $transaction = new Transaction;
            $transaction->date = $date;
            $transaction->type = 'I';
            $transaction->value = (int)$value;
            $transaction->description = $description;
            $transaction->account_id = (int)$account_id;
            $transaction->user_id = (int)$user_id;
            $transaction->preview = $preview;
            $transaction->usual = $usual;
            $transaction->installments_key = $installments_key;
            $transaction->installment = $installment;
            $transaction->total_installments = $total_installments;
            $transaction->save();  
            array_push($storedTransactions, $transaction);
        
            return response()->json(["message" => "Saldo inicial registrado com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }
}
