<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\Transaction;

class Category extends Model
{
    use HasFactory;

    public function group(){
        return $this->belongsTo(Group::class);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

}
