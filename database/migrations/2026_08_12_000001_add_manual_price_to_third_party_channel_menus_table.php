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
        Schema::table('third_party_channel_menus', function (Blueprint $table) {
            $table->boolean('is_manual_price')->default(false)->after('admin_fee');
            $table->decimal('override_price', 15, 2)->nullable()->after('is_manual_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('third_party_channel_menus', function (Blueprint $table) {
            $table->dropColumn(['is_manual_price', 'override_price']);
        });
    }
};
