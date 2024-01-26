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
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('trip_id')->unsigned()->nullable();
            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            //--------------Installment Plan-----------------
            $table->integer('total_installments')->nullable();
            $table->double('amount_per_installment')->nullable();
            $table->double('initial_deposit')->nullable();
            $table->date('initial_deposit_deadline')->nullable();
            $table->json('other_installments_deadline')->nullable();
            //--------------Deposit Plan------------------
            $table->integer('total_deposit')->nullable();
            $table->double('initial_deposit')->nullable();
            $table->double('final_deposit')->nullable();
            $table->date('initial_deposit_deadline')->nullable();
            $table->date('final_deposit_deadline')->nullable();
            //--------------Manual Payment Plan------------
            $table->integer('total_amount')->nullable();
            $table->double('initial_amount')->nullable();
            $table->double('final_amount')->nullable();
            $table->date('initial_amount_deadline')->nullable();
            $table->date('final_amount_deadline')->nullable();
            $table->string('comments')->nullable();
            $table->enum('payment_plan',['installments','deposit','manual_payments'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
