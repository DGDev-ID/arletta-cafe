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
        Schema::create('cafe_promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('m_cafes')->cascadeOnDelete();
            $table->string('promo_code');
            $table->enum('type', ['discount_percent', 'discount_amount']);
            $table->decimal('value', 15, 2);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cafe_promos');
    }
};
