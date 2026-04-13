<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_inbound_outbounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('m_materials')->cascadeOnDelete();
            $table->enum('type', ['inbound', 'outbound']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('base_unit_id')->constrained('m_units')->restrictOnDelete();
            $table->foreignId('transaction_detail_id')->nullable()->constrained('transaction_details')->nullOnDelete();
            $table->decimal('inbound_buy_price', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_inbound_outbounds');
    }
};
