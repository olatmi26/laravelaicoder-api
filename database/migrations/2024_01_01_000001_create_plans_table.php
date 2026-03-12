<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // "Pro", "Studio"
            $table->string('slug')->unique();              // "pro", "studio"
            $table->integer('price_monthly')->default(0); // cents: 2900 = $29
            $table->integer('price_yearly')->default(0);  // cents: 1900 = $19/mo
            $table->json('limits')->nullable();            // {"projects":5,"generations":-1,"agents":8,"storage_gb":10}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default plans
        DB::table('plans')->insert([
            [
                'name'           => 'Starter',
                'slug'           => 'starter',
                'price_monthly'  => 0,
                'price_yearly'   => 0,
                'limits'         => json_encode([
                    'projects'        => 1,
                    'generations'     => 100,
                    'agents'          => 3,
                    'storage_gb'      => 1,
                    'mobile'          => false,
                    'custom_ui'       => false,
                    'multi_workspace' => false,
                    'byok'            => false,
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'          => 'Pro',
                'slug'          => 'pro',
                'price_monthly' => 2900,
                'price_yearly'  => 1900,
                'limits'        => json_encode([
                    'projects'        => 5,
                    'generations'     => -1,  // unlimited
                    'agents'          => 8,
                    'storage_gb'      => 10,
                    'mobile'          => true,
                    'custom_ui'       => true,
                    'multi_workspace' => true,
                    'byok'            => true,
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'          => 'Studio',
                'slug'          => 'studio',
                'price_monthly' => 7900,
                'price_yearly'  => 5400,
                'limits'        => json_encode([
                    'projects'        => -1,
                    'generations'     => -1,
                    'agents'          => 8,
                    'storage_gb'      => 50,
                    'team_seats'      => 5,
                    'mobile'          => true,
                    'custom_ui'       => true,
                    'multi_workspace' => true,
                    'byok'            => true,
                    'white_label'     => true,
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
