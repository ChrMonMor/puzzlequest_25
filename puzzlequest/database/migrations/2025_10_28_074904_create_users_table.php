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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('user_name', 50);
            $table->string('user_email', 255)->unique();
            $table->string('user_password', 255);
            $table->boolean('user_verified')->default(false);
            $table->timestamp('user_joined')->useCurrent();
            $table->string('user_img', 255)->nullable();
            $table->timestamp('user_email_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
