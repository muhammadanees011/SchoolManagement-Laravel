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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_user_id')->unsigned()->nullable();
            $table->foreign('organization_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('title');            
            $table->string('email');
            $table->string('phone');
            $table->integer('otp')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('state')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->integer('teachers_count')->nullable();
            $table->integer('students_count')->nullable();
            $table->string('stages')->nullable();
            $table->string('tagline')->nullable();
            $table->string('description')->nullable();
            $table->enum('status',['active','inactive','pending','blocked','deleted'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
