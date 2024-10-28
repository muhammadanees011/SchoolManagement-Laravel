<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('acct_id')->nullable();
            $table->string('charge_id')->nullable();
            $table->string('last_4')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_holder_name')->nullable();
            $table->double('amount')->nullable();
            $table->enum('type', ['trip_funds', 'meal_funds', 'health_care', 'school_shop_funds', 'top_up','pos_transaction','school_shop_refund','pos_refund'])->nullable();
            $table->enum('status',['successful','failed','pending','deleted'])->default('successful');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcation_history');
    }
};
