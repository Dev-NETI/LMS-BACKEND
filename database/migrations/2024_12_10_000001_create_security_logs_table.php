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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainee_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('attempt_id')->nullable();
            $table->string('activity');
            $table->string('event_type')->index(); // 'tab_switch', 'right_click_blocked', etc.
            $table->string('severity')->default('low'); // 'low', 'medium', 'high'
            $table->ipAddress('ip_address');
            $table->text('user_agent');
            $table->json('additional_data')->nullable(); // For extra context
            $table->timestamp('event_timestamp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
