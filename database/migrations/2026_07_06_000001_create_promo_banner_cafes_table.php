<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel pivot untuk relasi many-to-many antara promo_banners dan m_cafes.
     * Banner hanya tampil di cafe yang terdaftar di tabel ini.
     */
    public function up(): void
    {
        Schema::create('promo_banner_cafes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_banner_id')
                ->constrained('promo_banners')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('cafe_id');
            $table->foreign('cafe_id')
                ->references('id')
                ->on('m_cafes')
                ->cascadeOnDelete();

            $table->unique(['promo_banner_id', 'cafe_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_banner_cafes');
    }
};
