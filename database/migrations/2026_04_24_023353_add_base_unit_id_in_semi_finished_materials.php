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
        if (!Schema::hasColumn('semi_finished_materials', 'base_unit_id')) {
            Schema::table('semi_finished_materials', function (Blueprint $table) {
                $table->foreignId('base_unit_id')->nullable()->constrained('m_units');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('semi_finished_materials', 'base_unit_id')) {
            Schema::table('semi_finished_materials', function (Blueprint $table) {
                $table->dropForeign(['base_unit_id']);
                $table->dropColumn('base_unit_id');
            });
        }
    }
};
