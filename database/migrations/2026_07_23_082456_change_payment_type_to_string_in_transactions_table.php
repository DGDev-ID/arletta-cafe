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
            DB::statement('ALTER TABLE transactions ALTER COLUMN payment_type TYPE character varying(255)');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE transactions MODIFY payment_type VARCHAR(255) NOT NULL');
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('payment_type')->change();
            });
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
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_type_check CHECK (payment_type::text = ANY (ARRAY['manual'::character varying, 'qris'::character varying, 'debit'::character varying, 'third_party'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_type ENUM('manual', 'qris', 'debit', 'third_party') NOT NULL");
        }
    }
};
