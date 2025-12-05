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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->text('question_text');
            $table->enum('question_type', ['multiple_choice', 'checkbox', 'identification']);
            $table->decimal('points', 8, 2)->default(1.00);
            $table->text('explanation')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->text('correct_answer')->nullable(); // For identification questions
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            // Add indexes
            $table->index(['course_id', 'is_active', 'order']);
            $table->index(['question_type']);
            $table->index(['difficulty']);
            $table->index(['created_by_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
