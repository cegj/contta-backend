<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use DateTime;

class BalanceController extends Controller
{
    public function getBalanceOfDate(Request $request){

        /**
         * QUERIES:
         * date: YYYY-MM-AA || YYYY-MM || YYYY (optional)
         * typeofdate: transaction || payment (default)
         */

        try{
            // Get and set date
            $dateQuery = $request->query('date');
            if (!$dateQuery){return response()->json(["message" => "É necessário informar uma data ('date') como YYYY-MM-DD"], 400);}
            $date = explode('-', $dateQuery);
            // $sizeOfDate = sizeOf($date);

            // if($sizeOfDate == 1){
            //     $date = $date[0] . '-' . '12' . '-' . '31';
            // } else if ($sizeOfDate == 2) {
            //     $dateTime = new DateTime($dateQuery);
            //     $dateTime->modify('last day of this month');
            //     $date = $date[0] . '-' . $date[1] . '-' . $dateTime->format('d');
            // } else if ($sizeOfDate == 3) {
            //     $date = $date[0] . '-' . $date[1] . '-' . $date[2];
            // }

            //Check if date is valid
            $dateIsValid = checkdate($date[1], $date[2], $date[0]);
            if (!$dateIsValid){
                return response()->json(["message" => "A data escolhida ({$date}) é inválida"], 400);
            }

            $typeOfDate = $request->query('typeofdate');
            if ($typeOfDate && $typeOfDate == 'transaction'){
                $typeOfDate = 'transaction_date';
            } else {
                $typeOfDate = 'payment_date';
            }

            $firstDateOfMonth = $date[0] . '-' . $date[1] . '-' . '01';
            //Check if starting date is valid
            $dateToCheck = explode('-', $firstDateOfMonth);
            $dateIsValid = checkdate($dateToCheck[1], $dateToCheck[2], $dateToCheck[0]);
            if (!$dateIsValid){
                return response()->json(["message" => "A data de início {$startingDate} calculada a partir da data ({$date}) é inválida"], 500);
            }

            // if ($cumulative){
            //     $incomes = Transaction::selectRaw("value, " . $typeOfDate)
            //     ->where($typeOfDate, "<=", $date)
            //     ->where('type', "=", 'R')
            //     ->orderBy($typeOfDate, 'asc')
            //     ->get()
            //     ->sum('value');
            //     $expenses = Transaction::selectRaw("value, " . $typeOfDate)
            //     ->where($typeOfDate, "<=", $date)
            //     ->where('type', "=", 'D')
            //     ->orderBy($typeOfDate, 'asc')
            //     ->get()
            //     ->sum('value');
            //     $balance = Transaction::selectRaw("value, " . $typeOfDate)
            //     ->where($typeOfDate, "<=", $date)
            //     ->orderBy($typeOfDate, 'asc')
            //     ->get()
            //     ->sum('value');
            //     $message = "Saldo obtido até {$date}";
            // } else {
            //     $incomes = Transaction::selectRaw("value, " . $typeOfDate)
            //     ->whereBetween($typeOfDate, [$startingDate, $date])
            //     ->where('type', "=", 'R')
            //     ->orderBy($typeOfDate, 'asc')
            //     ->get()
            //     ->sum('value');
            //     $expenses = Transaction::selectRaw("value, " . $typeOfDate)
            //     ->whereBetween($typeOfDate, [$startingDate, $date])
            //     ->where('type', "=", 'D')
            //     ->orderBy($typeOfDate, 'asc')
            //     ->get()
            //     ->sum('value');
            //     $balance = Transaction::selectRaw("value, " . $typeOfDate)
            //     ->where($typeOfDate, "<=", $date)
            //     ->whereBetween($typeOfDate, [$startingDate, $date])
            //     ->get()
            //     ->sum('value');
            //     $message = "Saldo obtido de {$startingDate} até {$date}";

                //Balance of date
                $incomesOfDate = Transaction::selectRaw("value, " . $typeOfDate)
                ->where($typeOfDate, "=", $date)
                ->where('type', "=", 'R')
                ->orderBy($typeOfDate, 'asc')
                ->get()
                ->sum('value');

                $expensesOfDate = Transaction::selectRaw("value, " . $typeOfDate)
                ->where($typeOfDate, "=", $date)
                ->where('type', "=", 'R')
                ->orderBy($typeOfDate, 'asc')
                ->get()
                ->sum('value');

                $balaceOfDate = Transaction::selectRaw("value, " . $typeOfDate)
                ->where($typeOfDate, "=", $date)
                ->orderBy($typeOfDate, 'asc')
                ->get()
                ->sum('value');

                //Balance month until date

                //Balance general until date

            }

            return response()->json(["message" => $message, 'incomes' => $incomes, 'expenses' => $expenses, 'balance' => $balance, 'c' => $cumulative], 200);
            
        } catch (\Throwable $th) {
            return response()->json(["message" => "Ocorreu um erro", "error" => $th->getMessage()], 500);
        }
            
    }
}
