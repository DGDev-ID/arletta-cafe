<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_semi_finished_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('m_menus')->cascadeOnDelete();
            $table->foreignId('semi_finished_material_id')->constrained('semi_finished_materials')->cascadeOnDelete();
            $table->decimal('multiplier', 8, 2)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_semi_finished_materials');
    }
};
