<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained('plans')->nullOnDelete();
            $table->string('avatar')->nullable()->after('email');
            $table->string('flw_customer_id')->nullable()->after('avatar'); // Flutterwave customer ID
            $table->timestamp('trial_ends_at')->nullable()->after('flw_customer_id');
            $table->boolean('email_verified')->default(false)->after('trial_ends_at');
        });

        // Set all existing users to Starter plan (plan id=1)
        DB::table('users')->whereNull('plan_id')->update(['plan_id' => 1]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'avatar', 'flw_customer_id', 'trial_ends_at', 'email_verified']);
        });
    }
};
