<?php
// database/migrations/2026_05_26_000001_add_type_to_m_materials_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_materials', function (Blueprint $table) {
            $table->enum('type', ['normal', 'selectable'])->default('normal')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('m_materials', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};