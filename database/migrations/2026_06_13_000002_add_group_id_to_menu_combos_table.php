<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds group_id to menu_combos so each combo item belongs to a group/slot.
     * Existing rows (if any) will have group_id = null (backward compatible).
     */
    public function up(): void
    {
        Schema::table('menu_combos', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('menu_id')
                ->constrained('menu_combo_groups')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_combos', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
