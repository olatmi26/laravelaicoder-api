<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ui_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');           // S3 / local path
            $table->string('status')->default('uploaded'); // uploaded, analyzing, done, failed
            $table->json('design_tokens')->nullable(); // extracted colors, fonts, spacing
            $table->json('component_map')->nullable(); // HTML file → Blade/React mapping
            $table->integer('components_found')->default(0);
            $table->integer('pages_found')->default(0);
            $table->text('integration_plan')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');               // e.g. barryvdh/laravel-dompdf
            $table->string('type');               // composer, npm
            $table->string('version')->nullable();
            $table->string('status')->default('installed'); // installing, installed, failed
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
        Schema::dropIfExists('ui_uploads');
    }
};
