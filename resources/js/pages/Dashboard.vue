<script setup lang="ts">
import CriticalStock from '@/components/dashboard/CriticalStock.vue';
import KpiCard from '@/components/dashboard/KpiCard.vue';
import RecentTransactions from '@/components/dashboard/RecentTransactions.vue';
import RevenueChart from '@/components/dashboard/RevenueChart.vue';
import TopMenus from '@/components/dashboard/TopMenus.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Coffee, ShoppingCart, Utensils, Wallet } from 'lucide-vue-next';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
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
}>();

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);

const percentChange = (current: number, previous: number) => {
    if (previous === 0) return current > 0 ? 100 : 0;
    return ((current - previous) / previous) * 100;
};

const revenueChange = computed(() => percentChange(props.stats.revenueToday, props.stats.revenueYesterday));
const txChange = computed(() => percentChange(props.stats.transactionsToday, props.stats.transactionsYesterday));

const topMenusToday = ref(props.topMenusToday ?? []);

let pollInterval: number | undefined;

onMounted(() => {
    const intervalMs = 60_000; // 60 seconds

    const poll = async () => {
        try {
            if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;
            const { data } = await axios.get('/dashboard/top-menus-today');
            topMenusToday.value = data;
        } catch (e) {
            // silent
            // console.error('Failed to poll top menus today', e);
        }
    };

    // run once immediately to pick up any changes since initial render
    void poll();

    pollInterval = window.setInterval(() => void poll(), intervalMs);
});

onBeforeUnmount(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = undefined;
    }
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

            <!-- Revenue Chart + Produk Terjual -->
            <div class="grid gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <RevenueChart :data="revenueChart" />
                </div>

                <div class="lg:col-span-2 rounded-lg border bg-card p-4 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <Coffee class="h-4 w-4 text-primary" />
                            <span class="text-sm font-medium text-muted-foreground">Produk Terjual Hari Ini</span>
                        </div>
                        <span v-if="topMenusToday.length" class="text-xs text-muted-foreground">{{ topMenusToday.length }} produk</span>
                    </div>

                    <div class="flex flex-col gap-2 overflow-y-auto max-h-52 pr-1">
                        <template v-if="topMenusToday && topMenusToday.length">
                            <div
                                v-for="(m, idx) in topMenusToday"
                                :key="m.menu_id"
                                class="flex items-center justify-between py-1.5 border-b border-border/40 last:border-0"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-7 w-7 items-center justify-center rounded bg-muted/10 text-xs font-semibold text-muted-foreground shrink-0">{{ idx + 1 }}</div>
                                    <div>
                                        <div class="text-sm font-medium leading-tight">{{ m.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ m.cafe_name ?? '' }}</div>
                                    </div>
                                </div>
                                <div class="text-sm font-semibold shrink-0 ml-2">{{ m.total_sold }}x</div>
                            </div>
                        </template>
                        <div v-else class="text-sm text-muted-foreground py-4 text-center">Belum ada penjualan hari ini</div>
                    </div>
                </div>

                <!-- Table Occupancy Card
                <div class="flex flex-col gap-4">
                    <div class="rounded-lg border bg-card p-5 shadow-sm flex flex-col justify-between h-full">
                        <div class="flex items-center gap-2 mb-4">
                            <Coffee class="h-4 w-4 text-primary" />
                            <span class="text-base font-semibold">Occupancy Meja</span>
                        </div>
                        <div class="flex flex-col items-center justify-center flex-1 gap-2">
                            <div class="relative flex h-28 w-28 items-center justify-center">
                                <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-width="10" class="text-muted/30" />
                                    <circle
                                        cx="50" cy="50" r="40" fill="none"
                                        stroke="currentColor" stroke-width="10"
                                        class="text-primary transition-all"
                                        stroke-linecap="round"
                                        :stroke-dasharray="`${stats.totalTables > 0 ? (stats.occupiedTables / stats.totalTables) * 251.2 : 0} 251.2`"
                                    />
                                </svg>
                                <span class="absolute text-2xl font-bold">
                                    {{ stats.totalTables > 0 ? Math.round((stats.occupiedTables / stats.totalTables) * 100) : 0 }}%
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground text-center">
                                {{ stats.occupiedTables }} dari {{ stats.totalTables }} meja sedang digunakan
                            </p>
                        </div>
                    </div>
                </div> -->
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
