<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\Account;
use App\Models\User;

class Transaction extends Model
{
    use HasFactory;

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function account(){
        return $this->belongsTo(Account::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

}
