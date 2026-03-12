<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');            // laravel-web, laravel-api, react-native, flutter, react-spa, vue-spa, admin-panel
            $table->string('directory')->nullable();   // local disk path (desktop only)
            $table->string('status')->default('idle'); // idle, building, done, error
            $table->integer('progress')->default(0);   // 0–100
            $table->string('template')->nullable();    // ecommerce, saas, blog, api, multitenant, custom
            $table->string('php_version')->nullable()->default('8.3');
            $table->string('node_version')->nullable()->default('20');
            $table->string('dart_version')->nullable();
            $table->string('mobile_framework')->nullable(); // react-native or flutter
            $table->string('db_driver')->nullable()->default('mysql');
            $table->integer('port')->nullable()->default(8000);
            $table->text('description')->nullable();
            $table->json('packages')->nullable();   // selected composer/npm packages
            $table->string('custom_ui_path')->nullable(); // uploaded HTML theme path
            $table->integer('generation_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index('user_id');
        });

        Schema::create('pipeline_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('target_project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('type');             // qa_to_mobile, api_to_frontend
            $table->json('payload')->nullable(); // API spec, contracts passed between projects
            $table->string('status')->default('pending'); // pending, triggered, done
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_handoffs');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('workspaces');
    }
};
