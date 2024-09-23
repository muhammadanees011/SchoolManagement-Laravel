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
        Schema::create('user_cards', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('school_id')->unsigned()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->string('card_id')->unique();
            $table->string('customer_id');
            $table->string('cardholder_name')->nullable();
            $table->string('brand')->nullable();
            $table->integer('last_4')->nullable();
            $table->unsignedInteger('card_expiry_month')->nullable();
            $table->unsignedInteger('card_expiry_year')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_cards');
    }
};
