<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Category;
use DateTime;

class BalanceController extends Controller
{
    public function getBalance(Request $request){

        /**
         * QUERIES:
         * date: YYYY-MM-AA 
         * from: YYYY-MM-AA
         * to: YYYY-MM-AA
         * typeofdate: transaction_date || payment_date 
         * includepreview: true || false
         * account: int/string
         * category: int/string
         */

        try{
            // Get and set date
            $dateQuery = $request->query('date');
            $fromQuery = $request->query('from');
            $toQuery = $request->query('to');

            if (!$dateQuery){
                if(!$fromQuery){
                    return response()->json(["message" => "É necessário informar uma data ('date') ou uma data de início ('from') como YYYY-MM-DD"], 400);
                } else if (!$toQuery){
                    return response()->json(["message" => "Data de início ('from') deve ser informada em conjunto com data de término ('to')"], 400);
                }
            } 

            $typeOfDate = $request->query('typeofdate');
            if ($typeOfDate){
                if ($typeOfDate != 'transaction_date'){
                    if ($typeOfDate != 'payment_date'){
                        return response()->json(["message" => "O tipo de data ('typeofdate') é inválido, informe 'transaction_date' ou 'payment_date'"], 400);  
                    }}
            } else {
                return response()->json(["message" => "O tipo de data ('typeofdate') não foi informado"], 400);
            }

            $includeExpected = $request->query('includeexpected');
            if ($includeExpected){
                if ($includeExpected == 'true' || $includeExpected == 'false'){
                    $includeExpected = ($includeExpected == 'true') ? true : false;
                } else {
                  return response()->json(["message" => "O parâmetro incluir transações previstas ('includeexpected') é inválido, informe 'true' ou 'false'"], 400);  
                }
            } else {
                return response()->json(["message" => "O parâmetro incluir transações previstas ('includeexpected') deve ser informado"], 400);
            }

            $account = $request->query('account');
            if ($account === "0"){$account = "null";};
            $category = $request->query('category');
            if ($category === "0"){$category = "null";};

            if($dateQuery){
                $date = explode('-', $dateQuery);
                $dateIsValid = checkdate($date[1], $date[2], $date[0]);
                if (!$dateIsValid){
                    return response()->json(["message" => "A data escolhida ({$dateQuery}) é inválida"], 400);
                }
        
                $firstDateOfMonth = $date[0] . '-' . $date[1] . '-' . '01';
                $dateToCheck = explode('-', $firstDateOfMonth);
                $dateIsValid = checkdate($dateToCheck[1], $dateToCheck[2], $dateToCheck[0]);
                if (!$dateIsValid){
                    return response()->json(["message" => "A data de início {$firstDateOfMonth} calculada a partir da data ({$dateQuery}) é inválida"], 500);
                }
    
                //Date balance
                $incomesOfDate = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, '=', $dateQuery)
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $expensesOfDate = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "=", $dateQuery)
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $balanceOfDate = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "=", $dateQuery)
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                    ->get()
                ->sum('value');
    
