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
        if (! Schema::hasColumn('transactions', 'profit_margin')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('profit_margin', 8, 2)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'profit_margin')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('profit_margin');
            });
        }
    }
};
