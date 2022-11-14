<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Transaction;
use DateTime;

class TransactionController extends Controller
{
    private function validateDate($dateStr){
        $date = explode( '-', $dateStr );
        return checkdate($date[1], $date[2], $date[0]);
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

    public function getTransactions(Request $request){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * QUERY PARAMS:
         * from: yyyy-mm-dd
         * to: yyyy-mm-dd
         * type: string ('R', 'D', 'T', 'I')
         * category: integer
         * account: integer
         * installments_key: string/number
         * typeofdate: string ('transaction' (default), 'payment')
         */

        try {

            // Get and set "from" date
            $fromQuery = $request->query('from');
            if (!$fromQuery){return response()->json(["message" => "É necessário informar uma data inicial ('from')"], 400);}
            $from = explode('-', $fromQuery);
            $sizeOfFrom = sizeOf($from);

            if($sizeOfFrom == 1){
                $from = $from[0] . '-' . '01' . '-' . '01';
            } else if ($sizeOfFrom == 2) {
                $from = $from[0] . '-' . $from[1] . '-' . '01';
            } else if ($sizeOfFrom == 3) {
                $from = $from[0] . '-' . $from[1] . '-' . $from[2];
            }

            //Check if "from" date is valid
            $dateToCheck = explode('-', $from);
            $dateIsValid = checkdate($dateToCheck[1], $dateToCheck[2], $dateToCheck[0]);
            if (!$dateIsValid){
                return response()->json(["message" => "A data inicial ({$from}) é inválida"], 400);
            }

            // Get and set "to" date
            $toQuery = $request->query('to');
            if (!$toQuery){return response()->json(["message" => "É necessário informar uma data final ('to')"], 400);}
            $to = explode('-', $toQuery);
            $sizeOfFrom = sizeOf($to);

            if($sizeOfFrom == 1){
                $to = $to[0] . '-' . '12' . '-' . '31';
            } else if ($sizeOfFrom == 2) {
                $date = new DateTime($toQuery);
                $date->modify('last day of this month');
                $to = $to[0] . '-' . $to[1] . '-' . $date->format('d');
            } else if ($sizeOfFrom == 3) {
                $to = $to[0] . '-' . $to[1] . '-' . $to[2];
            }

            //Check if "to" date is valid
            $dateToCheck = explode('-', $to);
            $dateIsValid = checkdate($dateToCheck[1], $dateToCheck[2], $dateToCheck[0]);
            if (!$dateIsValid){
                return response()->json(["message" => "A data final ({$to}) é inválida"], 400);
            }

            $typeOfDate = $request->query('typeofdate');
            if ($typeOfDate && $typeOfDate == 'payment'){
                $typeOfDate = 'payment_date';
            } else {
                $typeOfDate = 'transaction_date';
            }

            //Building the query to db
            $transactions = Transaction::whereDate($typeOfDate, ">=", $from)
            ->whereDate($typeOfDate, "<=", $to)
            ->get();

            //Filter by category
            $categoryId = $request->query('category');
            if ($categoryId) {
                $transactions = $transactions->filter(function ($transaction) use ($categoryId) {
                    return $transaction->category_id == $categoryId;
                })->values();            
            }

            //Filter by account
            $accountId = $request->query('account');
            if ($accountId) {
                $transactions = $transactions->filter(function ($transaction) use ($accountId) {
                    return $transaction->account_id == $accountId;
                })->values();            
            }

            //Filter by type
            $type = $request->query('type');
            if ($type) {
                $transactions = $transactions->filter(function ($transaction) use ($type) {
                    return $transaction->type == $type;
                })->values();            
            }

            //Filter by installments_key
            $installments_key = $request->query('installments_key');
            if ($installments_key) {
                $transactions = $transactions->filter(function ($transaction) use ($installments_key) {
                    return $transaction->installments_key == $installments_key;
                })->values();            
            }

            return response()->json(["message" => "Transações obtidas de {$from} até {$to}", 'transactions' => $transactions], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function getTransactionById(Request $request, $id){

        try {

            $transaction = Transaction::find($id);

            if (!$transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($transaction->type == 'R' || $transaction->type == 'D'){
                $allInstallments = Transaction::where('installments_key', $transaction->installments_key)->get();
                return response()->json(["message" => "Transação resgatada com sucesso", 'transaction' => $transaction, 'allRelated' => $allInstallments], 200);
            }

            if ($transaction->type == 'T'){
                $transferTransactions = Transaction::where('transfer_key', $transaction->transfer_key)->get();

                if ($transferTransactions[0]->value < 0 && $transferTransactions[1]->value > 0) {
                    $origin = $transferTransactions[0];
                    $destination = $transferTransactions[1];
                } else if ($transferTransactions[1]->value < 0 && $transferTransactions[0]->value > 0){
                    $origin = $transferTransactions[1];
                    $destination = $transferTransactions[0];
                }

                return response()->json(["message" => "Transação resgatada com sucesso", 'transaction' => $transaction, 'allRelated' => [$origin, $destination]], 200);
            }
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
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
            $installments_key = ($total_installments > 1) ? random_int(1, 9) . time() . random_int(1, 9) : null;
    
            //Values who changes according to the stallment
            $installment = 1;
            $transaction_date = new DateTime($request->transaction_date);
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($request->payment_date);
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
            $installments_key = ($total_installments > 1) ? random_int(1, 9) . time() . random_int(1, 9) : null;
    
            //Values who changes according to the stallment
            $installment = 1;
            $transaction_date = new DateTime($request->transaction_date);
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($request->payment_date);
            $paymentDateStr = $payment_date->format('Y-m-d');
            $storedTransactions = [];
            
            while($installment <= $total_installments){
                $transaction = new Transaction;
                $transaction->transaction_date = $transactionDateStr;
                $transaction->payment_date = $paymentDateStr;
                $transaction->type = 'D';
                $transaction->value = (int)$value * -1;
                $transaction->description = $description;
                $transaction->category_id = (int)$category_id;
                $transaction->account_id = (int)$account_id;
                $transaction->user_id = (int)$user_id;
                $transaction->preview = $preview;
                $transaction->usual = $usual;
                $transaction->installments_key = $installments_key;
                $transaction->installment = $installment;
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
    
            $transaction_date = new DateTime($request->transaction_date);
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
         */

        try {

            $cascade = $request->query('cascade') == 'true' ? true : false;

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($ref_transaction->type !== "R"){
                return response()->json(['message' => 'Transação informada não é do tipo receita (R)'], 400);
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
            $transaction_date = new DateTime($request->transaction_date);
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($request->payment_date);
            $paymentDateStr = $payment_date->format('Y-m-d');
            
            if ($cascade == true){
                if ($ref_transaction->installments_key){
                    $transactions = Transaction::where('installments_key', $ref_transaction->installments_key)
                                    ->get();
                    $total_installments = count($transactions);
                } else {
                    return response()->json(["message" => "Impossível editar em cascata: transação não tem outras parcelas associadas."], 400);        
                }

                $i = $ref_transaction->installment - 1;
                while($i < $total_installments){
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
        
                    //Increment values for next installment
                    $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                    $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
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
         */

        try {

            $cascade = $request->query('cascade') == 'true' ? true : false;

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($ref_transaction->type !== "D"){
                return response()->json(['message' => 'Transação informada não é do tipo despesa (D)'], 400);
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
            $transaction_date = new DateTime($request->transaction_date);
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($request->payment_date);
            $paymentDateStr = $payment_date->format('Y-m-d');
            
            if ($cascade == true){
                if ($ref_transaction->installments_key){
                    $transactions = Transaction::where('installments_key', $ref_transaction->installments_key)
                                    ->get();
                    $total_installments = count($transactions);
                } else {
                    return response()->json(["message" => "Impossível editar em cascata: transação não tem outras parcelas associadas."], 400);        
                }

                $i = $ref_transaction->installment - 1;
                while($i < $total_installments){
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

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($ref_transaction->type !== "T"){
                return response()->json(['message' => 'Transação informada não é do tipo transferência (T)'], 400);
            }

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
                
            $transaction_date = new DateTime($request->transaction_date);
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

    public function deleteIncomeExpense(Request $request, $id){

        try {

            $cascade = $request->query('cascade') == 'true' ? true : false;

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            $allInstallments = Transaction::where('installments_key', $ref_transaction->installments_key)->get();

            //Delete transaction(s)
            $deleted = [];
            $installment = $ref_transaction->installment;
            $totalInstallments = sizeOf($allInstallments);
            $n = 1;   
            foreach($allInstallments as $transaction){
                if ($cascade == true){
                    if ($transaction->installment >= $ref_transaction->installment){
                        $transaction->delete();
                        array_push($deleted, $transaction);
                    } else {
                        $transaction->installment = $n;
                        $transaction->save();
                        $n++;
                    }    
                } else {
                    if ($transaction->installment == $ref_transaction->installment){
                        $transaction->delete();
                        array_push($deleted, $transaction);
                    } else {
                        $transaction->installment = $n;
                        $transaction->save();
                        $n++;
                    }
                }
            }

            return response()->json(["message" => "Transações apagadas com sucesso", 'deleted' => $deleted], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function deleteTransfer(Request $request, $id){

        try {

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            $transactions = Transaction::where('transfer_key', $ref_transaction->transfer_key)->get();

            if ($transactions[0]->value < 0 && $transactions[1]->value > 0) {
                $origin = $transactions[0];
                $destination = $transactions[1];
            } else if ($transactions[1]->value < 0 && $transactions[0]->value > 0){
                $origin = $transactions[1];
                $destination = $transactions[0];
            }

            //Delete transaction(s)
            $deleted = [];
            $origin->delete();
            array_push($deleted, $origin);
            $destination->delete();
            array_push($deleted, $destination);

            return response()->json(["message" => "Transações apagadas com sucesso", 'deleted' => $deleted], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }

    public function deleteInitialBalance(Request $request, $accountId){

        try {

            $transaction = Transaction::where('type', 'I')->where('account_id', $accountId)->get();

            if (sizeOf($transaction) == 0){
                return response()->json(["message" => "O saldo inicial não foi localizado"], 400);    
            } elseif (sizeOf($transaction) == 1) {
                $transaction = $transaction[0];
            } else {
                return response()->json(["message" => "Mais de um saldo inicial foi localizado para a conta"], 400);    
            }

            //Delete transaction
            $deleted = [];
            $transaction->delete();
            array_push($deleted, $transaction);

            return response()->json(["message" => "Transações apagadas com sucesso", 'deleted' => $deleted], 200);
                                
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
    }
}
