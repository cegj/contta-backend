<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Category;
use App\Models\Account;
use App\Models\User;


return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('type');
            $table->integer('value');
            $table->string('description');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories');
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->boolean('preview')->default(false);
            $table->boolean('usual')->default(false);
            $table->string('transfer_key')->nullable();
            $table->string('installments_key')->nullable();
            $table->integer('installment')->nullable();
            $table->integer('total_installments')->nullable();
            $table->timestamps();
        });

        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
