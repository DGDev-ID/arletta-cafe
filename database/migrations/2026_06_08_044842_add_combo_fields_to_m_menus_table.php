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
        Schema::table('m_menus', function (Blueprint $table) {
            $table->boolean('is_combo')->default(false)->after('status');
            $table->time('start_time')->nullable()->after('is_combo');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_menus', function (Blueprint $table) {
            $table->dropColumn(['is_combo', 'start_time', 'end_time']);
        });
    }
};
