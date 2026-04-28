<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_cafe_tables', function (Blueprint $table) {
            $table->integer('is_open_bill')->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('m_cafe_tables', function (Blueprint $table) {
            $table->dropColumn('is_open_bill');
        });
    }
};
