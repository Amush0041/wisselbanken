<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // Human-readable label for the token (e.g. "AI import service").
            $table->string('name');
            // SHA-256 hash of the plaintext token. The plaintext is returned once on creation
            // and never stored.
            $table->string('token', 64)->unique();
            $table->dateTime('last_used_at')->nullable();
            // null = never expires.
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
