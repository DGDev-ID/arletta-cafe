<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('third_party_channel_id')
                ->nullable()
                ->after('promo_id')
                ->constrained('third_party_channels')
                ->nullOnDelete();
            $table->decimal('admin_fee', 12, 2)->default(0)->after('fee');
            $table->string('third_party_reference')->nullable()->after('admin_fee');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['third_party_channel_id']);
            $table->dropColumn(['third_party_channel_id', 'admin_fee', 'third_party_reference']);
        });
    }
};
