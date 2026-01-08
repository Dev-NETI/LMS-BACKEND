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
        Schema::table('announcement_replies', function (Blueprint $table) {
            $table->enum('user_type', ['admin', 'trainee', 'instructor'])->default('admin')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcement_replies', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
