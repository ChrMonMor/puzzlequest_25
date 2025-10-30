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
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('question_id')->primary();
            $table->uuid('run_id');
            $table->integer('flag_id');
            $table->integer('question_type');
            $table->string('question_text', 255);
            $table->integer('question_answer');

            $table->foreign('run_id')->references('run_id')->on('runs')->onDelete('cascade');
            $table->foreign('flag_id')->references('flag_id')->on('flags')->onDelete('cascade');
            $table->foreign('question_type')->references('question_type_id')->on('question_types');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
