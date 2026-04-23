<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semi_finished_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('m_cafes')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semi_finished_materials');
    }
};
