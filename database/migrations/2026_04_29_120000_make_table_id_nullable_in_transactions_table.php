<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        if (!Schema::hasColumn('transactions', 'table_id')) {
            return;
        }

        try {
            $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                // Drop existing FK if present
                try {
                    DB::statement('ALTER TABLE `transactions` DROP FOREIGN KEY `transactions_table_id_foreign`');
                } catch (\Throwable $e) {
                    // ignore
                }

                // Modify column to allow NULL
                DB::statement('ALTER TABLE `transactions` MODIFY `table_id` BIGINT UNSIGNED NULL');

                // Re-add FK with ON DELETE SET NULL
                try {
                    DB::statement('ALTER TABLE `transactions` ADD CONSTRAINT `transactions_table_id_foreign` FOREIGN KEY (`table_id`) REFERENCES `m_cafe_tables`(`id`) ON DELETE SET NULL');
                } catch (\Throwable $e) {
                    // ignore
                }
            } elseif ($driver === 'pgsql') {
                try {
                    DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_table_id_foreign');
                } catch (\Throwable $e) {
                }
                DB::statement('ALTER TABLE transactions ALTER COLUMN table_id DROP NOT NULL');
                try {
                    DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_table_id_foreign FOREIGN KEY (table_id) REFERENCES m_cafe_tables(id) ON DELETE SET NULL');
                } catch (\Throwable $e) {
                }
            } else {
                // Fallback: use schema change (requires doctrine/dbal)
                Schema::table('transactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('table_id')->nullable()->change();
                });
            }
        } catch (\Throwable $e) {
            // ignore failures to keep migration safe
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        if (!Schema::hasColumn('transactions', 'table_id')) {
            return;
        }

        try {
            $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                try {
                    DB::statement('ALTER TABLE `transactions` DROP FOREIGN KEY `transactions_table_id_foreign`');
                } catch (\Throwable $e) {
                }

                DB::statement('ALTER TABLE `transactions` MODIFY `table_id` BIGINT UNSIGNED NOT NULL');

                try {
                    DB::statement('ALTER TABLE `transactions` ADD CONSTRAINT `transactions_table_id_foreign` FOREIGN KEY (`table_id`) REFERENCES `m_cafe_tables`(`id`) ON DELETE CASCADE');
                } catch (\Throwable $e) {
                }
            } elseif ($driver === 'pgsql') {
                try {
                    DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_table_id_foreign');
                } catch (\Throwable $e) {
                }
                DB::statement('ALTER TABLE transactions ALTER COLUMN table_id SET NOT NULL');
                try {
                    DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_table_id_foreign FOREIGN KEY (table_id) REFERENCES m_cafe_tables(id) ON DELETE CASCADE');
                } catch (\Throwable $e) {
                }
            } else {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('table_id')->nullable(false)->change();
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
