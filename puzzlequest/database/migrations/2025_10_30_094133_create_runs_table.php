<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table) {
            $table->uuid('run_id')->primary();
            $table->uuid('user_id');
            $table->integer('run_type');
            $table->timestamp('run_added')->useCurrent();
            $table->string('run_title', 50);
            $table->text('run_description')->nullable();
            $table->string('run_img_icon', 255)->nullable();
            $table->string('run_img_front', 255)->nullable();
            $table->string('run_pin', 6)->nullable();
            $table->string('run_location', 3)->nullable();
            $table->timestamp('run_last_update')->nullable();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('run_type')->references('run_type_id')->on('run_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