                //Month balance until date
                $incomesOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})            
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $expensesOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $balanceOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                //General balance until date
                $incomesOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})                  
                ->get()
                ->sum('value');
    
                $expensesOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})           
                ->get()
                ->sum('value');
    
                $balanceOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})                     
                ->get()
                ->sum('value');
    
                return response()->json([
                    "message" => "Saldo obtido de {$dateQuery}",
                    "date" => [
                        'incomes' => $incomesOfDate,
                        'expenses' => $expensesOfDate,
                        'balance' => $balanceOfDate  
                    ],
                    "month_to_date" => [
                        'incomes' => $incomesOfMonth,
                        'expenses' => $expensesOfMonth,
                        'balance' => $balanceOfMonth  
                    ],
                    "all_to_date" => [
                        'incomes' => $incomesOfAll,
                        'expenses' => $expensesOfAll,
                        'balance' => $balanceOfAll  
                    ],               
                    ], 200);
            } else {

                $from = explode('-', $fromQuery);
                $fromIsValid = checkdate($from[1], $from[2], $from[0]);
                if (!$fromIsValid){
                    return response()->json(["message" => "A data de início escolhida ({$fromQuery}) é inválida"], 400);
                }

                $to = explode('-', $toQuery);
                $toIsValid = checkdate($to[1], $to[2], $to[0]);
                if (!$toIsValid){
                    return response()->json(["message" => "A data de término escolhida ({$toQuery}) é inválida"], 400);
                }

                $incomesOfRange = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})                    
                ->get()
                ->sum('value');
    
                $expensesOfRange = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})      
                ->get()
                ->sum('value');
    
                $balanceOfRange = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})                    
                ->get()
                ->sum('value');

                return response()->json([
                    "message" => "Saldo obtido de {$fromQuery} a {$toQuery}",
                    "daterange" => [
                        'incomes' => $incomesOfRange,
                        'expenses' => $expensesOfRange,
                        'balance' => $balanceOfRange  
                    ]
                    ], 200);
            }
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }   
    }

    public function getBalanceForBudget(Request $request){

        /**
         * QUERIES:
         * date: YYYY-MM-DD (string)
         * typeofdate: transaction_date || payment_date (string)
         */

        try{
            // Get and set date
            $fromQuery = $request->query('from');
            $toQuery = $request->query('to');

            if(!$fromQuery){
                return response()->json(["message" => "É necessário informar uma data de início ('from') como YYYY-MM-DD"], 400);
            } else if (!$toQuery){
                return response()->json(["message" => "Data de início ('from') deve ser informada em conjunto com data de término ('to')"], 400);
            }
            $from = explode('-', $fromQuery);
            $fromIsValid = checkdate($from[1], $from[2], $from[0]);
            if (!$fromIsValid){
                return response()->json(["message" => "A data de início escolhida ({$fromQuery}) é inválida"], 400);
            }
            $to = explode('-', $toQuery);
            $toIsValid = checkdate($to[1], $to[2], $to[0]);
            if (!$toIsValid){
                return response()->json(["message" => "A data de término escolhida ({$toQuery}) é inválida"], 400);
            }

            $typeOfDate = $request->query('typeofdate');
            if ($typeOfDate){
                if ($typeOfDate != 'transaction_date'){
                    if (!$typeOfDate != 'payment_date'){
                        return response()->json(["message" => "O tipo de data ('typeofdate') é inválido, informe 'transaction_date' ou 'payment_date'"], 400);  
                    }}
            } else {
                return response()->json(["message" => "O tipo de data ('typeofdate') deve ser informado"], 400);
            }

            $categories = Category::all();

            $response = [];
            
            foreach ($categories as $category){

                $response['categories'][$category->id] = [];

                $balanceExpected = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->where('category_id', '=', $category->id)
                ->get()
                ->sum('value');

                $balanceMade = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->where('preview', "=", '0')
                ->where('category_id', '=', $category->id)
                ->get()
                ->sum('value');

                $response['categories'][$category->id] = [
                    'expected' => $balanceExpected,
                    'made' => $balanceMade
                ];
            }

            $balanceMonthExpected = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
            ->get()
            ->sum('value');

            $balanceMonthMade = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
            ->where('preview', "=", '0')
            ->get()
            ->sum('value');

            $response['all_month'] = [
                'expected' => $balanceMonthExpected,
                'made' => $balanceMonthMade
            ];

            $balanceAccumulatedExpected = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $toQuery)
            ->get()
            ->sum('value');

            $balanceAccumulatedMade = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $toQuery)
            ->where('preview', "=", '0')
            ->get()
            ->sum('value');

            $response['all_accumulated'] = [
                'expected' => $balanceAccumulatedExpected,
                'made' => $balanceAccumulatedMade
            ];
        
            return response()->json(["message" => "Saldos de {$fromQuery} a {$toQuery} obtidos com sucesso para todas as categorias", "balances" => $response], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
            
    }
}
