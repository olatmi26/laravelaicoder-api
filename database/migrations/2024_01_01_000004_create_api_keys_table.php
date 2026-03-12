<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');          // anthropic, openai, gemini, groq
            $table->text('key_encrypted');       // AES-256 encrypted key
            $table->string('model');             // e.g. claude-sonnet-4-5, gpt-4o
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']); // one key per provider per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
