<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('unique_code')->unique()->nullable();
            $table->foreignId('cafe_id')->constrained('m_cafes')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('m_cafe_tables')->cascadeOnDelete();
            $table->string('cust_name')->default('Customer');
            $table->decimal('price', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2);
            $table->enum('payment_type', ['manual', 'qris']);
            $table->enum('status', ['pending', 'failed', 'success', 'in_order']);
            $table->string('snap_token')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
