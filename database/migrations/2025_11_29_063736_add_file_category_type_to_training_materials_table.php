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
        Schema::table('training_materials', function (Blueprint $table) {
            $table->enum('file_category_type', ['handout', 'document', 'manual'])->default('document')->after('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_materials', function (Blueprint $table) {
            $table->dropColumn('file_category_type');
        });
    }
};
