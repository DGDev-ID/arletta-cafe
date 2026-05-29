<?php
// database/migrations/2026_05_26_000002_create_material_variants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('m_materials')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('minimum_stock', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_variants');
    }
};