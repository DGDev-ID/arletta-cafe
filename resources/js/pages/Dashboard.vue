<script setup lang="ts">
import CriticalStock from '@/components/dashboard/CriticalStock.vue';
import KpiCard from '@/components/dashboard/KpiCard.vue';
import RecentTransactions from '@/components/dashboard/RecentTransactions.vue';
import RevenueChart from '@/components/dashboard/RevenueChart.vue';
import TopMenus from '@/components/dashboard/TopMenus.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Coffee, ShoppingCart, Utensils, Wallet, CalendarDays } from 'lucide-vue-next';
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
    };
    revenueChart: { date: string; revenue: number }[];
    topMenus: any[];
    criticalStocks: any[];
    recentTransactions: any[];
    topMenusToday: any[];
    parentCategories: { id: number; name: string }[];
}>();

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);

const percentChange = (current: number, previous: number) => {
    if (previous === 0) return current > 0 ? 100 : 0;
    return ((current - previous) / previous) * 100;
};

const revenueChange = computed(() => percentChange(props.stats.revenueToday, props.stats.revenueYesterday));
const txChange = computed(() => percentChange(props.stats.transactionsToday, props.stats.transactionsYesterday));

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
    pollInterval = window.setInterval(() => {
        // Only auto-poll if viewing today
        if (filterDate.value === todayStr) void fetchTopMenus();
    }, 60_000);
});

onBeforeUnmount(() => {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = undefined; }
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">

            <!-- KPI Cards -->
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

            <!-- Revenue Chart (full width) -->
            <RevenueChart :data="revenueChart" />

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
                                <span v-if="m.cafe_name"> · {{ m.cafe_name }}</span>
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
