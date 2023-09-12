<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
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

    private function checkThereIsBudgetControl($paymentDate, $categoryId, $type){
        $baseDate = new Datetime($paymentDate);
        $firstDayOfMonth = $baseDate->modify('first day of this month')->format('Y-m-d');
        $lastDayOfMonth = $baseDate->modify('last day of this month')->format('Y-m-d');    
        $budgetControlTransaction = Transaction::whereBetween('payment_date', [$firstDayOfMonth, $lastDayOfMonth])
        ->where('budget_control', 1)
        ->where('category_id', $categoryId)
        ->where('type', $type)
        ->get(); 
        if (count($budgetControlTransaction) > 0){
            $monthYearStr = explode('-', $firstDayOfMonth)[1] . '/' . explode('-', $firstDayOfMonth)[0]; 
            throw new \Exception("Já existe uma transação de controle de orçamento deste tipo em {$monthYearStr}", 400); 
        } else {
            return true;
        }
    }

    private function handleBudgetControlOnStore($bodyPaymentDate, $bodyCategoryId, $bodyValue, $type){
        $baseDate = new Datetime($bodyPaymentDate);
        $firstDayOfMonth = $baseDate->modify('first day of this month')->format('Y-m-d');
        $lastDayOfMonth = $baseDate->modify('last day of this month')->format('Y-m-d');    
        $bcTransaction = Transaction::whereBetween('payment_date', [$firstDayOfMonth, $lastDayOfMonth])
        ->where('budget_control', 1)
        ->where('category_id', $bodyCategoryId)
        ->where('type', $type)
        ->get(); 

        if (count($bcTransaction) > 0){
            $bcTransaction = $bcTransaction[0];
            $result = $bcTransaction->value - $bodyValue;
            $resultExceeds = $bcTransaction->type == "R" ? $result < 0 : $result > 0; 
            if ($resultExceeds) {
                $exceedValue = number_format(floatval($result)/100, 2, ",", ".");
                throw new \Exception("O valor desta transação ultrapassa o limite do controle do orçamento de {$bcTransaction->transaction_date} em R$ {$exceedValue}", 400); 
            } else {
                $bcTransaction->value = $result;
                $bcTransaction->save();    
            }
        }
        return;
    }

    private function handleBudgetControlOnEdit($transaction, $bodyPaymentDate, $bodyCategoryId, $bodyValue){

        //Restore value on old/actual db budget transaction
        $baseDate = new Datetime($transaction->payment_date);
        $firstDayOfMonth = $baseDate->modify('first day of this month')->format('Y-m-d');
        $lastDayOfMonth = $baseDate->modify('last day of this month')->format('Y-m-d');    
        $oldBcTransaction = Transaction::whereBetween('payment_date', [$firstDayOfMonth, $lastDayOfMonth])
        ->where('budget_control', 1)            
        ->when($transaction->category_id, function ($q, $category_id) {
            return $q->where('category_id', $category_id);
        }, function ($q) {
            return $q->whereNull('category_id');
        })
        ->where('type', $transaction->type)
        ->get(); 
        if (count($oldBcTransaction) > 0){
            $oldBcTransaction = $oldBcTransaction[0];
            $result = $oldBcTransaction->value + $transaction->value;
            $oldBcTransaction->value = $result;
            $oldBcTransaction->save();    
        }

        //Make changes on new BC (wich can be the same or not) based on body values
        $baseDate = new Datetime($bodyPaymentDate);
        $firstDayOfMonth = $baseDate->modify('first day of this month')->format('Y-m-d');
        $lastDayOfMonth = $baseDate->modify('last day of this month')->format('Y-m-d');    
        $newBcTransaction = Transaction::whereBetween('payment_date', [$firstDayOfMonth, $lastDayOfMonth])
        ->where('budget_control', 1)
        ->where('category_id', $bodyCategoryId)
        ->where('type', $transaction->type)
        ->get(); 

        if (count($newBcTransaction) > 0){
            $newBcTransaction = $newBcTransaction[0];
            $result = $newBcTransaction->value - $bodyValue;
            $resultExceeds = $newBcTransaction->type == "R" ? $result < 0 : $result > 0; 
            if ($resultExceeds) {
                $exceedValue = number_format(floatval($result)/100, 2, ",", ".");
                throw new \Exception("O valor desta transação ultrapassa o limite do controle do orçamento de {$newBcTransaction->transaction_date} em R$ {$exceedValue}", 400); 
            } else {
                $newBcTransaction->value = $result;
                $newBcTransaction->save();    
            }
        }
        return;
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
         * typeofdate: string ('transaction_date', 'payment_date')
         * includehiddenaccounts: true | false (default)
         * hideexpected: true | false (default)
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
            if ($typeOfDate){
                if (!$typeOfDate == 'transaction_date'){
                    if (!$typeOfDate == 'payment_date'){
                        return response()->json(["message" => "O tipo de data ('typeofdate') é inválido, informe 'transaction_date' ou 'payment_date'"], 400);  
                    }}
            } else {
                return response()->json(["message" => "O tipo de data ('typeofdate') não foi informado"], 400);
            }

            $account = $request->query('account');
            if ($account === "0"){$account = "null";};
            $category = $request->query('category');
            if ($category === "0"){$category = "null";};
            $type = $request->query('type');
            $installments_key = $request->query('installments_key');
            $includeHiddenAccounts = filter_var($request->includehiddenaccounts, FILTER_VALIDATE_BOOLEAN);
            $hideExpected = filter_var($request->hideexpected, FILTER_VALIDATE_BOOLEAN);
            $preview = filter_var($request->preview, FILTER_VALIDATE_BOOLEAN);
            $usual =  filter_var($request->usual, FILTER_VALIDATE_BOOLEAN);
            $budget_control = filter_var($request->budget_control, FILTER_VALIDATE_BOOLEAN);

            //Building the query to db
            $transactions = Transaction::whereBetween($typeOfDate, [$from, $to])
            ->with('account')
            ->with('category')
            ->when((!$includeHiddenAccounts), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', $account);})    
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', $category);})    
            ->when($type, function($q, $type){
                return $q->where('type', $type);})     
            ->when($installments_key, function($q, $installments_key){
                return $q->where('installments_key', $installments_key);})
            ->when($hideExpected, function($q){
                return $q->where('preview', 0);})                        
            ->orderBy($typeOfDate, 'asc')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('updated_at', 'asc')
            ->get();

            return response()->json(["message" => "Transações obtidas de {$from} até {$to}", 'transactions' => $transactions], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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

                $transaction->category;
                $transaction->group;
                foreach ($allInstallments as $installment){
                    $installment->category;
                    $installment->account;
                }

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

                $transaction->category;
                $transaction->group;
                $origin->category;
                $origin->category;
                $destination->category;
                $destination->category;

                return response()->json(["message" => "Transação resgatada com sucesso", 'transaction' => $transaction, 'allRelated' => [$origin, $destination]], 200);
            }
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function searchTransactions(Request $request){
        date_default_timezone_set('America/Sao_Paulo');

        /**
         * QUERY PARAMS:
         * q: string
         */

        try {

            // Get and set "from" date

            $searchQuery = $request->query('q');

            $searchQueryArr = explode(';', $searchQuery);

            $columns = ['value', 'description'];

            $query = Transaction::query()->with('account')->with('category');
            foreach($columns as $column){
                forEach($searchQueryArr as $term){
                    $query->orWhere($column, 'LIKE', '%' . trim($term) . '%');
                }
            }
            
            $result = $query->get();

            return response()->json(["message" => "Busca realizada com sucesso", 'transactions' => $result], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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
         * budget_control: string/boolean
         * total_installments: integer
         */

        try {
            $transaction_date = $request->transaction_date;
            $payment_date = $request->payment_date;
            $value = $request->value;
            $description = $request->description;
            $category_id = $request->category_id;
            $account_id = $request->account_id;
            $preview = filter_var($request->preview, FILTER_VALIDATE_BOOLEAN);
            $usual =  filter_var($request->usual, FILTER_VALIDATE_BOOLEAN);
            $budget_control = filter_var($request->budget_control, FILTER_VALIDATE_BOOLEAN);
            $total_installments = (int)$request->total_installments > 0 ? (int)$request->total_installments : 1; //1 (one) as default

            if ($budget_control){
                $preview = 1;
                $account_id = null;
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
    
            if (!strlen($description)){
                return response()->json(["message" => "É necessário informar uma descrição para a transação"], 400);
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

            DB::transaction(function ()
            use(
                &$installment,
                &$total_installments,
                &$transaction_date,
                &$payment_date,
                &$transactionDateStr,
                &$paymentDateStr,
                &$value,
                &$description,
                &$category_id,
                &$account_id,
                &$user_id,
                &$preview,
                &$budget_control,
                &$installments_key,
                &$storedTransactions,
                &$usual
            ) {
                while($installment <= $total_installments){
    
                    if ($budget_control){
                        $this->checkThereIsBudgetControl($paymentDateStr, $category_id, 'R');
                    } 
    
                    if (!$budget_control){
                        $this->handleBudgetControlOnStore($paymentDateStr, $category_id, $value, 'R');
                    }

                    $transaction = new Transaction;
                    $transaction->transaction_date = $transactionDateStr;
                    $transaction->payment_date = $paymentDateStr;
                    $transaction->type = 'R';
                    $transaction->value = (int)$value;
                    $transaction->description = $description;
                    $transaction->category_id = $category_id;
                    $transaction->account_id = $account_id;
                    $transaction->user_id = $user_id;
                    $transaction->preview = $preview;
                    $transaction->usual = $usual;
                    $transaction->budget_control = $budget_control;
                    $transaction->installments_key = $installments_key;
                    $transaction->installment = $installment;
                    $transaction->save();  
                    array_push($storedTransactions, $transaction);
        
                    //Increment values for next stallment
                    $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                    $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
                    $installment++;
                }
            });
            
            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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
         * budget_control: string/boolean
         * total_installments: integer
         */

        try {
            $transaction_date = $request->transaction_date;
            $payment_date = $request->payment_date;
            $value = $request->value;
            $description = $request->description;
            $category_id = $request->category_id;
            $account_id = $request->account_id;
            $preview = filter_var($request->preview, FILTER_VALIDATE_BOOLEAN);
            $usual =  filter_var($request->usual, FILTER_VALIDATE_BOOLEAN);
            $budget_control = filter_var($request->budget_control, FILTER_VALIDATE_BOOLEAN);
            $total_installments = (int)$request->total_installments > 0 ? (int)$request->total_installments : 1; //1 (one) as default

            if ($budget_control){
                $preview = 1;
                $account_id = null;
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
    
            if (!strlen($description)){
                return response()->json(["message" => "É necessário informar uma descrição para a transação"], 400);
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
            
            DB::transaction(function ()
                use(
                    &$installment,
                    &$total_installments,
                    &$transaction_date,
                    &$payment_date,
                    &$transactionDateStr,
                    &$paymentDateStr,
                    &$value,
                    &$description,
                    &$category_id,
                    &$account_id,
                    &$user_id,
                    &$preview,
                    &$budget_control,
                    &$installments_key,
                    &$storedTransactions,
                    &$usual
                ){
                while($installment <= $total_installments){
                    $baseDate = clone $payment_date;
                    $firstDayOfMonth = $baseDate->modify('first day of this month')->format('Y-m-d');
                    $lastDayOfMonth = $baseDate->modify('last day of this month')->format('Y-m-d');
    
                    if ($budget_control){
                        $this->checkThereIsBudgetControl($paymentDateStr, $category_id, 'D');
                    } 
    
                    if (!$budget_control){
                        $this->handleBudgetControlOnStore($paymentDateStr, $category_id, (int)$value * -1, 'D');
                    }
    
                    $transaction = new Transaction;
                    $transaction->transaction_date = $transactionDateStr;
                    $transaction->payment_date = $paymentDateStr;
                    $transaction->type = 'D';
                    $transaction->value = (int)$value * -1;
                    $transaction->description = $description;
                    $transaction->category_id = $category_id;
                    $transaction->account_id = $account_id;
                    $transaction->user_id = $user_id;
                    $transaction->preview = $preview;
                    $transaction->usual = $usual;
                    $transaction->budget_control = $budget_control;
                    $transaction->installments_key = $installments_key;
                    $transaction->installment = $installment;
                    $transaction->save();  
                    array_push($storedTransactions, $transaction);
        
                    //Increment values for next stallment
                    $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                    $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
                    $installment++;
                }
            });

            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], $th->getCode() ? $th->getCode() : 500);
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
            $usual =  filter_var($request->usual, FILTER_VALIDATE_BOOLEAN);
    
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
            $origin->account_id = $account_id;
            $origin->user_id = $user_id;
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
            $destination->account_id = $destination_account_id;
            $destination->user_id = $user_id;
            $destination->preview = 0;
            $destination->usual = $usual;
            $destination->transfer_key = $transfer_key;
            $destination->save();  
            array_push($storedTransactions, $destination);
    
            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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
            $date = new Datetime;
            $date = $date->modify('first day of this month')->format('Y-m-d');
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
            $transaction->account_id = $account_id;
            $transaction->user_id = $user_id;
            $transaction->preview = $preview;
            $transaction->usual = $usual;
            $transaction->save();  
            array_push($storedTransactions, $transaction);
        
            return response()->json(["message" => "Saldo inicial registrado com sucesso", "transactions" => $storedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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

            $transaction_date = $request->filled('transaction_date') ? $request->transaction_date : $ref_transaction->transaction_date;
            $payment_date = $request->filled('payment_date') ? $request->payment_date : $ref_transaction->payment_date;
            $value = $request->filled('value') ? $request->value : $ref_transaction->value;
            $description = $request->filled('description') ? $request->description : $ref_transaction->description;
            $category_id = $request->filled('category_id') ? $request->category_id : $ref_transaction->category_id;
            $account_id = $request->filled('account_id') ? $request->account_id : $ref_transaction->account_id;
            if ($request->filled('preview')) {
                $preview = filter_var($request->preview, FILTER_VALIDATE_BOOLEAN);
                } else {
                $preview = $ref_transaction->preview;
            }
            if ($request->filled('usual')) {
                $usual =  filter_var($request->usual, FILTER_VALIDATE_BOOLEAN);
                } else {
                $usual = $ref_transaction->usual;
            }

            if ($request->filled('budget_control')) {
                $budget_control = filter_var($request->budget_control, FILTER_VALIDATE_BOOLEAN);
                } else {
                $budget_control = $ref_transaction->budget_control;
            }

            if ($budget_control){
                $preview = 1;
                $account_id = null;
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
            $transaction_date = new DateTime($transaction_date);
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($payment_date);
            $paymentDateStr = $payment_date->format('Y-m-d');
            
            DB::transaction(function () 
            use(
                &$cascade,
                &$ref_transaction,
                &$transaction_date,
                &$payment_date,
                &$transactionDateStr,
                &$paymentDateStr,
                &$value,
                &$description,
                &$category_id,
                &$account_id,
                &$usual,
                &$preview,
                &$budget_control,
                &$editedTransactions,
            ){
                if ($cascade == true){
                    if ($ref_transaction->installments_key){
                        $transactions = Transaction::where('installments_key', $ref_transaction->installments_key)->get();
                        $total_installments = count($transactions);
                    } else {
                        return response()->json(["message" => "Impossível editar em cascata: transação não tem outras parcelas associadas."], 400);        
                    }

                    $i = $ref_transaction->installment - 1;
                    while($i < $total_installments){

                        if (!$transactions[$i]->budget_control && $budget_control){
                            $this->checkThereIsBudgetControl($paymentDateStr, $category_id, 'R');
                        } 
        
                        if (!$transactions[$i]->budget_control && !$budget_control){
                            $this->handleBudgetControlOnEdit($transactions[$i], $paymentDateStr, $category_id, $value);
                        }

                        $transactions[$i]->transaction_date = $transactionDateStr;
                        $transactions[$i]->payment_date = $paymentDateStr;
                        $transactions[$i]->value = (int)$value;
                        $transactions[$i]->description = $description;
                        $transactions[$i]->category_id = $category_id;
                        $transactions[$i]->account_id = $account_id;
                        $transactions[$i]->preview = $preview;
                        $transactions[$i]->usual = $usual;
                        $transactions[$i]->budget_control = $budget_control;
                        $transactions[$i]->save();  
                        array_push($editedTransactions, $transactions[$i]);
            
                        //Increment values for next installment
                        $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                        $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
                        $i++;
                    }
                } else {
                    if (!$ref_transaction->budget_control && $budget_control){
                        $this->checkThereIsBudgetControl($paymentDateStr, $category_id, 'R');
                    } 

                    if (!$ref_transaction->budget_control && !$budget_control){
                        $this->handleBudgetControlOnEdit($ref_transaction, $paymentDateStr, $category_id, $value);
                    }

                    $ref_transaction->transaction_date = $transactionDateStr;
                    $ref_transaction->payment_date = $paymentDateStr;
                    $ref_transaction->value = (int)$value;
                    $ref_transaction->description = $description;
                    $ref_transaction->category_id = $category_id;
                    $ref_transaction->account_id = $account_id;
                    $ref_transaction->preview = $preview;
                    $ref_transaction->usual = $usual;
                    $ref_transaction->budget_control = $budget_control;
                    $ref_transaction->save();  
                    array_push($editedTransactions, $ref_transaction);
                }
            });


            return response()->json(["message" => "Transação alterada com sucesso", "transactions" => $editedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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

            $transaction_date = $request->filled('transaction_date') ? $request->transaction_date : $ref_transaction->transaction_date;
            $payment_date = $request->filled('payment_date') ? $request->payment_date : $ref_transaction->payment_date;
            $description = $request->filled('description') ? $request->description : $ref_transaction->description;
            $category_id = $request->filled('category_id') ? $request->category_id : $ref_transaction->category_id;
            $account_id = $request->filled('account_id') ? $request->account_id : $ref_transaction->account_id;

            if ($request->filled('preview')) {
                $preview = filter_var($request->preview, FILTER_VALIDATE_BOOLEAN);
                } else {
                $preview = $ref_transaction->preview;
            }
            if ($request->filled('usual')) {
                $usual =  filter_var($request->usual, FILTER_VALIDATE_BOOLEAN);
                } else {
                $usual = $ref_transaction->usual;
            }

            if ($request->filled('budget_control')) {
                $budget_control = filter_var($request->budget_control, FILTER_VALIDATE_BOOLEAN);
                } else {
                $budget_control = $ref_transaction->budget_control;
            }

            if ($budget_control){
                $preview = 1;
                $account_id = null;
            } 

            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateDate($payment_date)){
                return response()->json(["message" => "A data de pagamento informada é inválida"], 400);
            }

            if ($request->filled('value')){
            $value = $request->value;
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
            }
            $value = -$value;
            } else {
                $value = $ref_transaction->value;
            }
                
            $editedTransactions = [];
            $transaction_date = new DateTime($transaction_date);
            $transactionDateStr = $transaction_date->format('Y-m-d');
            $payment_date = new DateTime($payment_date);
            $paymentDateStr = $payment_date->format('Y-m-d');
            
            DB::transaction(function () 
            use(
                &$cascade,
                &$ref_transaction,
                &$transaction_date,
                &$payment_date,
                &$transactionDateStr,
                &$paymentDateStr,
                &$value,
                &$description,
                &$category_id,
                &$account_id,
                &$usual,
                &$preview,
                &$budget_control,
                &$editedTransactions,
            ){
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

                        if (!$transactions[$i]->budget_control && $budget_control){
                            $this->checkThereIsBudgetControl($paymentDateStr, $category_id, 'D');
                        } 

                        if (!$transactions[$i]->budget_control && !$budget_control){
                            $this->handleBudgetControlOnEdit($transactions[$i], $paymentDateStr, $category_id, $value);
                        }

                        $transactions[$i]->transaction_date = $transactionDateStr;
                        $transactions[$i]->payment_date = $paymentDateStr;
                        $transactions[$i]->value = $value;
                        $transactions[$i]->description = $description;
                        $transactions[$i]->category_id = $category_id;
                        $transactions[$i]->account_id = $account_id;
                        $transactions[$i]->preview = $preview;
                        $transactions[$i]->usual = $usual;
                        $transactions[$i]->budget_control = $budget_control;
                        $transactions[$i]->save();  
                        array_push($editedTransactions, $transactions[$i]);
            
                        //Increment values for next stallment
                        $transactionDateStr = $transaction_date->modify("+1 month")->format('Y-m-d');
                        $paymentDateStr = $payment_date->modify("+1 month")->format('Y-m-d');
                        $i++;
                    }
                } else {

                    if (!$ref_transaction->budget_control && $budget_control){
                        $this->checkThereIsBudgetControl($paymentDateStr, $category_id, 'D');
                    } 

                    if (!$ref_transaction->budget_control && !$budget_control){
                        $this->handleBudgetControlOnEdit($ref_transaction, $paymentDateStr, $category_id, $value);
                    }
    
                    $ref_transaction->transaction_date = $transactionDateStr;
                    $ref_transaction->payment_date = $paymentDateStr;
                    $ref_transaction->value = $value;
                    $ref_transaction->description = $description;
                    $ref_transaction->category_id = $category_id;
                    $ref_transaction->account_id = $account_id;
                    $ref_transaction->preview = $preview;
                    $ref_transaction->usual = $usual;
                    $ref_transaction->budget_control = $budget_control;
                    $ref_transaction->save();  
                    array_push($editedTransactions, $ref_transaction);
                }    
            });


            return response()->json(["message" => "Transação alterada com sucesso", "transactions" => $editedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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

            $transaction_date = $request->filled('transaction_date') ? $request->transaction_date : $ref_transaction->transaction_date;
            $payment_date = $request->filled('transaction_date') ? $request->transaction_date : $ref_transaction->transaction_date;
            $value = $request->filled('value') ? $request->value : $ref_transaction->value;
            $description = $request->filled('description') ? $request->description : $ref_transaction->description;
            $account_id = $request->filled('account_id') ? $request->account_id : $ref_transaction->account_id;
            $destination_account_id = $request->filled('destination_account_id') ? $request->destination_account_id : $ref_transaction->destination_account_id;
            if ($request->filled('usual')) {
                $usual =  filter_var($request->usual, FILTER_VALIDATE_BOOLEAN);
                } else {
                $usual = $ref_transaction->usual;
            }
    
            if (!$this->validateDate($transaction_date)){
                return response()->json(["message" => "A data informada é inválida"], 400);
            }
    
            if (!$this->validateValue($value)){
                return response()->json(["message" => "O valor da transação deve ser informado como número inteiro maior que zero"], 400);
            }
                
            $transaction_date = new DateTime($transaction_date);
            $dateStr = $transaction_date->format('Y-m-d');
            $editedTransactions = [];

            $origin->transaction_date = $dateStr;
            $origin->payment_date = $dateStr;
            $origin->value = (int)$value * -1;
            $origin->description = $description;
            $origin->account_id = $account_id;
            $origin->usual = $usual;
            $origin->save();  
            array_push($editedTransactions, $origin);

            $destination->transaction_date = $dateStr;
            $destination->payment_date = $dateStr;
            $destination->value = (int)$value;
            $destination->description = $description;
            $destination->account_id = $destination_account_id;
            $destination->usual = $usual;
            $destination->save();  
            array_push($editedTransactions, $destination);
    
            return response()->json(["message" => "Transação registrada com sucesso", "transactions" => $editedTransactions], 200);
        
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function deleteIncomeExpense(Request $request, $id){

        try {

            $cascade = $request->query('cascade') == 'true' ? true : false;

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($ref_transaction->type === "R" || $ref_transaction->type === "D"){
            } else {
                return response()->json(['message' => 'Transação informada não é do tipo receita (R) ou despesa (D).'], 400);
            }

            if ($ref_transaction->installments_key){
                $allInstallments = Transaction::where('installments_key', $ref_transaction->installments_key)->get();
            } else {
                $allInstallments = [$ref_transaction];
            }

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
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }

    public function deleteTransfer(Request $request, $id){

        try {

            $ref_transaction = Transaction::find($id);

            if (!$ref_transaction){
                return response()->json(['message' => 'Transação não encontrada (id inválido)'], 400);
            }

            if ($ref_transaction->type === "T"){
            } else {
                return response()->json(['message' => 'Transação informada não é do tipo transferência (T).'], 400);
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
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
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
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
    }
}
