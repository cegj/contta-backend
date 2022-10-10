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

    private function validateValue($value, $acceptNegative = false){

        $int = (int)$value;
        $float = (float)$value;

        if ($int == $float){
            if ($int < 0) {
                if ($acceptNegative) {
                    return true;
                } else {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }
    
    public function storeIncome(Request $request){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * transaction_date: string
         * payment_date: string
         * value: integer
         * description: string
         * category_id: integer
         * account_id: integer
         * preview: string/boolean
         * usual: string/boolean
         * total_installments: integer
         */

        try {
            $transaction_date = $request->transaction_date;
            $payment_date = $request->payment_date;
            $value = $request->value;
            $description = $request->description;
            $category_id = $request->category_id;
            $account_id = $request->account_id;
            $preview = ($request->preview === true || $request->preview === "true") ? 1 : 0; //False (0) as default
            $usual =  ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
            $total_installments = (int)$request->total_installments > 0 ? (int)$request->total_installments : 1; //1 (one) as default
    
            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateDate($payment_date)){
                return response()->json(["message" => "A data de pagamento informada é inválida"], 400);
            }

            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
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
            $transaction_date = new DateTime($this->convertDate($request->transaction_date));
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($this->convertDate($request->payment_date));
            $paymentDateStr = $payment_date->format('Y-m-d');
            $storedTransactions = [];
            
            while($installment <= $total_installments){
                $transaction = new Transaction;
                $transaction->transaction_date = $transactionDateStr;
                $transaction->payment_date = $paymentDateStr;
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
                $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
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
         * transaction_date: string
         * payment_date: string
         * value: integer
         * description: string
         * category_id: integer
         * account_id: integer
         * preview: string/boolean
         * usual: string/boolean
         * total_installments: integer
         */

        try {
            $transaction_date = $request->transaction_date;
            $payment_date = $request->payment_date;
            $value = $request->value * -1;
            $description = $request->description;
            $category_id = $request->category_id;
            $account_id = $request->account_id;
            $preview = ($request->preview === true || $request->preview === "true") ? 1 : 0; //False (0) as default
            $usual =  ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
            $total_installments = (int)$request->total_installments > 0 ? (int)$request->total_installments : 1; //1 (one) as default
    
            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }

            if (!$this->validateDate($payment_date)){
                return response()->json(["message" => "A data de pagamento informada é inválida"], 400);
            }
    
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
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
            $transaction_date = new DateTime($this->convertDate($request->transaction_date));
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($this->convertDate($request->payment_date));
            $paymentDateStr = $payment_date->format('Y-m-d');
            $storedTransactions = [];
            
            while($installment <= $total_installments){
                $transaction = new Transaction;
                $transaction->transaction_date = $transactionDateStr;
                $transaction->payment_date = $paymentDateStr;
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
                $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
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
         * transaction_date: string
         * value: integer
         * description: string
         * account_id: integer
         * destination_account_id: integer
         * usual: string/boolean
         */

        try {
            $transaction_date = $request->transaction_date;
            $value = $request->value;
            $description = $request->description;
            $account_id = $request->account_id;
            $destination_account_id = $request->destination_account_id;
            $usual =  ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
    
            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
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
    
            $transaction_date = new DateTime($this->convertDate($request->transaction_date));
            $dateStr = $transaction_date->format('Y-m-d');
            $storedTransactions = [];
            
            $origin = new Transaction;
            $origin->transaction_date = $dateStr;
            $origin->payment_date = $dateStr;
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
            $destination->transaction_date = $dateStr;
            $destination->payment_date = $dateStr;
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

            $alreadExists = Transaction::where('type', 'I')->where('account_id', $account_id)->get();

            if (sizeOf($alreadExists) !== 0){
                $currentValue = number_format($alreadExists[0]->value/100, 2, ",", ".");
                return response()->json(["message" => "A conta já possui um saldo inicial no valor de R$ {$currentValue}"], 422);    
            }

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
            $transaction->transaction_date = $date;
            $transaction->payment_date = $date;
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

    public function editIncome(Request $request, $id){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * transaction_date: string
         * payment_date: string
         * value: integer
         * description: string
         * category_id: integer
         * account_id: integer
         * preview: string/boolean
         * usual: string/boolean
         * total_installments: integer
         * edit_on_cascade: boolean
         */

        try {

            $edit_on_cascade = $request->edit_on_cascade;

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($edit_on_cascade == true && (int)$ref_transaction->installments_key !== 0){
                $transactions = Transaction::where('installments_key', $ref_transaction->installments_key)
                                ->where('installment', '>=', $ref_transaction->installment)
                                ->get();
            }

            $transaction_date = $request->transaction_date ?? $ref_transaction->transaction_date;
            $payment_date = $request->payment_date ?? $ref_transaction->payment_date;
            $value = $request->value ?? $ref_transaction->value;
            $description = $request->description ?? $ref_transaction->description;
            $category_id = $request->category_id ?? $ref_transaction->category_id;
            $account_id = $request->account_id ?? $ref_transaction->account_id;
            if ($request->preview) {
                $preview = ($request->preview === true || $request->preview === "true") ? 1 : 0; //False (0) as default
            } else {
                $preview = $ref_transaction->preview;
            }
            if ($request->usual) {
                $usual = ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
            } else {
                $usual = $ref_transaction->usual;
            }
            $installment = $ref_transaction->installment;
            $total_installments = $ref_transaction->total_installments;
    
            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateDate($payment_date)){
                return response()->json(["message" => "A data de pagamento informada é inválida"], 400);
            }

            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
            }
                
            $editedTransactions = [];
            $transaction_date = new DateTime($this->convertDate($request->transaction_date));
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($this->convertDate($request->payment_date));
            $paymentDateStr = $payment_date->format('Y-m-d');
            
            if ($edit_on_cascade == true){
                $i = 0;
                while($installment <= $total_installments){
                    $transactions[$i]->transaction_date = $transactionDateStr;
                    $transactions[$i]->payment_date = $paymentDateStr;
                    $transactions[$i]->value = (int)$value;
                    $transactions[$i]->description = $description;
                    $transactions[$i]->category_id = (int)$category_id;
                    $transactions[$i]->account_id = (int)$account_id;
                    $transactions[$i]->preview = $preview;
                    $transactions[$i]->usual = $usual;
                    $transactions[$i]->save();  
                    array_push($editedTransactions, $transactions[$i]);
        
                    //Increment values for next stallment
                    $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                    $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
                    $installment++;
                    $i++;
                }
            } else {
                $ref_transaction->transaction_date = $transactionDateStr;
                $ref_transaction->payment_date = $paymentDateStr;
                $ref_transaction->value = (int)$value;
                $ref_transaction->description = $description;
                $ref_transaction->category_id = (int)$category_id;
                $ref_transaction->account_id = (int)$account_id;
                $ref_transaction->preview = $preview;
                $ref_transaction->usual = $usual;
                $ref_transaction->save();  
                array_push($editedTransactions, $ref_transaction);
            }

            return response()->json(["message" => "Transação alterada com sucesso", "transactions" => $editedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function editExpense(Request $request, $id){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * transaction_date: string
         * payment_date: string
         * value: integer
         * description: string
         * category_id: integer
         * account_id: integer
         * preview: string/boolean
         * usual: string/boolean
         * total_installments: integer
         * edit_on_cascade: boolean
         */

        try {

            $edit_on_cascade = $request->edit_on_cascade;

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($edit_on_cascade == true && (int)$ref_transaction->installments_key !== 0){
                $transactions = Transaction::where('installments_key', $ref_transaction->installments_key)
                                ->where('installment', '>=', $ref_transaction->installment)
                                ->get();
            }

            $transaction_date = $request->transaction_date ?? $ref_transaction->transaction_date;
            $payment_date = $request->payment_date ?? $ref_transaction->payment_date;
            $value = $request->value ?? $ref_transaction->value;
            $description = $request->description ?? $ref_transaction->description;
            $category_id = $request->category_id ?? $ref_transaction->category_id;
            $account_id = $request->account_id ?? $ref_transaction->account_id;
            if ($request->preview) {
                $preview = ($request->preview === true || $request->preview === "true") ? 1 : 0; //False (0) as default
            } else {
                $preview = $ref_transaction->preview;
            }
            if ($request->usual) {
                $usual = ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
            } else {
                $usual = $ref_transaction->usual;
            }
            $installment = $ref_transaction->installment;
            $total_installments = $ref_transaction->total_installments;
    
            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateDate($payment_date)){
                return response()->json(["message" => "A data de pagamento informada é inválida"], 400);
            }

            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
            }
                
            $editedTransactions = [];
            $transaction_date = new DateTime($this->convertDate($request->transaction_date));
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($this->convertDate($request->payment_date));
            $paymentDateStr = $payment_date->format('Y-m-d');
            
            if ($edit_on_cascade == true){
                $i = 0;
                while($installment <= $total_installments){
                    $transactions[$i]->transaction_date = $transactionDateStr;
                    $transactions[$i]->payment_date = $paymentDateStr;
                    $transactions[$i]->value = -(int)$value;
                    $transactions[$i]->description = $description;
                    $transactions[$i]->category_id = (int)$category_id;
                    $transactions[$i]->account_id = (int)$account_id;
                    $transactions[$i]->preview = $preview;
                    $transactions[$i]->usual = $usual;
                    $transactions[$i]->save();  
                    array_push($editedTransactions, $transactions[$i]);
        
                    //Increment values for next stallment
                    $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                    $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
                    $installment++;
                    $i++;
                }
            } else {
                $ref_transaction->transaction_date = $transactionDateStr;
                $ref_transaction->payment_date = $paymentDateStr;
                $ref_transaction->value = -(int)$value;
                $ref_transaction->description = $description;
                $ref_transaction->category_id = (int)$category_id;
                $ref_transaction->account_id = (int)$account_id;
                $ref_transaction->preview = $preview;
                $ref_transaction->usual = $usual;
                $ref_transaction->save();  
                array_push($editedTransactions, $ref_transaction);
            }

            return response()->json(["message" => "Transação alterada com sucesso", "transactions" => $editedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function editTransfer(Request $request, $id){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * transaction_date: string
         * value: integer
         * description: string
         * account_id: integer
         * destination_account_id: integer
         * usual: string/boolean
         */

        try {

            $ref_transaction = Transaction::find($id);
            $transactions = Transaction::where('transfer_key', $ref_transaction->transfer_key)->get();

            if ($transactions[0]->value < 0 && $transactions[1]->value > 0) {
                $origin = $transactions[0];
                $destination = $transactions[1];
            } else if ($transactions[1]->value < 0 && $transactions[0]->value > 0){
                $origin = $transactions[1];
                $destination = $transactions[0];
            }

            $transaction_date = $request->transaction_date ?? $ref_transaction->transaction_date;
            $payment_date = $request->transaction_date ?? $ref_transaction->transaction_date;
            $value = $request->value ?? $ref_transaction->value;
            $description = $request->description ?? $ref_transaction->description;
            $account_id = $request->account_id ?? $ref_transaction->account_id;
            $destination_account_id = $request->destination_account_id ?? $ref_transaction->destination_account_id;
            if ($request->usual) {
                $usual = ($request->usual === true || $request->usual === "true") ? 1 : 0; //False (0) as default
            } else {
                $usual = $ref_transaction->usual;
            }
    
            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
            }
                
            $transaction_date = new DateTime($this->convertDate($request->transaction_date));
            $dateStr = $transaction_date->format('Y-m-d');
            $editedTransactions = [];

            $origin->transaction_date = $dateStr;
            $origin->payment_date = $dateStr;
            $origin->value = (int)$value * -1;
            $origin->description = $description;
            $origin->account_id = (int)$account_id;
            $origin->usual = $usual;
            $origin->save();  
            array_push($editedTransactions, $origin);

            $destination->transaction_date = $dateStr;
            $destination->payment_date = $dateStr;
            $destination->value = (int)$value;
            $destination->description = $description;
            $destination->account_id = (int)$destination_account_id;
            $destination->usual = $usual;
            $destination->save();  
            array_push($editedTransactions, $destination);
    
            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $editedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function editInitialBalance(Request $request, $accountId){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * value: integer
         */

        try {

            $transaction = Transaction::where('type', 'I')->where('account_id', $accountId)->get();

            if (sizeOf($transaction) == 0){
                return response()->json(["message" => "O saldo inicial não foi localizado"], 400);    
            } elseif (sizeOf($transaction) == 1) {
                $transaction = $transaction[0];
            } else {
                return response()->json(["message" => "Mais de um saldo inicial foi localizado para a conta"], 400);    
            }

            //Receipt values
            $value = $request->value;
    
            if ($value !== 0 && $value !== "0"){
                if (!$this->validateValue($value, true)){
                    return response()->json(["message" => "O valor da transação deve ser informado como número inteiro"], 400);    
                }
            }
            
            $storedTransactions = [];
            $transaction->value = (int)$value;
            $transaction->save();  
            array_push($storedTransactions, $transaction);
        
            return response()->json(["message" => "Saldo inicial alterado com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }
}
