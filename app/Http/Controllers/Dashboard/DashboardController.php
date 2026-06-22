<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\MMaterial;
use App\Models\MaterialInboundOutbound;
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
        $cafeId = request()->filled('cafe_id') ? (int) request()->cafe_id : null;

        // Base query helper with optional cafe filter
        $txBase = fn () => Transaction::when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId));

        // Revenue Today
        $revenueToday = $txBase()->where('status', 'success')
            ->whereDate('created_at', $today)
            ->sum('total_price');

        $revenueYesterday = $txBase()->where('status', 'success')
            ->whereDate('created_at', $yesterday)
            ->sum('total_price');

        // Transactions Today
        $transactionsToday = $txBase()->where('status', 'success')
            ->whereDate('created_at', $today)
            ->count();

        $transactionsYesterday = $txBase()->where('status', 'success')
            ->whereDate('created_at', $yesterday)
            ->count();

        // Active Menus
        $activeMenus = MMenu::where('status', 'available')
            ->when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId))
            ->count();

        // Low Stock Materials
        $lowStockCount = MMaterial::whereColumn('stock', '<', 'critical_stock')
            ->when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId))
            ->count();
        $outOfStockCount = MMaterial::where('stock', '<=', 0)
            ->when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId))
            ->count();

        // Payment method stats (today) — count + revenue nominal
        $paymentStats = [
            'qris'   => [
                'count'   => (int)   $txBase()->where('status', 'success')->whereDate('created_at', $today)->where('payment_type', 'qris')->count(),
                'revenue' => (float) $txBase()->where('status', 'success')->whereDate('created_at', $today)->where('payment_type', 'qris')->sum('total_price'),
            ],
            'debit'  => [
                'count'   => (int)   $txBase()->where('status', 'success')->whereDate('created_at', $today)->where('payment_type', 'debit')->count(),
                'revenue' => (float) $txBase()->where('status', 'success')->whereDate('created_at', $today)->where('payment_type', 'debit')->sum('total_price'),
            ],
            'manual' => [
                'count'   => (int)   $txBase()->where('status', 'success')->whereDate('created_at', $today)->where('payment_type', 'manual')->count(),
                'revenue' => (float) $txBase()->where('status', 'success')->whereDate('created_at', $today)->where('payment_type', 'manual')->sum('total_price'),
            ],
        ];

        // Revenue last 7 days
        $last7Days = collect(range(6, 0))->map(function ($daysAgo) use ($txBase) {
            $date = Carbon::today()->subDays($daysAgo);
            return [
                'date'    => $date->format('d M'),
                'revenue' => $txBase()->where('status', 'success')
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
            ->whereHas('transaction', fn ($q) => $q->where('status', 'success')
                ->when($cafeId, fn ($q2) => $q2->where('cafe_id', $cafeId)))
            ->groupBy('menu_id')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // Top selling menus for today (all)
        $topTodayAggs = TransactionDetail::select(
                'menu_id',
                DB::raw('SUM(transaction_details.amount) as total_sold'),
                DB::raw('SUM(transaction_details.price * transaction_details.amount) as total_revenue')
            )
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'success')
            ->whereDate('transactions.created_at', $today)
            ->when($cafeId, fn ($q) => $q->where('transactions.cafe_id', $cafeId))
            ->groupBy('menu_id')
            ->orderByDesc('total_sold')
            ->get();

        $topMenusToday = collect();
        foreach ($topTodayAggs as $agg) {
            $menu = MMenu::with('cafe:id,name')->find($agg->menu_id);
            if ($menu) {
                $topMenusToday->push([
                    'menu_id' => $menu->id,
                    'name' => $menu->name,
                    'price' => $menu->price,
                    'total_sold' => (int) $agg->total_sold,
                    'cafe_id' => $menu->cafe_id,
                    'cafe_name' => $menu->cafe?->name ?? null,
                ]);
            }
        }

        // Critical stock materials
        $criticalStocks = MMaterial::with('cafe:id,name', 'baseUnit:id,name')
            ->whereColumn('stock', '<', 'critical_stock')
            ->when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId))
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get(['id', 'name', 'cafe_id', 'stock', 'base_unit_id', 'avg_buy_price']);

        // Recent transactions
        $recentTransactions = Transaction::with('cafe:id,name', 'table:id,name')
            ->when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'cafe_id', 'table_id', 'total_price', 'payment_type', 'status', 'created_at']);

        // Table occupancy
        $totalTables = MCafeTable::when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId))->count();
        $occupiedTables = $txBase()->whereIn('status', ['in_order', 'pending'])
            ->whereDate('created_at', $today)
            ->distinct('table_id')
            ->count('table_id');

        // All cafes list (for filter)
        $cafes = MCafe::orderBy('name')->get(['id', 'name']);

        // Total cafes
        $totalCafes = $cafes->count();

        // Parent categories (for filter on dashboard)
        $parentCategories = \App\Models\MMenuCategory::whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return inertia('Dashboard', [
            'stats' => [
                'revenueToday'          => (float) $revenueToday,
                'revenueYesterday'      => (float) $revenueYesterday,
                'transactionsToday'     => $transactionsToday,
                'transactionsYesterday' => $transactionsYesterday,
                'activeMenus'           => $activeMenus,
                'lowStockCount'         => $lowStockCount,
                'outOfStockCount'       => $outOfStockCount,
                'totalTables'           => $totalTables,
                'occupiedTables'        => $occupiedTables,
                'totalCafes'            => $totalCafes,
                'paymentStats'          => $paymentStats,
            ],
            'revenueChart'       => $last7Days,
            'topMenus'           => $topMenus,
            'criticalStocks'     => $criticalStocks,
            'recentTransactions' => $recentTransactions,
            'topMenusToday'      => $topMenusToday,
            'parentCategories'   => $parentCategories,
            'cafes'              => $cafes,
            'activeCafeId'       => $cafeId,
        ]);
    }

    /**
     * Return all menus sold on a given date as JSON (used by frontend polling + filter).
     * Query params:
     *   - date        : Y-m-d (default: today)
     *   - category_id : parent category id to filter (optional)
     */
    public function topMenusToday(\Illuminate\Http\Request $request)
    {
        $date       = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $cafeId     = $request->filled('cafe_id') ? (int) $request->cafe_id : null;

        $query = TransactionDetail::select(
                'menu_id',
                DB::raw('SUM(transaction_details.amount) as total_sold'),
                DB::raw('SUM(transaction_details.price * transaction_details.amount) as total_revenue')
            )
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'success')
            ->whereDate('transactions.created_at', $date)
            ->when($cafeId, fn ($q) => $q->where('transactions.cafe_id', $cafeId))
            ->groupBy('menu_id')
            ->orderByDesc('total_sold');

        // Filter by parent category (include children categories too)
        if ($categoryId) {
            $childIds = \App\Models\MMenuCategory::where('parent_id', $categoryId)->pluck('id');
            $allCategoryIds = $childIds->push($categoryId);
            $menuIdsInCategory = MMenu::whereIn('menu_category_id', $allCategoryIds)->pluck('id');
            $query->whereIn('menu_id', $menuIdsInCategory);
        }

        $topTodayAggs = $query->get();

        $topMenusToday = collect();
        foreach ($topTodayAggs as $agg) {
            $menu = MMenu::with('cafe:id,name', 'category:id,name,parent_id', 'category.parent:id,name')->find($agg->menu_id);
            if ($menu) {
                $parentCategory = $menu->category?->parent_id
                    ? $menu->category->parent
                    : $menu->category;

                $topMenusToday->push([
                    'menu_id'              => $menu->id,
                    'name'                 => $menu->name,
                    'price'                => $menu->price,
                    'total_sold'           => (int) $agg->total_sold,
                    'cafe_id'              => $menu->cafe_id,
                    'cafe_name'            => $menu->cafe?->name ?? null,
                    'category_id'          => $menu->category?->id,
                    'category_name'        => $menu->category?->name,
                    'parent_category_id'   => $parentCategory?->id,
                    'parent_category_name' => $parentCategory?->name,
                ]);
            }
        }

        return response()->json($topMenusToday->values());
    }

    /**
     * Return purchase summary (inbound & outbound) as JSON.
     * Query params:
     *   - date_from   : Y-m-d (default: today)
     *   - date_to     : Y-m-d (default: today)
     *   - cafe_id     : optional
     */
    public function purchaseSummary(\Illuminate\Http\Request $request)
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::createFromFormat('d-m-Y', $request->date_from)->startOfDay()
            : Carbon::today()->startOfDay();
        $dateTo   = $request->filled('date_to')
            ? Carbon::createFromFormat('d-m-Y', $request->date_to)->endOfDay()
            : Carbon::today()->endOfDay();
        $cafeId   = $request->filled('cafe_id')   ? (int) $request->cafe_id : null;

        $base = MaterialInboundOutbound::query()
            ->join('m_materials', 'material_inbound_outbounds.material_id', '=', 'm_materials.id')
            ->whereBetween('material_inbound_outbounds.created_at', [$dateFrom, $dateTo])
            ->when($cafeId, fn ($q) => $q->where('m_materials.cafe_id', $cafeId));

        $inbound = (clone $base)->where('material_inbound_outbounds.type', 'inbound')
            ->selectRaw('COUNT(*) as total_records, SUM(material_inbound_outbounds.amount) as total_amount, SUM(COALESCE(material_inbound_outbounds.inbound_buy_price, 0)) as total_nominal')
            ->first();

        $outbound = (clone $base)->where('material_inbound_outbounds.type', 'outbound')
            ->selectRaw('COUNT(*) as total_records, SUM(material_inbound_outbounds.amount) as total_amount')
            ->first();

        return response()->json([
            'inbound' => [
                'total_records' => (int) ($inbound->total_records ?? 0),
                'total_amount'  => (float) ($inbound->total_amount ?? 0),
                'total_nominal' => (float) ($inbound->total_nominal ?? 0),
            ],
            'outbound' => [
                'total_records' => (int) ($outbound->total_records ?? 0),
                'total_amount'  => (float) ($outbound->total_amount ?? 0),
            ],
        ]);
    }

    /**
     * Return payment method stats (QRIS, Debit, Manual) filtered by period.
     * Query params:
     *   - period  : 'day' (default) | 'month' | 'year'
     *   - cafe_id : optional
     */
    public function paymentStatsByPeriod(\Illuminate\Http\Request $request)
    {
        $period = $request->input('period', 'day');
        $cafeId = $request->filled('cafe_id') ? (int) $request->cafe_id : null;

        $now = Carbon::now();

        $txBase = Transaction::where('status', 'success')
            ->when($cafeId, fn ($q) => $q->where('cafe_id', $cafeId));

        if ($period === 'year') {
            // Spesifik tahun: ?year=2025  (fallback: tahun ini)
            $year = $request->filled('year') ? (int) $request->year : $now->year;
            $txBase = $txBase->whereYear('created_at', $year);
        } elseif ($period === 'month') {
            // Spesifik bulan: ?month=2025-06  (fallback: bulan ini)
            if ($request->filled('month')) {
                [$y, $m] = explode('-', $request->month);
                $txBase = $txBase->whereYear('created_at', (int) $y)
                                 ->whereMonth('created_at', (int) $m);
            } else {
                $txBase = $txBase->whereYear('created_at', $now->year)
                                 ->whereMonth('created_at', $now->month);
            }
        } elseif ($period === 'week') {
            // Spesifik minggu: ?week=2025-W25  format ISO 8601  (fallback: minggu ini)
            if ($request->filled('week')) {
                // Pisahkan "2025-W25" → tahun=2025, week=25
                preg_match('/^(\d{4})-W(\d{2})$/', $request->week, $m);
                if ($m) {
                    $startOfWeek = Carbon::now()->setISODate((int) $m[1], (int) $m[2])->startOfDay();
                } else {
                    $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                }
            } else {
                $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            }
            $endOfWeek = $startOfWeek->copy()->addDays(6)->endOfDay();
            $txBase = $txBase->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
        } else {
            // Spesifik hari: ?date=2025-06-22  (fallback: hari ini)
            $date = $request->filled('date') ? $request->date : $now->toDateString();
            $txBase = $txBase->whereDate('created_at', $date);
        }

        $paymentTypes = ['qris', 'debit', 'manual'];
        $stats = [];
        foreach ($paymentTypes as $type) {
            $q = (clone $txBase)->where('payment_type', $type);
            $stats[$type] = [
                'count'   => (int)   $q->count(),
                'revenue' => (float) $q->sum('total_price'),
            ];
        }

        return response()->json($stats);
    }
}
