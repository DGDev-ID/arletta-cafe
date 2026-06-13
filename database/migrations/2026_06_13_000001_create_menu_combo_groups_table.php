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
        Schema::create('menu_combo_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('m_menus')->cascadeOnDelete();
            $table->string('label');         // e.g. "Pilih Minuman", "Pilih Makanan"
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_combo_groups');
    }
};
