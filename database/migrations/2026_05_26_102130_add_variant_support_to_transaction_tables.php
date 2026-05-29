<?php
// database/migrations/2026_05_26_000003_add_variant_support_to_transaction_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Simpan pilihan variant user per detail transaksi
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->json('selected_variants')->nullable()->after('description');
        });

        // Track apakah outbound ini untuk variant
        Schema::table('material_inbound_outbounds', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('material_id')
                ->constrained('material_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropColumn('selected_variants');
        });
        Schema::table('material_inbound_outbounds', function (Blueprint $table) {
            $table->dropColumn('variant_id');
        });
    }
};