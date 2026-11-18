<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_databases', function (Blueprint $table) {
            $table->text('url')->nullable()->change();
            $table->string('status', 30)->default('completed')->after('url');
            $table->unsignedTinyInteger('progress')->default(100)->after('status');
            $table->string('progress_message')->nullable()->after('progress');
            $table->text('error_message')->nullable()->after('progress_message');
        });
    }

    public function down(): void
    {
        Schema::table('backup_databases', function (Blueprint $table) {
            $table->dropColumn(['status', 'progress', 'progress_message', 'error_message']);
        });
    }
};
