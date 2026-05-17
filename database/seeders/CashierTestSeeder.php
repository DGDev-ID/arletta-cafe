<?php

namespace Database\Seeders;

use App\Models\CafeCashier;
use App\Models\CafePromo;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\MMenu;
use App\Models\MMenuCategory;
use App\Models\MUnit;
use App\Models\MMaterial;
use App\Models\MenuMaterial;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CashierTestSeeder extends Seeder
{
    /**
     * Seed data khusus untuk testing fitur transaction/cashier.
     *
     * Apa yang dibuat:
     *  - User Cashier terhubung ke cafe (CafeCashier)
     *  - CafePromo: discount persen & nominal
     *  - Transaksi pending manual (bukan open-bill)
     *  - Transaksi pending open-bill dengan detail campuran (success & pending)
     *  - Transaksi in_order
     *  - Transaksi success (dalam 26 jam terakhir)
     *  - Transaksi dengan promo
     *
     * Jalankan setelah MCafeSeeder + UserSeeder + RolePermissionSeeder:
     *   php artisan db:seed --class=CashierTestSeeder
     */
    public function run(): void
    {
        // ─── 1. Ambil/buat cafe ───────────────────────────────────────────────
        $cafe = MCafe::first();

        if (!$cafe) {
            $cafe = MCafe::create([
                'unique_id'   => 'CAFE-TEST',
                'name'        => 'Arletta Test Cafe',
                'address'     => 'Jl. Test No. 1',
                'ppn_fee'     => 11,
                'qris_fee'    => 0.7,
            ]);
        }

        // ─── 2. Pastikan unit & material tersedia ─────────────────────────────
        $unitGram = MUnit::firstOrCreate(['name' => 'gram']);
        $unitMl   = MUnit::firstOrCreate(['name' => 'mililiter']);
        $unitPcs  = MUnit::firstOrCreate(['name' => 'pcs']);

        // ─── 3. Pastikan tabel/meja tersedia ─────────────────────────────────
        $tableNames = ['Meja A', 'Meja B', 'Meja C', 'Meja D'];
        $tables = collect($tableNames)->map(
            fn ($name) => MCafeTable::firstOrCreate(
                ['cafe_id' => $cafe->id, 'name' => $name],
                ['status' => 'available', 'description' => "Meja uji coba cashier - $name"]
            )
        );

        // ─── 4. Pastikan kategori & menu tersedia ────────────────────────────
        $catMinuman = MMenuCategory::firstOrCreate(
            ['cafe_id' => $cafe->id, 'name' => 'Minuman'],
            ['description' => 'Semua jenis minuman']
        );
        $catMakanan = MMenuCategory::firstOrCreate(
            ['cafe_id' => $cafe->id, 'name' => 'Makanan'],
            ['description' => 'Semua jenis makanan']
        );

        $menuDefs = [
            ['name' => 'Americano',          'price' => 22000, 'cat' => $catMinuman->id],
            ['name' => 'Cafe Latte',          'price' => 28000, 'cat' => $catMinuman->id],
            ['name' => 'Matcha Latte',        'price' => 30000, 'cat' => $catMinuman->id],
            ['name' => 'Es Teh Manis',        'price' => 10000, 'cat' => $catMinuman->id],
            ['name' => 'Nasi Goreng Spesial', 'price' => 35000, 'cat' => $catMakanan->id],
            ['name' => 'French Fries',        'price' => 20000, 'cat' => $catMakanan->id],
            ['name' => 'Cappuccino',          'price' => 26000, 'cat' => $catMinuman->id],
            ['name' => 'Roti Bakar Coklat',  'price' => 18000, 'cat' => $catMakanan->id],
        ];

        $menus = collect($menuDefs)->map(
            fn ($m) => MMenu::firstOrCreate(
                ['cafe_id' => $cafe->id, 'name' => $m['name']],
                ['price' => $m['price'], 'menu_category_id' => $m['cat'], 'status' => 'available']
            )
        );

        // Material sederhana untuk resep (agar outbound bisa dicatat)
        $matKopi = MMaterial::firstOrCreate(
            ['cafe_id' => $cafe->id, 'name' => 'Biji Kopi Arabica'],
            ['base_unit_id' => $unitGram->id, 'stock' => 9999, 'avg_buy_price' => 150]
        );
        $matSusu = MMaterial::firstOrCreate(
            ['cafe_id' => $cafe->id, 'name' => 'Susu Full Cream'],
            ['base_unit_id' => $unitMl->id,   'stock' => 99999, 'avg_buy_price' => 18]
        );

        // Resep sederhana (idempoten)
        foreach ([
            ['menu_id' => $menus[0]->id, 'material_id' => $matKopi->id, 'amount' => 18, 'unit_id' => $unitGram->id],
            ['menu_id' => $menus[1]->id, 'material_id' => $matKopi->id, 'amount' => 18, 'unit_id' => $unitGram->id],
            ['menu_id' => $menus[1]->id, 'material_id' => $matSusu->id, 'amount' => 200, 'unit_id' => $unitMl->id],
            ['menu_id' => $menus[6]->id, 'material_id' => $matKopi->id, 'amount' => 18, 'unit_id' => $unitGram->id],
            ['menu_id' => $menus[6]->id, 'material_id' => $matSusu->id, 'amount' => 150, 'unit_id' => $unitMl->id],
        ] as $mm) {
            MenuMaterial::firstOrCreate(
                ['menu_id' => $mm['menu_id'], 'material_id' => $mm['material_id']],
                ['amount' => $mm['amount'], 'unit_id' => $mm['unit_id']]
            );
        }

        // ─── 5. User Cashier terhubung ke cafe ───────────────────────────────
        $cashierRole = Role::firstOrCreate(['name' => 'Cashier']);

        $cashierUser = User::firstOrCreate(
            ['email' => 'cashier@arletta.com'],
            ['name' => 'Cashier Test', 'password' => bcrypt('password')]
        );
        if (!$cashierUser->hasRole('Cashier')) {
            $cashierUser->assignRole($cashierRole);
        }

        CafeCashier::firstOrCreate(
            ['cafe_id' => $cafe->id, 'user_id' => $cashierUser->id]
        );

        // ─── 6. CafePromo ────────────────────────────────────────────────────
        $promoDiscount = CafePromo::firstOrCreate(
            ['cafe_id' => $cafe->id, 'promo_code' => 'HEMAT10'],
            ['type' => 'discount_percent', 'value' => 10, 'status' => true]
        );
        $promoFlat = CafePromo::firstOrCreate(
            ['cafe_id' => $cafe->id, 'promo_code' => 'FLAT5K'],
            ['type' => 'discount_amount', 'value' => 5000, 'status' => true]
        );

        // ─── 7. Helper buat transaksi + detail ───────────────────────────────
        $makeTransaction = function (
            array $items,
            MCafeTable $table,
            string $paymentType,
            string $status,
            bool $isOpenBill = false,
            ?int $promoId = null,
            string $custName = 'Pelanggan'
        ) use ($cafe): Transaction {
            $subtotal = 0;
            foreach ($items as ['menu' => $menu, 'qty' => $qty]) {
                $subtotal += $menu->price * $qty;
            }

            $discount = 0;
            if ($promoId) {
                $promo = CafePromo::find($promoId);
                if ($promo) {
                    $discount = $promo->type === 'discount_percent'
                        ? round($subtotal * ($promo->value / 100), 2)
                        : (float) $promo->value;
                }
            }

            $priceAfterDiscount = max(0, $subtotal - $discount);
            $fee    = round($priceAfterDiscount * 0.05, 2);
            $total  = $priceAfterDiscount + $fee;

            return Transaction::create([
                'cafe_id'      => $cafe->id,
                'table_id'     => $table->id,
                'cust_name'    => $custName,
                'price'        => $priceAfterDiscount,
                'fee'          => $fee,
                'total_price'  => $total,
                'payment_type' => $paymentType,
                'status'       => $status,
                'is_open_bill' => $isOpenBill ? 1 : 0,
                'promo_id'     => $promoId,
            ]);
        };

        $addDetails = function (Transaction $trx, array $items, ?string $forceStatus = null): void {
            foreach ($items as ['menu' => $menu, 'qty' => $qty, 'detailStatus' => $ds]) {
                TransactionDetail::create([
                    'transaction_id' => $trx->id,
                    'menu_id'        => $menu->id,
                    'amount'         => $qty,
                    'price'          => $menu->price * $qty,
                    'status'         => $forceStatus ?? $ds,
                ]);
            }
        };

        // ─── 8. Transaksi PENDING manual (bukan open-bill) ───────────────────
        // Skenario: pesanan sudah dibuat, menunggu pembayaran tunai kasir
        $trxPending1 = $makeTransaction(
            [['menu' => $menus[0], 'qty' => 2], ['menu' => $menus[5], 'qty' => 1]],
            $tables[0], 'manual', 'pending', false, null, 'Budi'
        );
        $addDetails($trxPending1, [
            ['menu' => $menus[0], 'qty' => 2, 'detailStatus' => 'pending'],
            ['menu' => $menus[5], 'qty' => 1, 'detailStatus' => 'pending'],
        ]);

        $trxPending2 = $makeTransaction(
            [['menu' => $menus[6], 'qty' => 1], ['menu' => $menus[4], 'qty' => 1]],
            $tables[1], 'manual', 'pending', false, null, 'Siti'
        );
        $addDetails($trxPending2, [
            ['menu' => $menus[6], 'qty' => 1, 'detailStatus' => 'pending'],
            ['menu' => $menus[4], 'qty' => 1, 'detailStatus' => 'pending'],
        ]);

        // Pending dengan promo
        $trxPendingPromo = $makeTransaction(
            [['menu' => $menus[1], 'qty' => 2], ['menu' => $menus[7], 'qty' => 1]],
            $tables[2], 'manual', 'pending', false, $promoDiscount->id, 'Andi'
        );
        $addDetails($trxPendingPromo, [
            ['menu' => $menus[1], 'qty' => 2, 'detailStatus' => 'pending'],
            ['menu' => $menus[7], 'qty' => 1, 'detailStatus' => 'pending'],
        ]);

        // ─── 9. Transaksi OPEN-BILL (status=pending, is_open_bill=1) ─────────
        // Skenario: meja masih buka, pesanan datang bertahap

        // Open-bill 1: semua detail masih pending (belum disiapkan)
        $trxOB1 = $makeTransaction(
            [['menu' => $menus[0], 'qty' => 1], ['menu' => $menus[4], 'qty' => 1]],
            $tables[0], 'manual', 'pending', true, null, 'Meja A - Open'
        );
        $addDetails($trxOB1, [
            ['menu' => $menus[0], 'qty' => 1, 'detailStatus' => 'pending'],
            ['menu' => $menus[4], 'qty' => 1, 'detailStatus' => 'pending'],
        ]);

        // Open-bill 2: sebagian detail sudah success (sudah disiapkan/diserahkan)
        $trxOB2 = $makeTransaction(
            [['menu' => $menus[6], 'qty' => 2], ['menu' => $menus[3], 'qty' => 3], ['menu' => $menus[5], 'qty' => 1]],
            $tables[1], 'manual', 'pending', true, null, 'Meja B - Open'
        );
        $addDetails($trxOB2, [
            ['menu' => $menus[6], 'qty' => 2, 'detailStatus' => 'success'],  // sudah disiapkan
            ['menu' => $menus[3], 'qty' => 3, 'detailStatus' => 'success'],  // sudah disiapkan
            ['menu' => $menus[5], 'qty' => 1, 'detailStatus' => 'pending'],  // belum disiapkan
        ]);

        // Open-bill 3: dengan promo, beberapa sudah selesai
        $trxOB3 = $makeTransaction(
            [['menu' => $menus[2], 'qty' => 2], ['menu' => $menus[7], 'qty' => 2]],
            $tables[2], 'qris', 'pending', true, $promoFlat->id, 'Meja C - Open'
        );
        $addDetails($trxOB3, [
            ['menu' => $menus[2], 'qty' => 2, 'detailStatus' => 'pending'],
            ['menu' => $menus[7], 'qty' => 2, 'detailStatus' => 'success'],
        ]);

        // ─── 10. Transaksi IN_ORDER ───────────────────────────────────────────
        // Skenario: pesanan sedang diproses dapur/barista
        $trxInOrder1 = $makeTransaction(
            [['menu' => $menus[1], 'qty' => 1], ['menu' => $menus[5], 'qty' => 2]],
            $tables[3], 'manual', 'in_order', false, null, 'Rudi'
        );
        $addDetails($trxInOrder1, [
            ['menu' => $menus[1], 'qty' => 1, 'detailStatus' => 'pending'],
            ['menu' => $menus[5], 'qty' => 2, 'detailStatus' => 'pending'],
        ]);

        $trxInOrder2 = $makeTransaction(
            [['menu' => $menus[0], 'qty' => 1], ['menu' => $menus[4], 'qty' => 1], ['menu' => $menus[3], 'qty' => 2]],
            $tables[0], 'qris', 'in_order', false, null, 'Dewi'
        );
        $addDetails($trxInOrder2, [
            ['menu' => $menus[0], 'qty' => 1, 'detailStatus' => 'pending'],
            ['menu' => $menus[4], 'qty' => 1, 'detailStatus' => 'pending'],
            ['menu' => $menus[3], 'qty' => 2, 'detailStatus' => 'pending'],
        ]);

        // ─── 11. Transaksi SUCCESS (dalam 26 jam terakhir) ───────────────────
        $trxSuccess1 = $makeTransaction(
            [['menu' => $menus[0], 'qty' => 2], ['menu' => $menus[7], 'qty' => 1]],
            $tables[1], 'manual', 'success', false, null, 'Hana'
        );
        $addDetails($trxSuccess1, [
            ['menu' => $menus[0], 'qty' => 2, 'detailStatus' => 'success'],
            ['menu' => $menus[7], 'qty' => 1, 'detailStatus' => 'success'],
        ]);

        $trxSuccess2 = $makeTransaction(
            [['menu' => $menus[6], 'qty' => 1], ['menu' => $menus[2], 'qty' => 1]],
            $tables[2], 'qris', 'success', false, null, 'Tono'
        );
        $addDetails($trxSuccess2, [
            ['menu' => $menus[6], 'qty' => 1, 'detailStatus' => 'success'],
            ['menu' => $menus[2], 'qty' => 1, 'detailStatus' => 'success'],
        ]);

        $trxSuccess3 = $makeTransaction(
            [['menu' => $menus[4], 'qty' => 1], ['menu' => $menus[3], 'qty' => 2], ['menu' => $menus[5], 'qty' => 1]],
            $tables[3], 'manual', 'success', false, $promoDiscount->id, 'Lina'
        );
        $addDetails($trxSuccess3, [
            ['menu' => $menus[4], 'qty' => 1, 'detailStatus' => 'success'],
            ['menu' => $menus[3], 'qty' => 2, 'detailStatus' => 'success'],
            ['menu' => $menus[5], 'qty' => 1, 'detailStatus' => 'success'],
        ]);

        $this->command->info('CashierTestSeeder selesai.');
        $this->command->table(
            ['Tipe', 'Detail'],
            [
                ['Login Cashier', 'cashier@arletta.com / password'],
                ['Cafe',          $cafe->name . ' (ID: ' . $cafe->id . ')'],
                ['Promo 1',       'HEMAT10 — diskon 10%'],
                ['Promo 2',       'FLAT5K  — diskon Rp5.000'],
                ['Pending manual', '3 transaksi (termasuk 1 dengan promo)'],
                ['Open-bill',      '3 transaksi (detail campuran success/pending)'],
                ['In-order',       '2 transaksi'],
                ['Success',        '3 transaksi (dalam 26 jam terakhir)'],
            ]
        );
    }
}