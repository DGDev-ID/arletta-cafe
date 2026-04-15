<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\MMaterial;
use App\Models\MMenu;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Revenue Today
        $revenueToday = Transaction::where('status', 'success')
            ->whereDate('created_at', $today)
            ->sum('total_price');

        $revenueYesterday = Transaction::where('status', 'success')
            ->whereDate('created_at', $yesterday)
            ->sum('total_price');

        // Transactions Today
        $transactionsToday = Transaction::where('status', 'success')
            ->whereDate('created_at', $today)
            ->count();

        $transactionsYesterday = Transaction::where('status', 'success')
            ->whereDate('created_at', $yesterday)
            ->count();

        // Active Menus
        $activeMenus = MMenu::where('status', 'active')->count();

        // Low Stock Materials (stok di bawah 10)
        $lowStockCount = MMaterial::where('stock', '<', 10)->count();
        $outOfStockCount = MMaterial::where('stock', '<=', 0)->count();

        // Revenue last 7 days
        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date'    => $date->format('d M'),
                'revenue' => Transaction::where('status', 'success')
                    ->whereDate('created_at', $date)
                    ->sum('total_price'),
            ];
        });

        // Top selling menus (based on transaction details)
        $topMenus = TransactionDetail::select(
                'menu_id',
                DB::raw('SUM(amount) as total_sold'),
                DB::raw('SUM(price * amount) as total_revenue')
            )
            ->with('menu:id,name,price,cafe_id', 'menu.cafe:id,name')
            ->whereHas('transaction', fn ($q) => $q->where('status', 'success'))
            ->groupBy('menu_id')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // Critical stock materials
        $criticalStocks = MMaterial::with('cafe:id,name', 'baseUnit:id,name')
            ->whereColumn('stock', '<', 'critical_stock')
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get(['id', 'name', 'cafe_id', 'stock', 'base_unit_id', 'avg_buy_price']);

        // Recent transactions
        $recentTransactions = Transaction::with('cafe:id,name', 'table:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'cafe_id', 'table_id', 'total_price', 'payment_type', 'status', 'created_at']);

        // Table occupancy
        $totalTables = MCafeTable::count();
        $occupiedTables = Transaction::whereIn('status', ['in_order', 'pending'])
            ->whereDate('created_at', $today)
            ->distinct('table_id')
            ->count('table_id');

        // Total cafes
        $totalCafes = MCafe::count();

        return inertia('Dashboard', [
            'stats' => [
                'revenueToday'        => (float) $revenueToday,
                'revenueYesterday'    => (float) $revenueYesterday,
                'transactionsToday'   => $transactionsToday,
                'transactionsYesterday' => $transactionsYesterday,
                'activeMenus'         => $activeMenus,
                'lowStockCount'       => $lowStockCount,
                'outOfStockCount'     => $outOfStockCount,
                'totalTables'         => $totalTables,
                'occupiedTables'      => $occupiedTables,
                'totalCafes'          => $totalCafes,
            ],
            'revenueChart'        => $last7Days,
            'topMenus'            => $topMenus,
            'criticalStocks'      => $criticalStocks,
            'recentTransactions'  => $recentTransactions,
        ]);
    }
}
