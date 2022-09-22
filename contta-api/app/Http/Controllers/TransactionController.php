<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Transaction;
use DateTime;

class TransactionController extends Controller
{
    private function validateDate($date, $format = 'd/m/Y'){
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    private function validateType($type){
        if ($type == "R" 
        || $type == "D"
        || $type == "T"
        || $type == "I"){
            return $type;
        } else {
            return false; 
        }
    }

    private function validateValue($value){

        $int = (int)$value;
        $float = (float)$value;

        if ($int == $float){
            return $int;
        } else {
            return false;
        }
    }

    public function create(Request $request){

        $authUser = JWTAuth::parseToken()->toUser();

        $date = $request->date;
        $type = $request->type;
        $value = $this->validateValue($request->value);
        $description = $request->description;
        $category_id = (int)$request->category_id;
        $account_id = (int)$request->account_id;
        $destination_account_id = (int)$request->destination_account_id;
        $user_id = $authUser->id;
        $preview = $request->preview;
        $usual = $request->usual;
        $installment = $request->installment;
        $total_installments = $request->total_installments;

        if (!$this->validateDate($date)){
            return response()->json(["message" => "A data informada é inválida"], 400);
        }

        if (!$this->validateType($type)){
            return response()->json(["message" => "O valor do tipo de transação deve ser R, D, T ou I"], 400);
        }

        if (!$value && $value !== 0){
            return response()->json(["message" => "O valor da transação deve ser informado como número inteiro"], 400);
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
        
        if ($type === "T" && !$destination_account_id){
            return response()->json(["message" => "Para transação de transferência, é necessário informar uma conta de destino"], 400);
        }   
    
    }
}
