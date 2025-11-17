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
        Schema::create('history_flags', function (Blueprint $table) {
            $table->id('history_flag_id');
            $table->uuid('history_id');
            $table->timestamp('history_flag_reached')->nullable();
            $table->double('history_flag_long')->nullable();
            $table->double('history_flag_lat')->nullable();
            $table->double('history_flag_distance')->nullable();
            $table->double('history_flag_type')->nullable();
            $table->integer('history_flag_point')->nullable();

            $table->foreign('history_id')->references('history_id')->on('histories')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_flags');
    }
};
