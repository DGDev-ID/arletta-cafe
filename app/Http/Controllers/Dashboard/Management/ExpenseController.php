<?php

namespace App\Http\Controllers\Dashboard\Management;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\MCafe;
use App\Models\MMenu;
use App\Services\MenuAvailabilityService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();
        // Latest expense transactions (limit 50)
        $expenses = Transaction::with(['cafe', 'table', 'details.menu'])
            ->where('is_expense', 1)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('management/expense/Index', [
            'cafes' => $cafes,
            'expenses' => $expenses,
        ]);
    }

    public function create()
    {
        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('management/expense/Create', [
            'cafes' => $cafes,
        ]);
    }

    public function getMenusByCafe(Request $request)
    {
        $cafeId = $request->input('cafe_id');

        $menus = MMenu::where('cafe_id', $cafeId)
            ->select('id', 'name', 'price')
            ->orderBy('name')
            ->get();

        $svc = new MenuAvailabilityService();

        $result = $menus->map(function ($m) use ($svc) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'price' => $m->price,
                'available' => $svc->checkAvailableMenu($m, 1),
            ];
        });

        return response()->json($result);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:m_menus,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');

        $menuIds = collect($items)->pluck('menu_id')->all();
        $menus = MMenu::whereIn('id', $menuIds)->get()->keyBy('id');

        $svc = new MenuAvailabilityService();

        $result = [];
        foreach ($items as $it) {
            $menu = $menus[$it['menu_id']] ?? null;
            if (!$menu) {
                $result[] = [
                    'menu_id' => $it['menu_id'],
                    'available' => false,
                ];
                continue;
            }

            $available = $svc->checkAvailableMenu($menu, (int)$it['quantity']);
            $result[] = [
                'menu_id' => $it['menu_id'],
                'available' => (bool)$available,
            ];
        }

        return response()->json(['items' => $result]);
    }

    public function markExpense($id)
    {
        $transaction = Transaction::findOrFail($id);

        // Call TransactionService to mark as expense
        TransactionService::makeExpense($transaction);

        return redirect()->route('management.expense.index')
            ->with('success', 'Transaksi telah ditandai sebagai pengeluaran.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'cafe_id' => 'required|exists:m_cafes,id',
            'table_id' => 'nullable|exists:m_cafe_tables,id',
            'details' => 'required|array|min:1',
            'details.*.menu_id' => 'required|exists:m_menus,id',
            'details.*.amount' => 'required|integer|min:1',
            'cust_name' => 'nullable|string|max:255',
        ]);

        $cafe = MCafe::findOrFail($request->cafe_id);

        $data = [
            // makeTransaction expects cafe unique_id (API uses unique_id)
            'cafe_id' => $cafe->unique_id,
            'table_id' => $request->input('table_id') ?? null,
            'details' => $request->details,
            'cust_name' => $request->cust_name ?? 'Pengeluaran',
            'payment_type' => 'manual',
        ];

        try {
            $transaction = TransactionService::makeTransaction($data);
            TransactionService::makeExpenseTransaction($transaction);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal membuat pengeluaran: ' . $e->getMessage());
        }

        return redirect()->route('management.expense.index')
            ->with('success', 'Pengeluaran berhasil dibuat.');
    }
}
