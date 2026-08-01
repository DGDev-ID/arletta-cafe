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
    PackagePlus, PackageMinus, TrendingUp, TrendingDown, Download,
    Star, MessageSquare
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
        paymentStats: Record<string, { count: number; revenue: number }>;
    };
    revenueChart: { date: string; revenue: number }[];
    topMenus: any[];
    criticalStocks: any[];
    recentTransactions: any[];
    topMenusToday: any[];
    parentCategories: { id: number; name: string }[];
    cafes: { id: number; name: string }[];
    activeCafeId: number | null;
    recentFeedbacks: Array<{
        id: number;
        rating: number;
        comment: string | null;
        created_at: string;
        transaction: { cust_name: string | null; cafe: { name: string } | null; table: { name: string } | null } | null;
    }>;
    averageRating: number | null;
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

// ── Produk Terjual ────────────────────────────────────────────────────────────
const todayStr = new Date().toISOString().slice(0, 10);
const filterDateFrom = ref(todayStr);
const filterDateTo   = ref(todayStr);
const filterCategoryId = ref<number | null>(null);
const isLoadingMenus = ref(false);
const isExportingMenus = ref(false);

const topMenusToday = ref(props.topMenusToday ?? []);
const topMenusTotalSold = computed(() => topMenusToday.value.reduce((sum, item) => sum + (Number(item.total_sold) || 0), 0));
const topMenusTotalRevenue = computed(() => topMenusToday.value.reduce((sum, item) => sum + (Number(item.total_revenue) || 0), 0));

