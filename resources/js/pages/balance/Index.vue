<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { ref } from 'vue';
import { Wallet, ArrowUpRight, ArrowDownLeft, CalendarDays, TrendingUp, CheckCircle2, XCircle, Clock } from 'lucide-vue-next';

interface Balance {
    id: number;
    balance: string;
}

interface BalanceTopup {
    id: number;
    topup_amount: string;
    unique_amount: string;
    total_amount: string;
    qris_history_id: number | null;
    status: 'pending' | 'success' | 'failed';
    created_at: string;
}

interface BalanceLog {
    id: number;
    type: 'in' | 'out';
    amount: string;
    transaction_id: number | null;
    created_at: string;
}

const props = defineProps<{
    balance: Balance;
    topups: { data: BalanceTopup[]; links: any[]; meta: any };
    logs: { data: BalanceLog[]; links: any[]; meta: any };
    filters: { date_from: string; date_to: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Balance', href: '/balance' },
];

// Topup form
const form = useForm({
    amount: '' as string | number,
});

const submitTopup = () => {
    form.post('/balance/topup');
};

// Filter date range for logs
const dateFrom = ref(props.filters.date_from);
const dateTo   = ref(props.filters.date_to);

const applyLogFilter = () => {
    router.get('/balance', {
        date_from: dateFrom.value || undefined,
        date_to:   dateTo.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const resetLogFilter = () => {
    dateFrom.value = '';
    dateTo.value   = '';
    router.get('/balance', {}, { preserveState: true, preserveScroll: true });
};

const formatRupiah = (val: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));

const formatDate = (val: string) => {
    return new Date(val).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const statusBadge = (status: string) => {
    if (status === 'success') return { label: 'Berhasil', cls: 'bg-green-100 text-green-700', icon: CheckCircle2 };
    if (status === 'failed')  return { label: 'Gagal',    cls: 'bg-red-100 text-red-700',   icon: XCircle };
    return                           { label: 'Menunggu', cls: 'bg-yellow-100 text-yellow-700', icon: Clock };
};
</script>

<template>
    <Head title="Balance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-muted/40 py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 md:px-6">

                <Heading title="Balance" description="Kelola saldo dan riwayat topup" />

                <!-- Balance Card + Topup Form -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                    <!-- Balance Card -->
                    <div class="flex flex-col justify-between rounded-2xl border bg-primary p-6 shadow-sm text-primary-foreground">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-foreground/20">
                                <Wallet class="h-5 w-5" />
                            </div>
                            <span class="text-sm font-medium opacity-80">Saldo Arletta</span>
                        </div>
                        <div>
                            <div class="text-3xl font-bold tracking-tight">
                                {{ formatRupiah(balance.balance) }}
                            </div>
                            <p class="mt-2 text-xs opacity-60">Saldo tersedia untuk operasional café</p>
                        </div>
                    </div>

                    <!-- Topup Form -->
                    <div class="rounded-2xl border bg-background p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <TrendingUp class="h-5 w-5 text-primary" />
                            <h3 class="font-semibold text-foreground">Topup Saldo</h3>
                        </div>
                        <form @submit.prevent="submitTopup" class="flex flex-col gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-foreground">Jumlah Topup (Rp)</label>
                                <input
                                    v-model="form.amount"
                                    id="topup-amount"
                                    type="number"
                                    min="10000"
                                    step="1000"
                                    placeholder="Minimum Rp 10.000"
                                    required
                                    class="w-full rounded-lg border border-input bg-background px-4 py-2.5 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                                <p v-if="form.errors.amount" class="mt-1 text-xs text-red-500">{{ form.errors.amount }}</p>
                            </div>
                            <button
                                id="btn-topup-submit"
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-60"
                            >
                                <TrendingUp class="h-4 w-4" />
                                {{ form.processing ? 'Memproses...' : 'Topup Sekarang' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Topup History -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <div class="border-b px-6 py-4">
                        <h3 class="font-semibold text-foreground">Riwayat Topup</h3>
                    </div>
                    <div class="divide-y">
                        <div v-if="topups.data.length === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
                            Belum ada riwayat topup.
                        </div>
                        <div
                            v-for="topup in topups.data"
                            :key="topup.id"
                            class="flex items-center justify-between px-6 py-4 transition hover:bg-muted/40"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10">
                                    <ArrowUpRight class="h-4 w-4 text-primary" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-foreground">{{ formatRupiah(topup.topup_amount) }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Total dibayar: {{ formatRupiah(topup.total_amount) }} • {{ formatDate(topup.created_at) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusBadge(topup.status).cls"
                                >
                                    <component :is="statusBadge(topup.status).icon" class="h-3 w-3" />
                                    {{ statusBadge(topup.status).label }}
                                </span>
                                <a
                                    v-if="topup.status === 'pending'"
                                    :href="`/balance/topup/${topup.id}`"
                                    class="rounded-lg border border-input px-3 py-1.5 text-xs text-muted-foreground transition hover:border-primary hover:text-primary"
                                >
                                    Lihat QRIS
                                </a>
                            </div>
                        </div>
                    </div>
                    <div v-if="topups.data.length > 0" class="border-t px-6 py-4">
                        <Pagination :links="topups.links" />
                    </div>
                </div>

                <!-- Balance Logs -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-3 border-b px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="font-semibold text-foreground">Riwayat Saldo</h3>
                        <!-- Date Range Filter -->
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex items-center gap-1.5 rounded-lg border border-input bg-background px-3 py-1.5">
                                <CalendarDays class="h-3.5 w-3.5 text-muted-foreground" />
                                <input
                                    v-model="dateFrom"
                                    type="date"
                                    id="log-date-from"
                                    class="bg-transparent text-xs text-foreground outline-none"
                                    placeholder="Dari"
                                />
                            </div>
                            <span class="text-xs text-muted-foreground">—</span>
                            <div class="flex items-center gap-1.5 rounded-lg border border-input bg-background px-3 py-1.5">
                                <CalendarDays class="h-3.5 w-3.5 text-muted-foreground" />
                                <input
                                    v-model="dateTo"
                                    type="date"
                                    id="log-date-to"
                                    class="bg-transparent text-xs text-foreground outline-none"
                                    placeholder="Sampai"
                                />
                            </div>
                            <button
                                id="btn-filter-logs"
                                @click="applyLogFilter"
                                class="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition hover:opacity-90"
                            >
                                Filter
                            </button>
                            <button
                                id="btn-reset-filter-logs"
                                @click="resetLogFilter"
                                class="rounded-lg border border-input px-3 py-1.5 text-xs text-muted-foreground transition hover:border-primary hover:text-primary"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                    <div class="divide-y">
                        <div v-if="logs.data.length === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
                            Belum ada riwayat saldo.
                        </div>
                        <div
                            v-for="log in logs.data"
                            :key="log.id"
                            class="flex items-center justify-between px-6 py-4 transition hover:bg-muted/40"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl"
                                    :class="log.type === 'in' ? 'bg-green-100' : 'bg-red-100'"
                                >
                                    <ArrowUpRight v-if="log.type === 'in'" class="h-4 w-4 text-green-600" />
                                    <ArrowDownLeft v-else class="h-4 w-4 text-red-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium" :class="log.type === 'in' ? 'text-green-600' : 'text-red-600'">
                                        {{ log.type === 'in' ? '+' : '-' }}{{ formatRupiah(log.amount) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ log.type === 'in' ? 'Masuk' : 'Keluar' }}
                                        <span v-if="log.transaction_id"> • Ref #{{ log.transaction_id }}</span>
                                        • {{ formatDate(log.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="logs.data.length > 0" class="border-t px-6 py-4">
                        <Pagination :links="logs.links" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
