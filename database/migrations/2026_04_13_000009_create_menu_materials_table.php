<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('m_menus')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('m_materials')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('unit_id')->constrained('m_units')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_materials');
    }
};
