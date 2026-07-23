<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_payment_type_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_type_check CHECK (payment_type::text = ANY (ARRAY['manual'::character varying, 'qris'::character varying, 'debit'::character varying, 'third_party'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_type ENUM('manual', 'qris', 'debit', 'third_party') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_payment_type_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_type_check CHECK (payment_type::text = ANY (ARRAY['manual'::character varying, 'qris'::character varying, 'debit'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_type ENUM('manual', 'qris', 'debit') NOT NULL");
        }
    }
};
