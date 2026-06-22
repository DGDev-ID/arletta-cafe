<script setup lang="ts">
import CriticalStock from '@/components/dashboard/CriticalStock.vue';
import KpiCard from '@/components/dashboard/KpiCard.vue';
import RecentTransactions from '@/components/dashboard/RecentTransactions.vue';
import RevenueChart from '@/components/dashboard/RevenueChart.vue';
import TopMenus from '@/components/dashboard/TopMenus.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle, Coffee, ShoppingCart, Utensils, Wallet,
    CalendarDays, Building2, CreditCard, Banknote, QrCode, Store,
    PackagePlus, PackageMinus, TrendingUp, TrendingDown
} from 'lucide-vue-next';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

const props = defineProps<{
    stats: {
        revenueToday: number;
        revenueYesterday: number;
        transactionsToday: number;
        transactionsYesterday: number;
        activeMenus: number;
        lowStockCount: number;
        outOfStockCount: number;
        totalTables: number;
        occupiedTables: number;
        totalCafes: number;
        paymentStats: {
            qris:   { count: number; revenue: number };
            debit:  { count: number; revenue: number };
            manual: { count: number; revenue: number };
        };
    };
    revenueChart: { date: string; revenue: number }[];
    topMenus: any[];
    criticalStocks: any[];
    recentTransactions: any[];
    topMenusToday: any[];
    parentCategories: { id: number; name: string }[];
    cafes: { id: number; name: string }[];
    activeCafeId: number | null;
}>();

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(value);

const percentChange = (current: number, previous: number) => {
    if (previous === 0) return current > 0 ? 100 : 0;
    return ((current - previous) / previous) * 100;
};

const revenueChange = computed(() => percentChange(props.stats.revenueToday, props.stats.revenueYesterday));
const txChange = computed(() => percentChange(props.stats.transactionsToday, props.stats.transactionsYesterday));

// ── Filter Cabang ────────────────────────────────────────────────────────────
const selectedCafeId = ref<number | null>(props.activeCafeId ?? null);

function applyCafeFilter(cafeId: number | null) {
    selectedCafeId.value = cafeId;
    const params: Record<string, string> = {};
    if (cafeId !== null) params.cafe_id = String(cafeId);
    router.get('/dashboard', params, { preserveScroll: true, preserveState: false });
}

// ── Produk Terjual Hari Ini ───────────────────────────────────────────────────
const todayStr = new Date().toISOString().slice(0, 10);
const filterDate = ref(todayStr);
const filterCategoryId = ref<number | null>(null);
const isLoadingMenus = ref(false);

const topMenusToday = ref(props.topMenusToday ?? []);

async function fetchTopMenus() {
    try {
        isLoadingMenus.value = true;
        const params: Record<string, string> = { date: filterDate.value };
        if (filterCategoryId.value !== null) params.category_id = String(filterCategoryId.value);
        if (selectedCafeId.value !== null) params.cafe_id = String(selectedCafeId.value);
        const { data } = await axios.get('/dashboard/top-menus-today', { params });
        topMenusToday.value = data;
    } catch {
        // silent
    } finally {
        isLoadingMenus.value = false;
    }
}

// Refetch on filter change
watch([filterDate, filterCategoryId], () => fetchTopMenus());

let pollInterval: number | undefined;

onMounted(() => {
    void fetchTopMenus();
    void fetchPurchaseSummary();
    pollInterval = window.setInterval(() => {
        // Only auto-poll if viewing today
        if (filterDate.value === todayStr) void fetchTopMenus();
    }, 60_000);
});

onBeforeUnmount(() => {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = undefined; }
});

// Label cabang aktif
const activeCafeName = computed(() => {
    if (selectedCafeId.value === null) return 'Semua Cabang';
    return props.cafes.find(c => c.id === selectedCafeId.value)?.name ?? 'Semua Cabang';
});

