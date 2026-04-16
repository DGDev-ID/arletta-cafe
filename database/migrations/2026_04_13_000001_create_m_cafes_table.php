<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_cafes', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id')->unique();
            $table->string('name');
            $table->text('address');
            $table->string('address_coordinate')->nullable();
            $table->text('description')->nullable();
            $table->string('img_url')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('ppn_fee', 5, 2)->default(0);
            $table->decimal('qris_fee', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_cafes');
    }
};
