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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('school_id')->unsigned()->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->bigInteger('instructor_id')->unsigned()->nullable();
            $table->foreign('instructor_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->integer('credit_hrs')->nullable();
            $table->string('stage')->nullable();
            $table->integer('total_marks')->nullable();
            $table->integer('pass_marks')->nullable();
            $table->integer('assignment_marks_total')->nullable();
            $table->integer('quizez_marks_total')->nullable();
            $table->integer('viva_marks_total')->nullable();
            $table->integer('presentation_marks_total')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->date('registration_fee')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
