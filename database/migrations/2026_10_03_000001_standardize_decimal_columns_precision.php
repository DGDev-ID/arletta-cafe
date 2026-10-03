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
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'profit_margin')) {
                    $table->decimal('profit_margin', 15, 2)->nullable()->change();
                }
                if (Schema::hasColumn('transactions', 'price')) {
                    $table->decimal('price', 15, 2)->change();
                }
                if (Schema::hasColumn('transactions', 'fee')) {
                    $table->decimal('fee', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('transactions', 'total_price')) {
                    $table->decimal('total_price', 15, 2)->change();
                }
                if (Schema::hasColumn('transactions', 'admin_fee')) {
                    $table->decimal('admin_fee', 15, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('transaction_details')) {
            Schema::table('transaction_details', function (Blueprint $table) {
                if (Schema::hasColumn('transaction_details', 'price')) {
                    $table->decimal('price', 15, 2)->change();
                }
            });
        }

        if (Schema::hasTable('m_menus')) {
            Schema::table('m_menus', function (Blueprint $table) {
                if (Schema::hasColumn('m_menus', 'price')) {
                    $table->decimal('price', 15, 2)->change();
                }
            });
        }

        if (Schema::hasTable('menu_promos')) {
            Schema::table('menu_promos', function (Blueprint $table) {
                if (Schema::hasColumn('menu_promos', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->change();
                }
            });
        }

        if (Schema::hasTable('third_party_channels')) {
            Schema::table('third_party_channels', function (Blueprint $table) {
                if (Schema::hasColumn('third_party_channels', 'admin_fee')) {
                    $table->decimal('admin_fee', 15, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('m_materials')) {
            Schema::table('m_materials', function (Blueprint $table) {
                if (Schema::hasColumn('m_materials', 'stock')) {
                    $table->decimal('stock', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('m_materials', 'avg_buy_price')) {
                    $table->decimal('avg_buy_price', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('m_materials', 'critical_stock')) {
                    $table->decimal('critical_stock', 15, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('material_variants')) {
            Schema::table('material_variants', function (Blueprint $table) {
                if (Schema::hasColumn('material_variants', 'stock')) {
                    $table->decimal('stock', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('material_variants', 'minimum_stock')) {
                    $table->decimal('minimum_stock', 15, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('material_inbound_outbounds')) {
            Schema::table('material_inbound_outbounds', function (Blueprint $table) {
                if (Schema::hasColumn('material_inbound_outbounds', 'opening_stock')) {
                    $table->decimal('opening_stock', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('material_inbound_outbounds', 'amount')) {
                    $table->decimal('amount', 15, 2)->change();
                }
                if (Schema::hasColumn('material_inbound_outbounds', 'closing_stock')) {
                    $table->decimal('closing_stock', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('material_inbound_outbounds', 'inbound_buy_price')) {
                    $table->decimal('inbound_buy_price', 15, 2)->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('menu_materials')) {
            Schema::table('menu_materials', function (Blueprint $table) {
                if (Schema::hasColumn('menu_materials', 'amount')) {
                    $table->decimal('amount', 15, 2)->change();
                }
            });
        }

        if (Schema::hasTable('semi_finished_material_details')) {
            Schema::table('semi_finished_material_details', function (Blueprint $table) {
                if (Schema::hasColumn('semi_finished_material_details', 'amount')) {
                    $table->decimal('amount', 15, 2)->change();
                }
            });
        }

        if (Schema::hasTable('menu_semi_finished_materials')) {
            Schema::table('menu_semi_finished_materials', function (Blueprint $table) {
                if (Schema::hasColumn('menu_semi_finished_materials', 'multiplier')) {
                    $table->decimal('multiplier', 15, 2)->default(1)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'profit_margin')) {
                    $table->decimal('profit_margin', 8, 2)->nullable()->change();
                }
                if (Schema::hasColumn('transactions', 'price')) {
                    $table->decimal('price', 12, 2)->change();
                }
                if (Schema::hasColumn('transactions', 'fee')) {
                    $table->decimal('fee', 12, 2)->default(0)->change();
                }
                if (Schema::hasColumn('transactions', 'total_price')) {
                    $table->decimal('total_price', 12, 2)->change();
                }
                if (Schema::hasColumn('transactions', 'admin_fee')) {
                    $table->decimal('admin_fee', 12, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('transaction_details')) {
            Schema::table('transaction_details', function (Blueprint $table) {
                if (Schema::hasColumn('transaction_details', 'price')) {
                    $table->decimal('price', 12, 2)->change();
                }
            });
        }

        if (Schema::hasTable('m_menus')) {
            Schema::table('m_menus', function (Blueprint $table) {
                if (Schema::hasColumn('m_menus', 'price')) {
                    $table->decimal('price', 12, 2)->change();
                }
            });
        }

        if (Schema::hasTable('menu_promos')) {
            Schema::table('menu_promos', function (Blueprint $table) {
                if (Schema::hasColumn('menu_promos', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->change();
                }
            });
        }

        if (Schema::hasTable('third_party_channels')) {
            Schema::table('third_party_channels', function (Blueprint $table) {
                if (Schema::hasColumn('third_party_channels', 'admin_fee')) {
                    $table->decimal('admin_fee', 12, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('m_materials')) {
            Schema::table('m_materials', function (Blueprint $table) {
                if (Schema::hasColumn('m_materials', 'stock')) {
                    $table->decimal('stock', 12, 2)->default(0)->change();
                }
                if (Schema::hasColumn('m_materials', 'avg_buy_price')) {
                    $table->decimal('avg_buy_price', 12, 2)->default(0)->change();
                }
                if (Schema::hasColumn('m_materials', 'critical_stock')) {
                    $table->decimal('critical_stock', 12, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('material_variants')) {
            Schema::table('material_variants', function (Blueprint $table) {
                if (Schema::hasColumn('material_variants', 'stock')) {
                    $table->decimal('stock', 12, 2)->default(0)->change();
                }
                if (Schema::hasColumn('material_variants', 'minimum_stock')) {
                    $table->decimal('minimum_stock', 12, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('material_inbound_outbounds')) {
            Schema::table('material_inbound_outbounds', function (Blueprint $table) {
                if (Schema::hasColumn('material_inbound_outbounds', 'opening_stock')) {
                    $table->decimal('opening_stock', 12, 2)->default(0)->change();
                }
                if (Schema::hasColumn('material_inbound_outbounds', 'amount')) {
                    $table->decimal('amount', 12, 2)->change();
                }
                if (Schema::hasColumn('material_inbound_outbounds', 'closing_stock')) {
                    $table->decimal('closing_stock', 12, 2)->default(0)->change();
                }
                if (Schema::hasColumn('material_inbound_outbounds', 'inbound_buy_price')) {
                    $table->decimal('inbound_buy_price', 12, 2)->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('menu_materials')) {
            Schema::table('menu_materials', function (Blueprint $table) {
                if (Schema::hasColumn('menu_materials', 'amount')) {
                    $table->decimal('amount', 12, 2)->change();
                }
            });
        }

        if (Schema::hasTable('semi_finished_material_details')) {
            Schema::table('semi_finished_material_details', function (Blueprint $table) {
                if (Schema::hasColumn('semi_finished_material_details', 'amount')) {
                    $table->decimal('amount', 12, 2)->change();
                }
            });
        }

        if (Schema::hasTable('menu_semi_finished_materials')) {
            Schema::table('menu_semi_finished_materials', function (Blueprint $table) {
                if (Schema::hasColumn('menu_semi_finished_materials', 'multiplier')) {
                    $table->decimal('multiplier', 8, 2)->default(1)->change();
                }
            });
        }
    }
};
