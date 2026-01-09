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
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_file_name');
            $table->string('video_file_path');
            $table->string('video_file_type');
            $table->bigInteger('video_file_size');
            $table->integer('duration_seconds')->nullable(); // Video duration in seconds
            $table->enum('category', ['user_manual', 'quality_procedure', 'tutorial'])->default('tutorial');
            $table->integer('total_views')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutorials');
    }
};
