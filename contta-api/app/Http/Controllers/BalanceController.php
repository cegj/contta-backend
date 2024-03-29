<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Group;
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
         * group: int/string
         * includehiddenaccounts: true | false (default)
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

            if ($request->filled('group') && $request->filled('category')){
                return response()->json(["message" => "Não é possível filtrar por grupo e categoria ao mesmo tempo"], 400);
            }

            $account = $request->query('account');
            if ($account === "0"){$account = "null";};
            $category = $request->query('category');
            if ($category === "0"){$category = "null";};
            $group = $request->query('group');
            if ($group === "0"){$group = "null";};
            $includeHiddenAccounts = filter_var($request->includehiddenaccounts, FILTER_VALIDATE_BOOLEAN);

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
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})  
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})    
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $expensesOfDate = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "=", $dateQuery)
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})  
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})        
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $balanceOfDate = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "=", $dateQuery)
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})               
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                    ->get()
                ->sum('value');
    
                //Month balance until date
                $incomesOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})            
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $expensesOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);}) 
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                       
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                $balanceOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                        
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})    
                ->get()
                ->sum('value');
    
                //General balance until date
                $incomesOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                        
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})                  
                ->get()
                ->sum('value');
    
                $expensesOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                        
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})           
                ->get()
                ->sum('value');
    
                $balanceOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                       
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
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'R')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);}) 
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                       
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})                    
                ->get()
                ->sum('value');
    
                $expensesOfRange = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('type', "=", 'D')
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                        
                ->when($category, function($q, $category){
                    if ($category === "null") {return $q->whereNull('category_id');}
                    else return $q->where('category_id', '=', $category);})      
                ->get()
                ->sum('value');
    
                $balanceOfRange = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->where('budget_control', 0)
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->when(!$includeExpected, function($q){
                    return $q->where('preview', '=', 0);})
                ->when($account, function($q, $account){
                    if ($account === "null") {return $q->whereNull('account_id');}
                    else return $q->where('account_id', '=', $account);})
                ->when($group, function($q, $group){
                    if ($group === "null") {return $q->whereNull('category_id');}
                    else {
                        $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                        $ids = [];
                        foreach($categoriesOfGroup as $id){
                            array_push($ids, $id["id"]);
                        }
                        return $q->whereIn('category_id', $ids);}})                        
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
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }   
    }

    public function getAllDaysOfMonthBalance(Request $request){

        /**
         * QUERIES:
         * yearmonth: YYYY-MM 
         * typeofdate: transaction_date || payment_date 
         * includepreview: true || false
         * account: int/string
         * category: int/string
         * group: int/string
         * includehiddenaccounts: true | false (default)
         */

        try{
            // Get and set date
            $yearMonthQuery = $request->query('yearmonth');

            if (!$yearMonthQuery){
                return response()->json(["message" => "É necessário informar o mês e o ano (yearnmonth) no formato AAAA-MM"], 400);
            }

            $refDate = $request->query('yearmonth') . "-01";
            
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

            if ($request->filled('group') && $request->filled('category')){
                return response()->json(["message" => "Não é possível filtrar por grupo e categoria ao mesmo tempo"], 400);
            }

            $account = $request->query('account');
            if ($account === "0"){$account = "null";};
            $category = $request->query('category');
            if ($category === "0"){$category = "null";};
            $group = $request->query('group');
            if ($group === "0"){$group = "null";};
            $includeHiddenAccounts = filter_var($request->includehiddenaccounts, FILTER_VALIDATE_BOOLEAN);

            $refDateArr = explode('-', $refDate);
            $dateIsValid = checkdate($refDateArr[1], $refDateArr[2], $refDateArr[0]);
            if (!$dateIsValid){
                return response()->json(["message" => "O mês e/ou o ano escolhido ({$yearMonthQuery}) é inválido"], 400);
            }

            $firstDateOfMonth = $refDate;
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $refDateArr[1], $refDateArr[0]);
            $refDate = new Datetime($refDate);
            $refDateStr = $refDate->format('Y-m-d');

            $response = [];

            for ($i = 1; $i <= $daysInMonth; $i++){
                
            //Date balance
            $incomesOfDate = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, '=', $refDateStr)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'R')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})  
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})    
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})    
            ->get()
            ->sum('value');

            $expensesOfDate = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "=", $refDateStr)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'D')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})  
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})        
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})    
            ->get()
            ->sum('value');

            $balanceOfDate = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "=", $refDateStr)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})               
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})    
                ->get()
            ->sum('value');

            //Month balance until date
            $incomesOfMonth = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$firstDateOfMonth, $refDateStr])
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'R')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})            
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})                
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})    
            ->get()
            ->sum('value');

            $expensesOfMonth = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$firstDateOfMonth, $refDateStr])
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'D')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);}) 
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})                       
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})    
            ->get()
            ->sum('value');

            $balanceOfMonth = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$firstDateOfMonth, $refDateStr])
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})                        
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})    
            ->get()
            ->sum('value');

            //General balance until date
            $incomesOfAll = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $refDateStr)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'R')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})                        
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})                  
            ->get()
            ->sum('value');

            $expensesOfAll = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $refDateStr)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'D')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})                        
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})           
            ->get()
            ->sum('value');

            $balanceOfAll = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $refDateStr)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            ->when($account, function($q, $account){
                if ($account === "null") {return $q->whereNull('account_id');}
                else return $q->where('account_id', '=', $account);})
            ->when($group, function($q, $group){
                if ($group === "null") {return $q->whereNull('category_id');}
                else {
                    $categoriesOfGroup = Category::select("id")->where("group_id", $group)->get();
                    $ids = [];
                    foreach($categoriesOfGroup as $id){
                        array_push($ids, $id["id"]);
                    }
                    return $q->whereIn('category_id', $ids);}})                       
            ->when($category, function($q, $category){
                if ($category === "null") {return $q->whereNull('category_id');}
                else return $q->where('category_id', '=', $category);})                     
            ->get()
            ->sum('value');

            $response[$i] = [
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
            ];

        //Increment values for next day
        $refDateStr = $refDate->modify("+1 day")->format('Y-m-d');
        }

            return response()->json([
                "message" => "Saldo obtido de {$yearMonthQuery}",
                "balances" => $response             
                ], 200);
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }   
    }

    public function getAllCategoriesBalance(Request $request){

        /**
         * QUERIES:
         * date: YYYY-MM-DD 
         * typeofdate: transaction_date || payment_date 
         * includepreview: true || false
         * includehiddenaccounts: true | false (default)
         * isgroup: true | false (default)
         */

        try{
            // Get and set date
            $dateQuery = $request->query('date');

            if (!$dateQuery){
                return response()->json(["message" => "É necessário informar uma data no formato AAAA-MM-DD"], 400);
            }

            $dateArr = explode('-', $dateQuery);
            $dateIsValid = checkdate($dateArr[1], $dateArr[2], $dateArr[0]);
            if (!$dateIsValid){
                return response()->json(["message" => "A data escolhida ({$dateQuery}) é inválida"], 400);
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

            $includeHiddenAccounts = filter_var($request->includehiddenaccounts, FILTER_VALIDATE_BOOLEAN);
            $isGroup = filter_var($request->isgroup, FILTER_VALIDATE_BOOLEAN);

            $firstDateOfMonth = $dateArr[0] . '-' . $dateArr[1] . '-01';

            if ($isGroup) {
                $ids = Group::select('id')->get();
            } else {
                $ids = Category::select('id')->get();
            }

            $response = [];
            foreach ($ids as $id) {
                $id = $id["id"]; 
                echo $id;
            //Date balance
            $incomesOfDate = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, '=', $dateQuery)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'R')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            $expensesOfDate = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "=", $dateQuery)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'D')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            $balanceOfDate = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "=", $dateQuery)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            //Month balance until date
            $incomesOfMonth = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'R')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})            
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            $expensesOfMonth = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'D')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            $balanceOfMonth = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            //General balance until date
            $incomesOfAll = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $dateQuery)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'R')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            $expensesOfAll = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $dateQuery)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('type', "=", 'D')
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');

            $balanceOfAll = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $dateQuery)
            ->where('budget_control', 0)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->when(!$includeExpected, function($q){
                return $q->where('preview', '=', 0);})
            // ->when($account, function($q, $account){
            //     if ($account === "null") {return $q->whereNull('account_id');}
            //     else return $q->where('account_id', '=', $account);})  
            ->when($isGroup, function($q) use ($id){
                $categoriesOfGroup = Category::select("id")->where("group_id", $id)->get();
                $idsOfGroupCategories = [];
                foreach($categoriesOfGroup as $idOfCategory){
                    array_push($idsOfGroupCategories, $idOfCategory["id"]);
                }
                return $q->whereIn('category_id', $idsOfGroupCategories);})    
            ->when(!$isGroup, function($q) use ($id){
                return $q->where('category_id', '=', $id);})    
            ->get()
            ->sum('value');
                
            $response[$id] = [
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
            ];
        }
            return response()->json([
                "message" => "Saldos de todas as categorias obtidos com referência a {$dateQuery}",
                "balances" => $response             
                ], 200);
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }   
    }

    public function getBalanceForBudget(Request $request){

        /**
         * QUERIES:
         * date: YYYY-MM-DD (string)
         * typeofdate: transaction_date || payment_date (string)
         * includehiddenaccounts: true | false (default)
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
                    if ($typeOfDate != 'payment_date'){
                        return response()->json(["message" => "O tipo de data ('typeofdate') é inválido, informe 'transaction_date' ou 'payment_date'"], 400);  
                    }}
            } else {
                return response()->json(["message" => "O tipo de data ('typeofdate') deve ser informado"], 400);
            }

            $includeHiddenAccounts = filter_var($request->includehiddenaccounts, FILTER_VALIDATE_BOOLEAN);

            $categories = Category::all();

            $response = [];
            
            foreach ($categories as $category){

                $response['categories'][$category->id] = [];

                $balanceExpected = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('category_id', '=', $category->id)
                ->get()
                ->sum('value');

                $balanceMade = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->when(($includeHiddenAccounts != "true"), function($q){
                    return $q->where(function ($q) {
                        $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                    });})     
                ->where('preview', "=", '0')
                ->where('category_id', '=', $category->id)
                ->get()
                ->sum('value');

                $response['categories'][$category->id] = [
                    'expected' => $balanceExpected,
                    'made' => $balanceMade
                ];
            }

            $balanceNullCatExpected = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->whereNull('category_id')
            ->get()
            ->sum('value');

            $balanceNullCatMade = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('preview', "=", '0')
            ->whereNull('category_id')
            ->get()
            ->sum('value');

            $response['categories']["0"] = [
                "expected" => $balanceNullCatExpected,
                "made" => $balanceNullCatMade];

            $balanceMonthExpected = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->get()
            ->sum('value');

            $balanceMonthMade = Transaction::select("value", $typeOfDate)
            ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('preview', "=", '0')
            ->get()
            ->sum('value');

            $response['all_month'] = [
                'expected' => $balanceMonthExpected,
                'made' => $balanceMonthMade
            ];

            $balanceAccumulatedExpected = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $toQuery)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->get()
            ->sum('value');

            $balanceAccumulatedMade = Transaction::select("value", $typeOfDate)
            ->where($typeOfDate, "<=", $toQuery)
            ->when(($includeHiddenAccounts != "true"), function($q){
                return $q->where(function ($q) {
                    $q->whereRelation('account', 'show', '=', 1)->orWhere('account_id', null);
                });})     
            ->where('preview', "=", '0')
            ->get()
            ->sum('value');

            $response['all_accumulated'] = [
                'expected' => $balanceAccumulatedExpected,
                'made' => $balanceAccumulatedMade
            ];
        
            return response()->json(["message" => "Saldos de {$fromQuery} a {$toQuery} obtidos com sucesso para todas as categorias", "balances" => $response], 200);

        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage(), "origin" => basename($th->getFile()), "line" => $th->getLine()], 500);
        }
            
    }
}
