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
        Schema::create('trainee_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainee_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('course_content_id');
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->integer('time_spent')->default(0); // in minutes
            $table->decimal('completion_percentage', 5, 2)->default(0.00); // 0.00 to 100.00
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->json('activity_log')->nullable(); // Track detailed activities
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['trainee_id', 'course_id']);
            $table->index(['trainee_id', 'course_content_id']);
            $table->index(['course_id', 'status']);
            $table->index('last_activity');
            
            // Unique constraint to prevent duplicate progress entries
            $table->unique(['trainee_id', 'course_content_id']);
            
            // Foreign key constraints (commented out as they reference different databases)
            // $table->foreign('trainee_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('course_id')->references('courseid')->on('main_db.tblcourses')->onDelete('cascade');
            // $table->foreign('course_content_id')->references('id')->on('course_content')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainee_progress');
    }
};
