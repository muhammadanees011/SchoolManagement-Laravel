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
        Schema::create('my_purchases', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('shop_item_id')->unsigned()->nullable();
            $table->foreign('shop_item_id')->references('id')->on('shop_items')->onDelete('cascade');
            $table->double('total_price')->nullable();
            $table->double('amount_paid')->nullable();
            $table->enum('payment_status',['fully_paid','partially_paid'])->nullable();
            $table->enum('refund_status',['refunded','not_refunded','refund_requested'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('my_purchases');
    }
};
