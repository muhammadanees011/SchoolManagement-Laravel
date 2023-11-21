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
        Schema::create('student_course', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('student_id')->unsigned()->nullable();
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('course_id')->unsigned()->nullable();
            $table->foreign('course_id')->references('id')->on('users')->onDelete('cascade');
            $table->integer('obtained_marks')->nullable();
            $table->integer('assignment_marks')->nullable();
            $table->integer('quizez_marks')->nullable();
            $table->integer('viva_marks')->nullable();
            $table->integer('presentation_marks')->nullable();
            $table->enum('status',['clear','fail','droped'])->nullable();
            $table->enum('comment',['excelent','good','poor','very_poor'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_course');
    }
};
