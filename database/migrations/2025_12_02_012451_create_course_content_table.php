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
        Schema::create('course_content', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('content_type', ['file', 'url']);
            $table->enum('file_type', ['articulate_html', 'pdf', 'link'])->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();
            
            $table->index(['course_id', 'is_active']);
            $table->index('uploaded_by_user_id');
            $table->index(['course_id', 'content_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_content');
    }
};