async function fetchTopMenus() {
    try {
        isLoadingMenus.value = true;
        const params: Record<string, string> = {
            date_from: filterDateFrom.value,
            date_to:   filterDateTo.value,
        };
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

async function exportTopMenus() {
    try {
        isExportingMenus.value = true;
        const params = new URLSearchParams({
            date_from: filterDateFrom.value,
            date_to:   filterDateTo.value,
        });
        if (filterCategoryId.value !== null) params.set('category_id', String(filterCategoryId.value));
        if (selectedCafeId.value !== null) params.set('cafe_id', String(selectedCafeId.value));
        window.location.href = `/dashboard/top-menus-export?${params.toString()}`;
    } finally {
        setTimeout(() => { isExportingMenus.value = false; }, 1500);
    }
}

// Refetch on filter change
watch([filterDateFrom, filterDateTo, filterCategoryId], () => fetchTopMenus());

let pollInterval: number | undefined;

onMounted(() => {
    void fetchTopMenus();
    void fetchPurchaseSummary();
    pollInterval = window.setInterval(() => {
        // Only auto-poll if viewing today range
        if (filterDateFrom.value === todayStr && filterDateTo.value === todayStr) void fetchTopMenus();
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
type PaymentPeriod = 'day' | 'week' | 'month' | 'year';
const paymentPeriod      = ref<PaymentPeriod>('day');
const paymentFilterDate  = ref(todayStr);                              // YYYY-MM-DD
const paymentFilterWeek  = ref(getCurrentISOWeek());                   // YYYY-Www
const paymentFilterMonth = ref(todayStr.slice(0, 7));                  // YYYY-MM
const paymentFilterYear  = ref(new Date().getFullYear());              // number
const isLoadingPayment   = ref(false);
const paymentStats = ref<Record<string, { count: number; revenue: number }> | null>(null);

// Helper: dapatkan ISO week string (YYYY-Www) untuk hari ini
function getCurrentISOWeek(): string {
    const now = new Date();
    const year = now.getFullYear();
    // Cari Kamis di minggu yang sama (ISO 8601: minggu dimulai Senin)
    const thu = new Date(now);
    thu.setDate(now.getDate() - ((now.getDay() + 6) % 7) + 3);
    const jan4 = new Date(year, 0, 4);
    const weekNum = Math.round(((thu.getTime() - jan4.getTime()) / 86400000 + ((jan4.getDay() + 6) % 7)) / 7) + 1;
    const isoYear = thu.getFullYear();
    return `${isoYear}-W${String(weekNum).padStart(2, '0')}`;
}

// Helper: tampilkan label rentang minggu dari nilai YYYY-Www
function getWeekLabel(weekVal: string): string {
    // Parse YYYY-Www
    const match = weekVal.match(/^(\d{4})-W(\d{2})$/);
    if (!match) return weekVal;
    const year = parseInt(match[1]);
    const week = parseInt(match[2]);
    // Cari Senin minggu ke-n (ISO week: Kamis di minggu itu ada di tahun itu)
    const jan4 = new Date(year, 0, 4);
    const mondayW1 = new Date(jan4);
    mondayW1.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7));
    const monday = new Date(mondayW1);
    monday.setDate(mondayW1.getDate() + (week - 1) * 7);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    const fmt = (d: Date) => d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    return `${fmt(monday)} – ${fmt(sunday)} ${year}`;
}

// Seed awal dari props (hari ini)
function seedPaymentStats() {
    paymentStats.value = JSON.parse(JSON.stringify(props.stats.paymentStats));
}
seedPaymentStats();

async function fetchPaymentStats() {
    try {
        isLoadingPayment.value = true;
        const params: Record<string, string> = { period: paymentPeriod.value };
        if (selectedCafeId.value !== null) params.cafe_id = String(selectedCafeId.value);
        if (paymentPeriod.value === 'day')   params.date  = paymentFilterDate.value;
        if (paymentPeriod.value === 'week')  params.week  = paymentFilterWeek.value;
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

// Daftar tahun tersedia (6 tahun ke belakang sampai sekarang)
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
    if (paymentPeriod.value === 'week') {
        return getWeekLabel(paymentFilterWeek.value);
    }
    // day
    const d = new Date(paymentFilterDate.value + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
});

watch([paymentPeriod, paymentFilterDate, paymentFilterWeek, paymentFilterMonth, paymentFilterYear, selectedCafeId], () => fetchPaymentStats());

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

const isExportingPurchase = ref(false);

async function exportPurchaseSummary() {
    try {
        isExportingPurchase.value = true;
        const params = new URLSearchParams({
            date_from: toApiDate(purchaseDateFrom.value),
            date_to:   toApiDate(purchaseDateTo.value),
        });
        if (selectedCafeId.value !== null) params.set('cafe_id', String(selectedCafeId.value));
        window.location.href = `/dashboard/purchase-summary-export?${params.toString()}`;
    } finally {
        setTimeout(() => { isExportingPurchase.value = false; }, 1500);
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
                        <!-- Toggle mode: Hari / Minggu / Bulan / Tahun -->
                        <div class="flex items-center gap-1 rounded-lg border bg-muted p-0.5 self-start sm:self-auto">
                            <button
                                v-for="opt in ([{ value: 'day', label: 'Hari' }, { value: 'week', label: 'Minggu' }, { value: 'month', label: 'Bulan' }, { value: 'year', label: 'Tahun' }] as const)"
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
                        <!-- Mode Minggu: week picker -->
                        <div v-else-if="paymentPeriod === 'week'" class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Minggu:</span>
                            <input
                                v-model="paymentFilterWeek"
                                type="week"
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
                            <div class="text-xl font-bold leading-tight truncate">{{ formatCurrency(paymentStats.qris?.revenue ?? 0) }}</div>
                            <div class="text-xs text-muted-foreground">{{ paymentStats.qris?.count ?? 0 }} transaksi</div>
                        </div>
                    </div>
                    <!-- Debit -->
                    <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <CreditCard class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-muted-foreground">Debit</div>
                            <div class="text-xl font-bold leading-tight truncate">{{ formatCurrency(paymentStats.debit?.revenue ?? 0) }}</div>
                            <div class="text-xs text-muted-foreground">{{ paymentStats.debit?.count ?? 0 }} transaksi</div>
                        </div>
                    </div>
                    <!-- Manual / Cash -->
                    <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                            <Banknote class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-muted-foreground">Manual / Cash</div>
                            <div class="text-xl font-bold leading-tight truncate">{{ formatCurrency(paymentStats.manual?.revenue ?? 0) }}</div>
                            <div class="text-xs text-muted-foreground">{{ paymentStats.manual?.count ?? 0 }} transaksi</div>
                        </div>
                    </div>
                    <!-- Pihak Ketiga (Third Party Channels) -->
                    <template v-for="channel in Object.keys(paymentStats).filter(k => !['qris', 'debit', 'manual'].includes(k))" :key="channel">
                        <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30">
                                <Store class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-medium text-muted-foreground">{{ channel }}</div>
                                <div class="text-xl font-bold leading-tight truncate">{{ formatCurrency(paymentStats[channel]?.revenue ?? 0) }}</div>
                                <div class="text-xs text-muted-foreground">{{ paymentStats[channel]?.count ?? 0 }} transaksi</div>
                            </div>
                        </div>
                    </template>
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
                                :max="purchaseDateTo"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Sampai:</span>
                            <input
                                v-model="purchaseDateTo"
                                type="date"
                                :min="purchaseDateFrom"
                                :max="todayStr"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                        <!-- Export Excel button -->
                        <button
                            @click="exportPurchaseSummary"
                            :disabled="isExportingPurchase || isLoadingPurchase || !purchaseSummary || (purchaseSummary.inbound.total_records === 0 && purchaseSummary.outbound.total_records === 0)"
                            class="flex items-center gap-1.5 rounded-md bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-3 py-1.5 text-xs font-medium transition-colors"
                        >
                            <Download class="h-3.5 w-3.5 shrink-0" />
                            {{ isExportingPurchase ? 'Mengunduh...' : 'Export Excel' }}
                        </button>
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
                        <!-- Date range filter -->
                        <div class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Dari:</span>
                            <input
                                v-model="filterDateFrom"
                                type="date"
                                :max="filterDateTo"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1.5 text-xs">
                            <CalendarDays class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Sampai:</span>
                            <input
                                v-model="filterDateTo"
                                type="date"
                                :min="filterDateFrom"
                                :max="todayStr"
                                class="bg-transparent text-xs outline-none cursor-pointer"
                            />
                        </div>
                        <!-- Export Excel button -->
                        <button
                            @click="exportTopMenus"
                            :disabled="isExportingMenus || isLoadingMenus || topMenusToday.length === 0"
                            class="flex items-center gap-1.5 rounded-md bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-3 py-1.5 text-xs font-medium transition-colors"
                        >
                            <Download class="h-3.5 w-3.5 shrink-0" />
                            {{ isExportingMenus ? 'Mengunduh...' : 'Export Excel' }}
                        </button>
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

                <!-- Summary Totals -->
                <div v-if="!isLoadingMenus && topMenusToday.length > 0" class="flex flex-wrap items-center gap-4 rounded-md border border-border/50 bg-muted/20 px-4 py-3">
                    <div class="flex flex-col">
                        <span class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">Total Item Terjual</span>
                        <span class="text-base font-bold text-primary">{{ topMenusTotalSold }}x</span>
                    </div>
                    <div class="h-8 w-px bg-border/60 hidden sm:block"></div>
                    <div class="flex flex-col">
                        <span class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">Total Pendapatan</span>
                        <span class="text-base font-bold text-emerald-600 dark:text-emerald-500">{{ formatCurrency(topMenusTotalRevenue) }}</span>
                    </div>
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
                        <div class="text-right shrink-0">
                            <div class="text-sm font-bold text-primary">{{ m.total_sold }}x</div>
                            <div v-if="m.total_revenue" class="text-[11px] font-semibold text-muted-foreground">{{ formatCurrency(m.total_revenue) }}</div>
                        </div>
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

            <!-- ── Widget Ulasan Pelanggan ──────────────────────────────── -->
            <div class="rounded-2xl border bg-card overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b">
                    <div class="flex items-center gap-2">
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                        <h3 class="text-sm font-semibold text-foreground">Ulasan Pelanggan Terbaru</h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Average rating badge -->
                        <div v-if="averageRating" class="flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1">
                            <Star class="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                            <span class="text-sm font-bold text-amber-600">{{ averageRating }}</span>
                            <span class="text-xs text-amber-500">/ 5</span>
                        </div>
                        <a
                            href="/master/customer-feedback"
                            class="text-xs font-medium text-primary hover:underline transition-colors"
                        >Lihat Semua →</a>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="!recentFeedbacks || recentFeedbacks.length === 0" class="py-10 text-center">
                    <MessageSquare class="w-10 h-10 text-muted-foreground/30 mx-auto mb-2" />
                    <p class="text-sm text-muted-foreground">Belum ada ulasan dari pelanggan</p>
                </div>

                <!-- Feedback list -->
                <div v-else class="divide-y">
                    <div
                        v-for="fb in recentFeedbacks"
                        :key="fb.id"
                        class="flex items-start gap-3 px-5 py-3.5 hover:bg-muted/20 transition-colors"
                    >
                        <!-- Star badge -->
                        <div class="flex items-center gap-1 shrink-0 rounded-lg bg-amber-50 border border-amber-200 px-2 py-1">
                            <Star class="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                            <span class="text-xs font-bold text-amber-600">{{ fb.rating }}</span>
                        </div>
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold text-foreground">
                                    {{ fb.transaction?.cust_name || 'Pelanggan Anonim' }}
                                </span>
                                <span v-if="fb.transaction?.cafe" class="text-xs text-muted-foreground">
                                    · {{ fb.transaction.cafe.name }}
                                </span>
                                <span v-if="fb.transaction?.table" class="text-xs text-muted-foreground">
                                    · Meja {{ fb.transaction.table.name }}
                                </span>
                            </div>
                            <p v-if="fb.comment" class="text-xs text-foreground/70 leading-relaxed line-clamp-2">
                                "{{ fb.comment }}"
                            </p>
                            <p v-else class="text-xs text-muted-foreground italic">Tidak ada komentar</p>
                        </div>
                        <!-- Date -->
                        <span class="text-xs text-muted-foreground shrink-0">
                            {{ new Date(fb.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
