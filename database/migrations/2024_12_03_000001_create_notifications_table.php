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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['admin', 'trainee'])->default('trainee');
            $table->enum('type', ['announcement', 'reply', 'general'])->default('general');
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('related_id')->nullable(); // announcement_id or other related entity
            $table->unsignedBigInteger('schedule_id')->nullable(); // schedule_id for direct navigation
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'user_type']);
            $table->index(['user_id', 'is_read']);
            $table->index(['type', 'related_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};