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
            $table->bigInteger('organization_id')->unsigned()->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('title');     
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();       
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('state')->nullable();
            $table->integer('teachers_count')->nullable()->default(0);
            $table->integer('students_count')->nullable()->default(0);
            $table->string('stages')->nullable();
            $table->string('tagline')->nullable();
            $table->string('description')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('logo')->nullable();
            $table->string('finance_coordinator_email')->nullable();
            $table->enum('status',['active','inactive','pending','blocked','deleted'])->default('active');
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
