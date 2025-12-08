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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->integer('time_limit')->comment('Time limit in minutes');
            $table->integer('max_attempts')->default(1);
            $table->decimal('passing_score', 5, 2)->default(70.00);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_randomized')->default(false);
            $table->boolean('show_results_immediately')->default(true);
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();
            $table->index(['course_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
