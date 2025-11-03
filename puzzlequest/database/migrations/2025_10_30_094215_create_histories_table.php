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
            $table->uuid('user_id')->nullable();
            $table->uuid('run_id')->nullable();
            $table->timestamp('history_start');
            $table->timestamp('history_end')->nullable();
            $table->timestamp('history_run_update');
            $table->string('history_run_type', 255);
            $table->integer('history_run_position')->nullable();
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
