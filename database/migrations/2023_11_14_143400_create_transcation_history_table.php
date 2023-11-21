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
        Schema::create('transcation_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sender_wallet_id')->unsigned()->nullable();
            $table->foreign('sender_wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->bigInteger('receiver_wallet_id')->unsigned()->nullable();
            $table->foreign('receiver_wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->double('amount')->nullable();
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
