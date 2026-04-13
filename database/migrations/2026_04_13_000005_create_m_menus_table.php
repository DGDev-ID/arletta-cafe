<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('m_cafes')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('img_url')->nullable();
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_menus');
    }
};
