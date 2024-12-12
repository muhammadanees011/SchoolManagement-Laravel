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
        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shop_id')->unsigned()->nullable();
            $table->foreign('shop_id')->references('id')->on('organization_shops')->onDelete('cascade');
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('attribute_id')->unsigned()->nullable();
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->bigInteger('school_id')->unsigned()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->json('attributes')->nullable();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('detail')->nullable();
            $table->double('price');
            $table->integer('quantity')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('quantity_sold')->nullable();
            $table->string('product_owner_email')->nullable();
            $table->json('limit_colleges')->nullable();
            $table->json('limit_courses')->nullable();
            $table->json('visibility_options')->nullable();
            $table->string('product_type')->nullable();
            $table->enum('payment_plan',['full_payment','installments','installments_and_deposit'])->default('full_payment');
            $table->enum('status',['available','not_available','deleted','expired'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_items');
    }
};
