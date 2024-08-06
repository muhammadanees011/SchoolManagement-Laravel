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
            //--------------IF Product is trip-----------------
            $table->bigInteger('trip_id')->unsigned()->nullable();
            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            //--------------IF Product is shop item-----------------
            $table->bigInteger('shop_item_id')->unsigned()->nullable();
            $table->foreign('shop_item_id')->references('id')->on('shop_items')->onDelete('cascade');
            //--------------IF Product is course-----------------
            $table->bigInteger('course_id')->unsigned()->nullable();
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            //--------------Payment Plan-----------------
            $table->integer('total_installments')->nullable();
            $table->double('amount_per_installment')->nullable();
            $table->double('initial_deposit_installments')->nullable();
            $table->date('initial_deposit_deadline_installments')->nullable();
            $table->json('other_installments_deadline_installments')->nullable();
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
