<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\MMaterial;
use App\Models\MaterialInboundOutbound;
use App\Models\MMenu;
use App\Models\MMenuCategory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\CustomerFeedback;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $thirdPartyChannels = \App\Models\ThirdPartyChannel::where('is_active', true)->pluck('name')->toArray();
        $paymentTypes = array_merge(['qris', 'debit', 'manual'], $thirdPartyChannels);

        $paymentStats = [];
        foreach ($paymentTypes as $type) {
            $q = $txBase()->where('status', 'success')->whereDate('created_at', $today)->where('payment_type', $type);
            $paymentStats[$type] = [
                'count'   => (int)   $q->count(),
                'revenue' => (float) $q->sum('total_price'),
            ];
        }

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
                    'total_revenue' => (float) $agg->total_revenue,
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

        // Recent Feedbacks (for dashboard widget)
        $recentFeedbacks = CustomerFeedback::with(['transaction:id,unique_code,cafe_id,cust_name,table_id,created_at', 'transaction.cafe:id,name', 'transaction.table:id,name'])
            ->when($cafeId, fn ($q) => $q->whereHas('transaction', fn ($tq) => $tq->where('cafe_id', $cafeId)))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $averageRating = CustomerFeedback::when($cafeId, fn ($q) => $q->whereHas('transaction', fn ($tq) => $tq->where('cafe_id', $cafeId)))
            ->avg('rating');

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
            'recentFeedbacks'    => $recentFeedbacks,
            'averageRating'      => $averageRating ? round((float) $averageRating, 1) : null,
        ]);
    }

    /**
     * Return all menus sold on a given date/range as JSON (used by frontend polling + filter).
     * Query params:
     *   - date_from    : Y-m-d (default: today)  — OR fallback to legacy: date
     *   - date_to      : Y-m-d (default: today)
     *   - category_id  : parent category id to filter (optional)
     *   - cafe_id      : optional
     */
    public function topMenusToday(\Illuminate\Http\Request $request)
    {
        // Support both legacy single ?date= and new date range ?date_from= / ?date_to=
        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        } elseif ($request->filled('date')) {
            $dateFrom = Carbon::parse($request->date)->startOfDay();
        } else {
            $dateFrom = Carbon::today()->startOfDay();
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
        } elseif ($request->filled('date')) {
            $dateTo = Carbon::parse($request->date)->endOfDay();
        } else {
            $dateTo = Carbon::today()->endOfDay();
        }

        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $cafeId     = $request->filled('cafe_id') ? (int) $request->cafe_id : null;

        $query = TransactionDetail::select(
                'menu_id',
                DB::raw('SUM(transaction_details.amount) as total_sold'),
                DB::raw('SUM(transaction_details.price * transaction_details.amount) as total_revenue')
            )
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'success')
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->when($cafeId, fn ($q) => $q->where('transactions.cafe_id', $cafeId))
            ->groupBy('menu_id')
            ->orderByDesc('total_sold');

        // Filter by parent category (include children categories too)
        if ($categoryId) {
            $childIds = MMenuCategory::where('parent_id', $categoryId)->pluck('id');
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
                    'total_revenue'        => (float) $agg->total_revenue,
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
     * Export Produk Terjual to Excel.
     * Query params:
     *   - date_from   : Y-m-d (default: today)
     *   - date_to     : Y-m-d (default: today)
     *   - category_id : optional
     *   - cafe_id     : optional
     */
    public function exportTopMenus(\Illuminate\Http\Request $request)
    {
        $dateFrom   = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : Carbon::today()->startOfDay();
        $dateTo     = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : Carbon::today()->endOfDay();
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $cafeId     = $request->filled('cafe_id')     ? (int) $request->cafe_id     : null;

        if ($cafeId) {
            $cafes = MCafe::where('id', $cafeId)->get();
        } else {
            $cafes = MCafe::orderBy('name')->get();
        }

        // ── Build Spreadsheet ─────────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produk Terjual');

        $labelFrom = $dateFrom->format('d M Y');
        $labelTo   = $dateTo->format('d M Y');
        $periodLabel = $labelFrom === $labelTo ? $labelFrom : "{$labelFrom} s/d {$labelTo}";

        // ── Title ──────────────────────────────────────────────────────────
        $sheet->setCellValue('A1', 'LAPORAN PRODUK TERJUAL');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Periode: ' . $periodLabel);
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);

        $sheet->setCellValue('A3', 'Dicetak: ' . now()->format('d M Y, H:i'));
        $sheet->mergeCells('A3:C3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);

        $currentRow = 5;

        foreach ($cafes as $cafe) {
            $sheet->setCellValue("A{$currentRow}", "Cabang: " . $cafe->name);
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
            $currentRow++;

            $query = TransactionDetail::select(
                    'menu_id',
                    DB::raw('SUM(transaction_details.amount) as total_sold')
                )
                ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
                ->where('transactions.status', 'success')
                ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
                ->where('transactions.cafe_id', $cafe->id)
                ->groupBy('menu_id')
                ->orderByDesc('total_sold');

            if ($categoryId) {
                $childIds = MMenuCategory::where('parent_id', $categoryId)->pluck('id');
                $allCategoryIds = $childIds->push($categoryId);
                $menuIdsInCategory = MMenu::whereIn('menu_category_id', $allCategoryIds)->pluck('id');
                $query->whereIn('menu_id', $menuIdsInCategory);
            }

            $aggs = $query->get();

            // Resolve menu names
            $rows = [];
            foreach ($aggs as $agg) {
                $menu = MMenu::find($agg->menu_id);
                if ($menu) {
                    $rows[] = [
                        'name'       => $menu->name,
                        'total_sold' => (int) $agg->total_sold,
                    ];
                }
            }

            // ── Header Row ─────────────────────────────────────────────────────
            $headerRow = $currentRow;
            $sheet->fromArray(['No', 'Produk', 'QTY Terjual'], null, "A{$headerRow}");
            $sheet->getStyle("A{$headerRow}:C{$headerRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$headerRow}:C{$headerRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF1E3A5F');
            $sheet->getStyle("A{$headerRow}:C{$headerRow}")->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A{$headerRow}:C{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // ── Data Rows ──────────────────────────────────────────────────────
            $currentRow++;
            $no  = 1;
            foreach ($rows as $item) {
                $sheet->fromArray([
                    $no++,
                    $item['name'],
                    $item['total_sold'],
                ], null, "A{$currentRow}");

                // Alternating row color
                $fillColor = ($no % 2 === 0) ? 'FFF5F5F5' : 'FFFFFFFF';
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($fillColor);

                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

                $currentRow++;
            }

            // ── Total Row ──────────────────────────────────────────────────────
            if (count($rows) > 0) {
                $totalQty = array_sum(array_column($rows, 'total_sold'));
                $sheet->setCellValue("A{$currentRow}", 'TOTAL');
                $sheet->setCellValue("B{$currentRow}", '');
                $sheet->setCellValue("C{$currentRow}", $totalQty);
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFEFEFEF');
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else {
                $sheet->setCellValue("A{$currentRow}", 'Tidak ada data');
                $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFont()->setItalic(true);
                $sheet->getStyle("A{$currentRow}:C{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF5F5F5');
            }

            // ── Borders on entire table ────────────────────────────────────────
            $sheet->getStyle("A{$headerRow}:C{$currentRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFCCCCCC');

            $currentRow += 2; // Spacer
        }

        // ── Column widths ──────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(45);
        $sheet->getColumnDimension('C')->setWidth(16);

        $filename = 'Produk_Terjual_' . $dateFrom->format('Ymd') . '_' . $dateTo->format('Ymd') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
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
     * Export Purchase Summary (Inbound & Outbound) to Excel.
     * Query params:
     *   - date_from   : Y-m-d (default: today)
     *   - date_to     : Y-m-d (default: today)
     *   - cafe_id     : optional
     */
    public function exportPurchaseSummary(\Illuminate\Http\Request $request)
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::createFromFormat('d-m-Y', $request->date_from)->startOfDay()
            : Carbon::today()->startOfDay();
        $dateTo   = $request->filled('date_to')
            ? Carbon::createFromFormat('d-m-Y', $request->date_to)->endOfDay()
            : Carbon::today()->endOfDay();
        $cafeId   = $request->filled('cafe_id') ? (int) $request->cafe_id : null;

        if ($cafeId) {
            $cafes = MCafe::where('id', $cafeId)->get();
        } else {
            $cafes = MCafe::orderBy('name')->get();
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Purchase Summary');

        $labelFrom = $dateFrom->format('d M Y');
        $labelTo   = $dateTo->format('d M Y');
        $periodLabel = $labelFrom === $labelTo ? $labelFrom : "{$labelFrom} s/d {$labelTo}";

        $sheet->setCellValue('A1', 'LAPORAN PURCHASE (INBOUND & OUTBOUND)');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Periode: ' . $periodLabel);
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);

        $sheet->setCellValue('A3', 'Dicetak: ' . now()->format('d M Y, H:i'));
        $sheet->mergeCells('A3:E3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);

        $currentRow = 5;

        foreach ($cafes as $cafe) {
            $sheet->setCellValue("A{$currentRow}", "Cabang: " . $cafe->name);
            $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
            $currentRow++;

            $query = MaterialInboundOutbound::query()
                ->select(
                    'm_materials.name',
                    DB::raw("SUM(CASE WHEN material_inbound_outbounds.type = 'inbound' THEN material_inbound_outbounds.amount ELSE 0 END) as qty_inbound"),
                    DB::raw("SUM(CASE WHEN material_inbound_outbounds.type = 'inbound' THEN COALESCE(material_inbound_outbounds.inbound_buy_price, 0) ELSE 0 END) as nominal_inbound"),
                    DB::raw("SUM(CASE WHEN material_inbound_outbounds.type = 'outbound' THEN material_inbound_outbounds.amount ELSE 0 END) as qty_outbound")
                )
                ->join('m_materials', 'material_inbound_outbounds.material_id', '=', 'm_materials.id')
                ->whereBetween('material_inbound_outbounds.created_at', [$dateFrom, $dateTo])
                ->where('m_materials.cafe_id', $cafe->id)
                ->groupBy('m_materials.id', 'm_materials.name')
                ->orderBy('m_materials.name');

            $data = $query->get();

            // Header Row
            $headerRow = $currentRow;
            $sheet->fromArray(['No', 'Bahan Baku', 'Belanja Masuk (QTY)', 'Belanja Masuk (Rp)', 'Belanja Keluar (QTY)'], null, "A{$headerRow}");
            $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF1E3A5F');
            $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $currentRow++;
            $no = 1;

            foreach ($data as $item) {
                $sheet->fromArray([
                    $no++,
                    $item->name,
                    (float) $item->qty_inbound,
                    (float) $item->nominal_inbound,
                    (float) $item->qty_outbound,
                ], null, "A{$currentRow}");

                // Alternating row color
                $fillColor = ($no % 2 === 0) ? 'FFF5F5F5' : 'FFFFFFFF';
                $sheet->getStyle("A{$currentRow}:E{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($fillColor);

                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                $currentRow++;
            }

            if ($data->count() > 0) {
                $totalQtyInbound = $data->sum('qty_inbound');
                $totalNominalInbound = $data->sum('nominal_inbound');
                $totalQtyOutbound = $data->sum('qty_outbound');

                $sheet->setCellValue("A{$currentRow}", 'TOTAL');
                $sheet->setCellValue("B{$currentRow}", '');
                $sheet->setCellValue("C{$currentRow}", (float) $totalQtyInbound);
                $sheet->setCellValue("D{$currentRow}", (float) $totalNominalInbound);
                $sheet->setCellValue("E{$currentRow}", (float) $totalQtyOutbound);

                $sheet->getStyle("A{$currentRow}:E{$currentRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$currentRow}:E{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFEFEFEF');
                $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $sheet->setCellValue("A{$currentRow}", 'Tidak ada data');
                $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                $sheet->getStyle("A{$currentRow}:E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$currentRow}:E{$currentRow}")->getFont()->setItalic(true);
                $sheet->getStyle("A{$currentRow}:E{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF5F5F5');
            }

            $sheet->getStyle("A{$headerRow}:E{$currentRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFCCCCCC');

            $currentRow += 2;
        }

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);

        $filename = 'Purchase_Summary_' . $dateFrom->format('Ymd') . '_' . $dateTo->format('Ymd') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
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

        $thirdPartyChannels = \App\Models\ThirdPartyChannel::where('is_active', true)->pluck('name')->toArray();
        $paymentTypes = array_merge(['qris', 'debit', 'manual'], $thirdPartyChannels);
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
