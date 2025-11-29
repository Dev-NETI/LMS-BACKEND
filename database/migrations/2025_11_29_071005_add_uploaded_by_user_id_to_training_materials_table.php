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
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->after('is_active');
            $table->index('uploaded_by_user_id');
            
            // Add foreign key constraint if users table exists
            // Uncomment the line below if you want to enforce referential integrity
            // $table->foreign('uploaded_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_materials', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by_user_id']);
            $table->dropColumn('uploaded_by_user_id');
        });
    }
};
