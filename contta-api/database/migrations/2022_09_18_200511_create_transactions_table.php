<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Category;
use App\Models\Account;


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
            $table->foreignIdFor(Category::class);
            $table->foreignIdFor(Account::class);
            $table->foreignIdFor(User::class);
            $table->boolean('preview')->default(false);
            $table->boolean('usual');
            $table->integer('transfer_id')->nullable();
            $table->integer('installments_id')->nullable();
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
