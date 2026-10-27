<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balance_id')->constrained('balances')->cascadeOnDelete();
            $table->decimal('topup_amount', 15, 2);
            $table->decimal('unique_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->unsignedBigInteger('qris_history_id')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_topups');
    }
};
