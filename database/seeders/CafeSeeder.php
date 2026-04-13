<?php

namespace Database\Seeders;

use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\Material;
use App\Models\MaterialInboundOutbound;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuMaterial;
use App\Models\MenuPromo;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Unit;
use App\Models\UnitMaterialConverter;
use Illuminate\Database\Seeder;

class CafeSeeder extends Seeder
{
    public function run(): void
    {
        // Units
        $units = collect([
            'gram', 'kilogram', 'mililiter', 'liter', 'pcs', 'sendok', 'sachet',
        ])->map(fn ($name) => Unit::create(['name' => $name]));

        $unitMap = $units->keyBy('name');

        // Cafe
        $cafe = Cafe::create([
            'unique_id' => 'CAFE-001',
            'name' => 'Arletta Coffee & Eatery',
            'address' => 'Jl. Sudirman No. 12, Jakarta Selatan',
            'address_coordinate' => '-6.2088,106.8456',
            'description' => 'Cafe modern dengan suasana cozy dan menu kopi spesialti.',
        ]);

        // Tables
        $tables = collect([
            ['name' => 'Meja 1', 'status' => 'available', 'description' => 'Meja outdoor 2 kursi'],
            ['name' => 'Meja 2', 'status' => 'available', 'description' => 'Meja indoor 4 kursi'],
            ['name' => 'Meja 3', 'status' => 'occupied', 'description' => 'Meja VIP sofa'],
            ['name' => 'Meja 4', 'status' => 'available', 'description' => 'Meja bar counter'],
            ['name' => 'Meja 5', 'status' => 'available', 'description' => 'Meja teras lantai 2'],
        ])->map(fn ($t) => CafeTable::create(array_merge($t, ['cafe_id' => $cafe->id])));

        // Menu Categories
        $catMinuman = MenuCategory::create(['cafe_id' => $cafe->id, 'name' => 'Minuman', 'description' => 'Semua jenis minuman']);
        $catKopi = MenuCategory::create(['cafe_id' => $cafe->id, 'name' => 'Kopi', 'parent_id' => $catMinuman->id, 'description' => 'Menu berbasis kopi']);
        $catNonKopi = MenuCategory::create(['cafe_id' => $cafe->id, 'name' => 'Non-Kopi', 'parent_id' => $catMinuman->id, 'description' => 'Minuman tanpa kopi']);
        $catMakanan = MenuCategory::create(['cafe_id' => $cafe->id, 'name' => 'Makanan', 'description' => 'Semua jenis makanan']);
        $catSnack = MenuCategory::create(['cafe_id' => $cafe->id, 'name' => 'Snack', 'parent_id' => $catMakanan->id, 'description' => 'Cemilan ringan']);

        // Menus
        $menuData = [
            ['name' => 'Americano', 'description' => 'Espresso dengan air panas', 'price' => 22000],
            ['name' => 'Cafe Latte', 'description' => 'Espresso dengan susu steamed', 'price' => 28000],
            ['name' => 'Matcha Latte', 'description' => 'Green tea matcha dengan susu', 'price' => 30000],
            ['name' => 'Es Teh Manis', 'description' => 'Teh manis dingin segar', 'price' => 10000],
            ['name' => 'Nasi Goreng Spesial', 'description' => 'Nasi goreng dengan telur dan ayam', 'price' => 35000],
            ['name' => 'French Fries', 'description' => 'Kentang goreng crispy dengan saus', 'price' => 20000],
            ['name' => 'Cappuccino', 'description' => 'Espresso, susu steamed, dan foam', 'price' => 26000],
            ['name' => 'Roti Bakar Coklat', 'description' => 'Roti panggang dengan selai coklat', 'price' => 18000],
        ];

        $menus = collect($menuData)->map(fn ($m) => Menu::create(array_merge($m, ['cafe_id' => $cafe->id])));

        // Menu Promos (beberapa menu dapat promo)
        MenuPromo::create(['menu_id' => $menus[0]->id, 'type' => 'discount_percent', 'discount_amount' => 10]);
        MenuPromo::create(['menu_id' => $menus[4]->id, 'type' => 'discount_amount', 'discount_amount' => 5000]);
        MenuPromo::create(['menu_id' => $menus[2]->id, 'type' => 'discount_percent', 'discount_amount' => 15]);

        // Materials
        $materialData = [
            ['name' => 'Biji Kopi Arabica', 'base_unit_id' => $unitMap['gram']->id, 'stock' => 5000, 'avg_buy_price' => 150],
            ['name' => 'Susu Full Cream', 'base_unit_id' => $unitMap['mililiter']->id, 'stock' => 10000, 'avg_buy_price' => 18],
            ['name' => 'Gula Pasir', 'base_unit_id' => $unitMap['gram']->id, 'stock' => 3000, 'avg_buy_price' => 14],
            ['name' => 'Matcha Powder', 'base_unit_id' => $unitMap['gram']->id, 'stock' => 500, 'avg_buy_price' => 800],
            ['name' => 'Beras', 'base_unit_id' => $unitMap['gram']->id, 'stock' => 10000, 'avg_buy_price' => 12],
            ['name' => 'Teh Celup', 'base_unit_id' => $unitMap['sachet']->id, 'stock' => 200, 'avg_buy_price' => 500],
            ['name' => 'Kentang', 'base_unit_id' => $unitMap['gram']->id, 'stock' => 5000, 'avg_buy_price' => 25],
            ['name' => 'Roti Tawar', 'base_unit_id' => $unitMap['pcs']->id, 'stock' => 40, 'avg_buy_price' => 3000],
            ['name' => 'Selai Coklat', 'base_unit_id' => $unitMap['gram']->id, 'stock' => 1000, 'avg_buy_price' => 60],
            ['name' => 'Telur Ayam', 'base_unit_id' => $unitMap['pcs']->id, 'stock' => 100, 'avg_buy_price' => 2500],
        ];

        $materials = collect($materialData)->map(fn ($m) => Material::create(array_merge($m, ['cafe_id' => $cafe->id])));

        // Unit converters (contoh: gram -> kilogram)
        UnitMaterialConverter::create([
            'material_id' => $materials[0]->id, // Biji Kopi
            'from_unit_id' => $unitMap['kilogram']->id,
            'to_unit_id' => $unitMap['gram']->id,
            'multiplier' => 1000,
        ]);
        UnitMaterialConverter::create([
            'material_id' => $materials[1]->id, // Susu
            'from_unit_id' => $unitMap['liter']->id,
            'to_unit_id' => $unitMap['mililiter']->id,
            'multiplier' => 1000,
        ]);

        // Menu Materials (resep)
        $menuMaterialData = [
            // Americano: 18g kopi
            ['menu_id' => $menus[0]->id, 'material_id' => $materials[0]->id, 'amount' => 18, 'unit_id' => $unitMap['gram']->id],
            // Cafe Latte: 18g kopi + 200ml susu
            ['menu_id' => $menus[1]->id, 'material_id' => $materials[0]->id, 'amount' => 18, 'unit_id' => $unitMap['gram']->id],
            ['menu_id' => $menus[1]->id, 'material_id' => $materials[1]->id, 'amount' => 200, 'unit_id' => $unitMap['mililiter']->id],
            // Matcha Latte: 5g matcha + 250ml susu
            ['menu_id' => $menus[2]->id, 'material_id' => $materials[3]->id, 'amount' => 5, 'unit_id' => $unitMap['gram']->id],
            ['menu_id' => $menus[2]->id, 'material_id' => $materials[1]->id, 'amount' => 250, 'unit_id' => $unitMap['mililiter']->id],
            // Es Teh Manis: 1 sachet teh + 15g gula
            ['menu_id' => $menus[3]->id, 'material_id' => $materials[5]->id, 'amount' => 1, 'unit_id' => $unitMap['sachet']->id],
            ['menu_id' => $menus[3]->id, 'material_id' => $materials[2]->id, 'amount' => 15, 'unit_id' => $unitMap['gram']->id],
            // Nasi Goreng: 200g beras + 1 telur
            ['menu_id' => $menus[4]->id, 'material_id' => $materials[4]->id, 'amount' => 200, 'unit_id' => $unitMap['gram']->id],
            ['menu_id' => $menus[4]->id, 'material_id' => $materials[9]->id, 'amount' => 1, 'unit_id' => $unitMap['pcs']->id],
            // French Fries: 200g kentang
            ['menu_id' => $menus[5]->id, 'material_id' => $materials[6]->id, 'amount' => 200, 'unit_id' => $unitMap['gram']->id],
            // Cappuccino: 18g kopi + 150ml susu
            ['menu_id' => $menus[6]->id, 'material_id' => $materials[0]->id, 'amount' => 18, 'unit_id' => $unitMap['gram']->id],
            ['menu_id' => $menus[6]->id, 'material_id' => $materials[1]->id, 'amount' => 150, 'unit_id' => $unitMap['mililiter']->id],
            // Roti Bakar: 2 pcs roti + 30g selai
            ['menu_id' => $menus[7]->id, 'material_id' => $materials[7]->id, 'amount' => 2, 'unit_id' => $unitMap['pcs']->id],
            ['menu_id' => $menus[7]->id, 'material_id' => $materials[8]->id, 'amount' => 30, 'unit_id' => $unitMap['gram']->id],
        ];

        foreach ($menuMaterialData as $mm) {
            MenuMaterial::create($mm);
        }

        // Material Inbound (stok masuk awal)
        foreach ($materials as $mat) {
            MaterialInboundOutbound::create([
                'material_id' => $mat->id,
                'type' => 'inbound',
                'amount' => $mat->stock,
                'base_unit_id' => $mat->base_unit_id,
                'inbound_buy_price' => $mat->avg_buy_price,
            ]);
        }

        // Transactions (10 transaksi)
        $transactionConfigs = [
            ['table' => 0, 'payment' => 'manual', 'status' => 'success', 'items' => [[0, 2], [5, 1]]],
            ['table' => 1, 'payment' => 'qris',   'status' => 'success', 'items' => [[1, 1], [4, 1]]],
            ['table' => 2, 'payment' => 'manual', 'status' => 'success', 'items' => [[2, 2], [7, 1]]],
            ['table' => 3, 'payment' => 'qris',   'status' => 'success', 'items' => [[6, 1], [3, 2]]],
            ['table' => 4, 'payment' => 'manual', 'status' => 'success', 'items' => [[0, 1], [1, 1], [5, 2]]],
            ['table' => 0, 'payment' => 'qris',   'status' => 'pending', 'items' => [[4, 2]]],
            ['table' => 1, 'payment' => 'manual', 'status' => 'success', 'items' => [[3, 3], [7, 2]]],
            ['table' => 2, 'payment' => 'qris',   'status' => 'in_order', 'items' => [[1, 2], [2, 1]]],
            ['table' => 3, 'payment' => 'manual', 'status' => 'success', 'items' => [[6, 2], [5, 1]]],
            ['table' => 4, 'payment' => 'qris',   'status' => 'failed',  'items' => [[0, 1]]],
        ];

        foreach ($transactionConfigs as $tc) {
            $subtotal = 0;
            $details = [];

            foreach ($tc['items'] as [$menuIdx, $qty]) {
                $menu = $menus[$menuIdx];
                $linePrice = $menu->price * $qty;
                $subtotal += $linePrice;
                $details[] = [
                    'menu_id' => $menu->id,
                    'amount' => $qty,
                    'price' => $linePrice,
                ];
            }

            $fee = round($subtotal * 0.05, 2); // 5% fee
            $totalPrice = $subtotal + $fee;

            $transaction = Transaction::create([
                'cafe_id' => $cafe->id,
                'table_id' => $tables[$tc['table']]->id,
                'price' => $subtotal,
                'fee' => $fee,
                'total_price' => $totalPrice,
                'payment_type' => $tc['payment'],
                'status' => $tc['status'],
            ]);

            foreach ($details as $detail) {
                $td = TransactionDetail::create(array_merge($detail, [
                    'transaction_id' => $transaction->id,
                ]));

                // Buat outbound material untuk transaksi sukses
                if ($tc['status'] === 'success') {
                    $menu = Menu::find($detail['menu_id']);
                    $menuMats = MenuMaterial::where('menu_id', $menu->id)->get();

                    foreach ($menuMats as $mm) {
                        MaterialInboundOutbound::create([
                            'material_id' => $mm->material_id,
                            'type' => 'outbound',
                            'amount' => $mm->amount * $detail['amount'],
                            'base_unit_id' => $mm->unit_id,
                            'transaction_detail_id' => $td->id,
                        ]);
                    }
                }
            }
        }
    }
}
