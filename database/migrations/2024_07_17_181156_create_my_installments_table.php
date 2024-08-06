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
        Schema::create('my_installments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('shop_item_id')->unsigned()->nullable();
            $table->foreign('shop_item_id')->references('id')->on('shop_items')->onDelete('cascade');
            $table->bigInteger('purchases_id')->unsigned()->nullable();
            $table->foreign('purchases_id')->references('id')->on('my_purchases')->onDelete('cascade');
            $table->integer('installment_no');
            $table->double('installment_amount');
            $table->date('installment_deadline')->nullable();
            $table->enum('payment_status',['paid','pending'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('my_installments');
    }
};
