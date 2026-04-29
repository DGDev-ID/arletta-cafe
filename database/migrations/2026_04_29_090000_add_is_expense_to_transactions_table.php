<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->tinyInteger('is_expense')->unsigned()->default(0)->comment('0 = income, 1 = expense');
        });

        // Add a CHECK constraint where supported to enforce only 0 or 1
        try {
            $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `transactions` ADD CONSTRAINT chk_transactions_is_expense CHECK (`is_expense` IN (0,1))");
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE "transactions" ADD CONSTRAINT chk_transactions_is_expense CHECK (is_expense IN (0,1))');
            } else {
                DB::statement("ALTER TABLE transactions ADD CONSTRAINT chk_transactions_is_expense CHECK (is_expense IN (0,1))");
            }
        } catch (\Throwable $e) {
            // ignore if the DB engine doesn't support CHECK constraints
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Try to drop the check constraint if it exists
        try {
            $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `transactions` DROP CHECK chk_transactions_is_expense");
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE "transactions" DROP CONSTRAINT IF EXISTS chk_transactions_is_expense');
            } else {
                DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS chk_transactions_is_expense");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'is_expense')) {
                $table->dropColumn('is_expense');
            }
        });
    }
};
