<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use DateTime;

class BalanceController extends Controller
{
    public function getBalance(Request $request){

        /**
         * QUERIES:
         * date: YYYY-MM-AA 
         * from: YYYY-MM-AA
         * to: YYYY-MM-AA
         * typeofdate: transaction || payment 
         * includepreview: true || false
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
                if (!$typeOfDate == 'transaction_date'){
                    if (!$typeOfDate == 'payment_date'){
                        return response()->json(["message" => "O tipo de data ('typeofdate') é inválido, informe 'transaction_date' ou 'payment_date'"], 400);  
                    }}
            } else {
                return response()->json(["message" => "O tipo de data ('typeofdate') não foi informado"], 400);
            }

            $includeExpected = $request->query('includeexpected');
            if ($includeExpected){
                if ($includeExpected == 'true' || $includeExpected == 'false'){
                    $includeExpected = $includeExpected === 'true' ? true : false;
                } else {
                  return response()->json(["message" => "O parâmetro incluir transações previstas ('includeexpected') é inválido, informe 'true' ou 'false'"], 400);  
                }
            } else {
                return response()->json(["message" => "O parâmetro incluir transações previstas ('includeexpected') deve ser informado"], 400);
            }

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
                ->where('type', "=", 'R');
                if(!$includeExpected){$incomesOfDate->where('preview', '=', 0);}
                $incomesOfDate->get()
                ->sum('value');
    
                $expensesOfDate = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "=", $dateQuery)
                ->where('type', "=", 'D');
                if(!$includeExpected){$expensesOfDate->where('preview', '=', 0);}
                $expensesOfDate->get()
                ->sum('value');
    
                $balanceOfDate = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "=", $dateQuery);
                if(!$includeExpected){$balanceOfDate->where('preview', '=', 0);}
                $balanceOfDate->get()
                ->sum('value');
    
                //Month balance until date
                $incomesOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->where('type', "=", 'R');
                if(!$includeExpected){$incomesOfMonth->where('preview', '=', 0);}
                $incomesOfMonth->get()
                ->sum('value');
    
                $expensesOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery])
                ->where('type', "=", 'D');
                if(!$includeExpected){$expensesOfMonth->where('preview', '=', 0);}
                $expensesOfMonth->get()
                ->sum('value');
    
                $balanceOfMonth = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$firstDateOfMonth, $dateQuery]);
                if(!$includeExpected){$balanceOfMonth->where('preview', '=', 0);}
                $balanceOfMonth->get()
                ->sum('value');
    
                //General balance until date
                $incomesOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->where('type', "=", 'R');
                if(!$includeExpected){$incomesOfAll->where('preview', '=', 0);}
                $incomesOfAll->get()
                ->sum('value');
    
                $expensesOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery)
                ->where('type', "=", 'D');
                if(!$includeExpected){$expensesOfAll->where('preview', '=', 0);}
                $expensesOfAll->get()
                ->sum('value');
    
                $balanceOfAll = Transaction::select("value", $typeOfDate)
                ->where($typeOfDate, "<=", $dateQuery);
                if(!$includeExpected){$balanceOfAll->where('preview', '=', 0);}
                $balanceOfAll->get()
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
                ->where('type', "=", 'R');
                if(!$includeExpected){$incomesOfRange->where('preview', '=', 0);}
                $incomesOfRange->get()
                ->sum('value');
    
                $expensesOfRange = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery])
                ->where('type', "=", 'D');
                if(!$includeExpected){$expensesOfRange->where('preview', '=', 0);}
                $expensesOfRange->get()
                ->sum('value');
    
                $balanceOfRange = Transaction::select("value", $typeOfDate)
                ->whereBetween($typeOfDate, [$fromQuery, $toQuery]);
                if(!$includeExpected){$balanceOfRange->where('preview', '=', 0);}
                $balanceOfRange->get()
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
}
