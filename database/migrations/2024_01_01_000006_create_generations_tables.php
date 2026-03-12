<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('status')->default('pending'); // pending, running, done, failed
            $table->string('ai_provider')->nullable();    // anthropic, openai — which was used
            $table->string('ai_model')->nullable();
            $table->boolean('used_byok')->default(false); // was user's own key used?
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->json('agent_pipeline')->nullable();   // ordered list of agent IDs for this run
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('agent_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->string('agent_id');           // architect, laravel, mobile, frontend, etc.
            $table->integer('sequence')->default(0); // order in pipeline
            $table->string('status')->default('pending'); // pending, running, done, failed, skipped
            $table->longText('output_text')->nullable();  // full agent response
            $table->json('generated_files')->nullable();  // list of files this agent produced
            $table->integer('tokens_input')->default(0);
            $table->integer('tokens_output')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['generation_id', 'sequence']);
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');               // e.g. app/Http/Controllers/Api/ProductController.php
            $table->longText('content');
            $table->string('language')->nullable(); // php, javascript, dart, blade
            $table->string('status')->default('generated'); // generated, reviewing, approved
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'path']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('agent_jobs');
        Schema::dropIfExists('generations');
    }
};
