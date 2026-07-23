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
        Schema::create('third_party_channel_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('third_party_channel_id')->constrained('third_party_channels')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('m_menus')->cascadeOnDelete();
            $table->decimal('admin_fee', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['third_party_channel_id', 'menu_id'], 'channel_menu_unique');
        });

        Schema::table('third_party_channels', function (Blueprint $table) {
            $table->dropColumn('admin_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('third_party_channels', function (Blueprint $table) {
            $table->decimal('admin_fee', 15, 2)->default(0)->after('name');
        });

        Schema::dropIfExists('third_party_channel_menus');
    }
};
