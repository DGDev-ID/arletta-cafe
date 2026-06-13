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
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_payment_type_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_type_check CHECK (payment_type::text = ANY (ARRAY['manual'::character varying, 'qris'::character varying, 'debit'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_type ENUM('manual', 'qris', 'debit') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_payment_type_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_type_check CHECK (payment_type::text = ANY (ARRAY['manual'::character varying, 'qris'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_type ENUM('manual', 'qris') NOT NULL");
        }
    }
};
