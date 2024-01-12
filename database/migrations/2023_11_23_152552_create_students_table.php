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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('school_id')->unsigned()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->bigInteger('attribute_id')->unsigned()->nullable();
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->json('attributes')->nullable();
            $table->string('student_id')->unique()->nullable();
            $table->string('upn')->nullable();
            $table->string('mifare_id')->unique()->nullable();
            $table->boolean('fsm_activated')->default(false);
            $table->double('fsm_amount')->default(0); 
            $table->string('purse_type')->nullable();
            $table->string('site')->nullable();
            $table->date('dob')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->string('stage')->nullable();
            $table->string('about_me')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('allergies')->nullable();
            $table->string('medical_conditions')->nullable();
            $table->string('photo_url')->nullable();
            $table->enum('transportation_mode',['bus','car','walking','bicycle','public_transport','parent_pick_drop','other'])->nullable();
            $table->string('bus_route')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