// ── Payment Stats (Metode Pembayaran) ────────────────────────────────────────
type PaymentPeriod = 'day' | 'month' | 'year';
const paymentPeriod      = ref<PaymentPeriod>('day');
const paymentFilterDate  = ref(todayStr);                              // YYYY-MM-DD
const paymentFilterMonth = ref(todayStr.slice(0, 7));                  // YYYY-MM
const paymentFilterYear  = ref(new Date().getFullYear());              // number
const isLoadingPayment   = ref(false);
const paymentStats = ref<{
    qris:   { count: number; revenue: number };
    debit:  { count: number; revenue: number };
    manual: { count: number; revenue: number };
} | null>(null);

// Seed awal dari props (hari ini)
function seedPaymentStats() {
    paymentStats.value = {
        qris:   { ...props.stats.paymentStats.qris },
        debit:  { ...props.stats.paymentStats.debit },
        manual: { ...props.stats.paymentStats.manual },
    };
}
seedPaymentStats();

async function fetchPaymentStats() {
    try {
        isLoadingPayment.value = true;
        const params: Record<string, string> = { period: paymentPeriod.value };
        if (selectedCafeId.value !== null) params.cafe_id = String(selectedCafeId.value);
        if (paymentPeriod.value === 'day')   params.date  = paymentFilterDate.value;
        if (paymentPeriod.value === 'month') params.month = paymentFilterMonth.value;
        if (paymentPeriod.value === 'year')  params.year  = String(paymentFilterYear.value);
        const { data } = await axios.get('/dashboard/payment-stats', { params });
        paymentStats.value = data;
    } catch {
        // silent
    } finally {
        isLoadingPayment.value = false;
    }
}

// Daftar tahun tersedia (5 tahun ke belakang sampai sekarang)
const availableYears = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 6 }, (_, i) => currentYear - i);
});

