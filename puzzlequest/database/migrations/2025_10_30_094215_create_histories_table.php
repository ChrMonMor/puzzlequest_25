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
        Schema::create('histories', function (Blueprint $table) {
            $table->uuid('history_id')->primary();
            $table->uuid('user_id');
            $table->uuid('run_id');
            $table->timestamp('history_start');
            $table->timestamp('history_end');
            $table->timestamp('history_run_update');
            $table->string('history_run_type', 255);
            $table->integer('history_run_position');

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('run_id')->references('run_id')->on('runs')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histories');
    }
};
