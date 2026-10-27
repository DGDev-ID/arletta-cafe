<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balance_id')->constrained('balances')->cascadeOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_logs');
    }
};