// Format label header
const paymentPeriodLabel = computed(() => {
    if (paymentPeriod.value === 'year') {
        return `Tahun ${paymentFilterYear.value}`;
    }
    if (paymentPeriod.value === 'month') {
        const [y, m] = paymentFilterMonth.value.split('-');
        const date = new Date(Number(y), Number(m) - 1, 1);
        return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    }
    // day
    const d = new Date(paymentFilterDate.value + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
});

watch([paymentPeriod, paymentFilterDate, paymentFilterMonth, paymentFilterYear, selectedCafeId], () => fetchPaymentStats());

// ── Purchase Summary (Inbound & Outbound) ────────────────────────────────────
const purchaseDateFrom = ref(todayStr);
const purchaseDateTo   = ref(todayStr);
const isLoadingPurchase = ref(false);
const purchaseSummary = ref<{
    inbound:  { total_records: number; total_amount: number; total_nominal: number };
    outbound: { total_records: number; total_amount: number };
} | null>(null);

// Helper: konversi YYYY-MM-DD -> DD-MM-YYYY untuk dikirim ke API
const toApiDate = (ymd: string) => {
    const [y, m, d] = ymd.split('-');
    return `${d}-${m}-${y}`;
};

async function fetchPurchaseSummary() {
    try {
        isLoadingPurchase.value = true;
        const params: Record<string, string> = {
            date_from: toApiDate(purchaseDateFrom.value),
            date_to:   toApiDate(purchaseDateTo.value),
        };
        if (selectedCafeId.value !== null) params.cafe_id = String(selectedCafeId.value);
        const { data } = await axios.get('/dashboard/purchase-summary', { params });
        purchaseSummary.value = data;
    } catch {
        // silent
    } finally {
        isLoadingPurchase.value = false;
    }
}

watch([purchaseDateFrom, purchaseDateTo, selectedCafeId], () => fetchPurchaseSummary());


</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">

            <!-- ── Filter Cabang ─────────────────────────────────────────── -->
            <div v-if="cafes.length > 1" class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Building2 class="h-4 w-4 shrink-0" />
                    <span class="font-medium">Filter Cabang:</span>
                </div>
                <button
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="selectedCafeId === null
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                    @click="applyCafeFilter(null)"
                >
                    Semua Cabang
                </button>
                <button
                    v-for="cafe in cafes"
                    :key="cafe.id"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="selectedCafeId === cafe.id
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                    @click="applyCafeFilter(cafe.id)"
                >
                    {{ cafe.name }}
                </button>
            </div>

            <!-- Label cabang yang sedang aktif -->
            <div v-if="selectedCafeId !== null" class="flex items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary">
                <Store class="h-3.5 w-3.5 shrink-0" />
                <span>Menampilkan data untuk cabang: <strong>{{ activeCafeName }}</strong></span>
                <button class="ml-auto rounded px-1.5 py-0.5 hover:bg-primary/10 transition-colors" @click="applyCafeFilter(null)">
                    ✕ Reset
                </button>
            </div>

            <!-- ── KPI Cards ─────────────────────────────────────────────── -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    title="Revenue Hari Ini"
                    :value="formatCurrency(stats.revenueToday)"
                    :change="revenueChange"
                    subtitle="vs kemarin"
                    :icon="Wallet"
                    icon-class="bg-emerald-100 dark:bg-emerald-900/30"
                />
                <KpiCard
                    title="Transaksi Hari Ini"
                    :value="String(stats.transactionsToday)"
                    :change="txChange"
                    subtitle="vs kemarin"
                    :icon="ShoppingCart"
                    icon-class="bg-blue-100 dark:bg-blue-900/30"
                />
                <KpiCard
                    title="Stok Kritis"
                    :value="String(stats.lowStockCount)"
                    :subtitle="stats.outOfStockCount > 0 ? `${stats.outOfStockCount} bahan habis` : 'semua bahan tersedia'"
                    :icon="AlertTriangle"
                    icon-class="bg-orange-100 dark:bg-orange-900/30"
                />
                <KpiCard
                    title="Menu Aktif"
                    :value="String(stats.activeMenus)"
                    :subtitle="`${stats.totalCafes} cafe terdaftar`"
                    :icon="Utensils"
                    icon-class="bg-purple-100 dark:bg-purple-900/30"
                />
            </div>

            <!-- ── Metode Pembayaran ──────────────────────────────────────── -->
            <div>
                <!-- Header + Filter Periode -->
                <div class="mb-3 flex flex-col gap-2">
                    <!-- Baris 1: judul + toggle mode -->
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <CreditCard class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-semibold">Transaksi per Metode Pembayaran
                                <span class="font-normal text-muted-foreground">({{ paymentPeriodLabel }})</span>
                            </span>
                        </div>
                        <!-- Toggle mode: Hari / Bulan / Tahun -->
                        <div class="flex items-center gap-1 rounded-lg border bg-muted p-0.5 self-start sm:self-auto">
                            <button
                                v-for="opt in ([{ value: 'day', label: 'Hari' }, { value: 'month', label: 'Bulan' }, { value: 'year', label: 'Tahun' }] as const)"
                                :key="opt.value"
                                class="rounded-md px-3 py-1 text-xs font-medium transition-all"
                                :class="paymentPeriod === opt.value
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'"
                                @click="paymentPeriod = opt.value"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                    <!-- Baris 2: input picker sesuai mode -->
                    <div class="flex items-center gap-2">
                        <!-- Mode Hari: date picker -->
                        <div v-if="paymentPeriod === 'day'" class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Tanggal:</span>
                            <input
                                v-model="paymentFilterDate"
                                type="date"
                                :max="todayStr"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                        <!-- Mode Bulan: month picker -->
                        <div v-else-if="paymentPeriod === 'month'" class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Bulan:</span>
                            <input
                                v-model="paymentFilterMonth"
                                type="month"
                                :max="todayStr.slice(0, 7)"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                        <!-- Mode Tahun: select dropdown -->
                        <div v-else class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Tahun:</span>
                            <select
                                v-model="paymentFilterYear"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            >
                                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Skeleton loader -->
                <div v-if="isLoadingPayment" class="grid gap-4 sm:grid-cols-3">
                    <div v-for="i in 3" :key="i" class="flex items-center gap-4 rounded-xl border p-4 animate-pulse">
                        <div class="h-11 w-11 rounded-lg bg-muted shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-2.5 w-1/3 rounded bg-muted"></div>
                            <div class="h-5 w-2/3 rounded bg-muted"></div>
                            <div class="h-2.5 w-1/2 rounded bg-muted"></div>
                        </div>
                    </div>
                </div>

                <!-- Cards -->
                <div v-else-if="paymentStats" class="grid gap-4 sm:grid-cols-3">
                    <!-- QRIS -->
                    <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/30">
                            <QrCode class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-muted-foreground">QRIS</div>
                            <div class="text-xl font-bold leading-tight truncate">{{ formatCurrency(paymentStats.qris.revenue) }}</div>
                            <div class="text-xs text-muted-foreground">{{ paymentStats.qris.count }} transaksi</div>
                        </div>
                    </div>
                    <!-- Debit -->
                    <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <CreditCard class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-muted-foreground">Debit</div>
                            <div class="text-xl font-bold leading-tight truncate">{{ formatCurrency(paymentStats.debit.revenue) }}</div>
                            <div class="text-xs text-muted-foreground">{{ paymentStats.debit.count }} transaksi</div>
                        </div>
                    </div>
                    <!-- Manual / Cash -->
                    <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                            <Banknote class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-muted-foreground">Manual / Cash</div>
                            <div class="text-xl font-bold leading-tight truncate">{{ formatCurrency(paymentStats.manual.revenue) }}</div>
                            <div class="text-xs text-muted-foreground">{{ paymentStats.manual.count }} transaksi</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart (full width) -->
            <RevenueChart :data="revenueChart" />

            <!-- ── Total Purchase (Inbound & Outbound) ─────────────────── -->
            <div class="rounded-lg border bg-card p-4 shadow-sm flex flex-col gap-4">

                <!-- Header -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <TrendingUp class="h-4 w-4 text-primary" />
                        <span class="text-sm font-semibold">Total Purchase</span>
                        <span class="text-xs text-muted-foreground font-normal">— Inbound & Outbound Bahan Baku</span>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Dari:</span>
                            <input
                                v-model="purchaseDateFrom"
                                type="date"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Sampai:</span>
                            <input
                                v-model="purchaseDateTo"
                                type="date"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                    </div>
                </div>

                <!-- Cards -->
                <div v-if="isLoadingPurchase" class="grid gap-4 sm:grid-cols-2">
                    <div v-for="i in 2" :key="i" class="flex items-center gap-4 rounded-xl border p-4 animate-pulse">
                        <div class="h-11 w-11 rounded-lg bg-muted shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3 w-1/3 rounded bg-muted"></div>
                            <div class="h-5 w-1/2 rounded bg-muted"></div>
                            <div class="h-2.5 w-2/3 rounded bg-muted"></div>
                        </div>
                    </div>
                </div>

                <div v-else-if="purchaseSummary" class="grid gap-4 sm:grid-cols-2">
                    <!-- Inbound -->
                    <div class="flex items-start gap-4 rounded-xl border bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-800/40 p-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                            <PackagePlus class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide mb-1">Inbound (Masuk)</div>
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="text-2xl font-bold leading-tight text-emerald-700 dark:text-emerald-300">
                                    {{ purchaseSummary.inbound.total_records }}
                                </span>
                                <span class="text-xs text-muted-foreground">transaksi</span>
                            </div>
                            <div class="mt-1 space-y-0.5">
                                <div class="text-xs text-muted-foreground">
                                    Jumlah Barang:
                                    <span class="font-semibold text-foreground">{{ purchaseSummary.inbound.total_amount.toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    Total Nominal:
                                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ formatCurrency(purchaseSummary.inbound.total_nominal) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outbound -->
                    <div class="flex items-start gap-4 rounded-xl border bg-orange-50/50 dark:bg-orange-900/10 border-orange-200 dark:border-orange-800/40 p-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/40">
                            <PackageMinus class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-orange-700 dark:text-orange-400 uppercase tracking-wide mb-1">Outbound (Keluar)</div>
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="text-2xl font-bold leading-tight text-orange-700 dark:text-orange-300">
                                    {{ purchaseSummary.outbound.total_records }}
                                </span>
                                <span class="text-xs text-muted-foreground">transaksi</span>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs text-muted-foreground">
                                    Jumlah Barang:
                                    <span class="font-semibold text-foreground">{{ Math.round(purchaseSummary.outbound.total_amount).toLocaleString('id-ID') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="py-8 text-center text-sm text-muted-foreground">
                    Tidak ada data purchase untuk filter ini.
                </div>
            </div>

            <!-- Produk Terjual (full width) -->
            <div class="rounded-lg border bg-card p-4 shadow-sm flex flex-col gap-4">

                <!-- Header -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <Coffee class="h-4 w-4 text-primary" />
                        <span class="text-sm font-semibold">Produk Terjual</span>
                        <span v-if="!isLoadingMenus" class="text-xs text-muted-foreground">
                            — {{ topMenusToday.length }} produk
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Date filter -->
                        <div class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <input
                                v-model="filterDate"
                                type="date"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                    </div>
                </div>

                <!-- Category filter buttons -->
                <div v-if="parentCategories.length" class="flex flex-wrap gap-2">
                    <button
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="filterCategoryId === null
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                        @click="filterCategoryId = null"
                    >
                        Semua
                    </button>
                    <button
                        v-for="cat in parentCategories"
                        :key="cat.id"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="filterCategoryId === cat.id
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                        @click="filterCategoryId = cat.id"
                    >
                        {{ cat.name }}
                    </button>
                </div>

                <!-- List -->
                <div v-if="isLoadingMenus" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="i in 6" :key="i" class="flex items-center gap-3 rounded-md border p-3 animate-pulse">
                        <div class="h-7 w-7 rounded bg-muted shrink-0"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-3 w-3/4 rounded bg-muted"></div>
                            <div class="h-2.5 w-1/2 rounded bg-muted"></div>
                        </div>
                        <div class="h-4 w-8 rounded bg-muted shrink-0"></div>
                    </div>
                </div>

                <div v-else-if="topMenusToday.length" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(m, idx) in topMenusToday"
                        :key="m.menu_id"
                        class="flex items-center gap-3 rounded-md border border-border/50 p-3 hover:bg-muted/30 transition-colors"
                    >
                        <div class="flex h-7 w-7 items-center justify-center rounded bg-muted/50 text-xs font-semibold text-muted-foreground shrink-0">
                            {{ idx + 1 }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium leading-tight truncate">{{ m.name }}</div>
                            <div class="text-xs text-muted-foreground truncate">
                                {{ m.parent_category_name ?? m.category_name ?? '' }}
                                <span v-if="m.cafe_name">· {{ m.cafe_name }}</span>
                            </div>
                        </div>
                        <div class="text-sm font-bold shrink-0 text-primary">{{ m.total_sold }}x</div>
                    </div>
                </div>

                <div v-else class="py-10 text-center text-sm text-muted-foreground">
                    Belum ada penjualan untuk filter ini.
                </div>
            </div>

            <!-- Top Menus + Critical Stock -->
            <div class="grid gap-4 lg:grid-cols-2">
                <TopMenus :menus="topMenus" />
                <CriticalStock :stocks="criticalStocks" />
            </div>

            <!-- Recent Transactions -->
            <RecentTransactions :transactions="recentTransactions" />

        </div>
    </AppLayout>
</template>
