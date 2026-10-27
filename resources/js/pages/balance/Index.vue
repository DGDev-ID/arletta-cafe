<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { ref, computed } from 'vue';
import { Wallet, ArrowUpRight, ArrowDownLeft, Clock, CheckCircle2, XCircle, CalendarDays, TrendingUp } from 'lucide-vue-next';

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
    if (status === 'success')  return { label: 'Berhasil',  cls: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' };
    if (status === 'failed')   return { label: 'Gagal',     cls: 'bg-red-500/15 text-red-400 border-red-500/30' };
    return                            { label: 'Menunggu',  cls: 'bg-amber-500/15 text-amber-400 border-amber-500/30' };
};
</script>

<template>
    <Head title="Balance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <Heading title="Balance" description="Kelola saldo dan riwayat topup" />

            <!-- Balance Card + Topup Form -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Balance Card -->
                <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-violet-600 via-purple-700 to-indigo-800 p-6 shadow-2xl">
                    <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/5"></div>
                    <div class="absolute -bottom-6 -left-6 h-32 w-32 rounded-full bg-white/5"></div>
                    <div class="relative">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur">
                                <Wallet class="h-5 w-5 text-white" />
                            </div>
                            <span class="text-sm font-medium text-white/70">Saldo Arletta</span>
                        </div>
                        <div class="text-3xl font-bold tracking-tight text-white">
                            {{ formatRupiah(balance.balance) }}
                        </div>
                        <p class="mt-2 text-xs text-white/50">Saldo tersedia untuk operasional café</p>
                    </div>
                </div>

                <!-- Topup Form -->
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <TrendingUp class="h-5 w-5 text-violet-400" />
                        <h3 class="font-semibold text-white">Topup Saldo</h3>
                    </div>
                    <form @submit.prevent="submitTopup" class="flex flex-col gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm text-white/70">Jumlah Topup (Rp)</label>
                            <input
                                v-model="form.amount"
                                id="topup-amount"
                                type="number"
                                min="10000"
                                step="1000"
                                placeholder="Minimum Rp 10.000"
                                required
                                class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/30 outline-none ring-0 transition focus:border-violet-500 focus:bg-white/10 focus:ring-1 focus:ring-violet-500"
                            />
                            <p v-if="form.errors.amount" class="mt-1 text-xs text-red-400">{{ form.errors.amount }}</p>
                        </div>
                        <button
                            id="btn-topup-submit"
                            type="submit"
                            :disabled="form.processing"
                            class="flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-3 font-semibold text-white transition hover:bg-violet-500 disabled:opacity-60"
                        >
                            <TrendingUp class="h-4 w-4" />
                            {{ form.processing ? 'Memproses...' : 'Topup Sekarang' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Topup History -->
            <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm">
                <div class="border-b border-white/10 px-6 py-4">
                    <h3 class="font-semibold text-white">Riwayat Topup</h3>
                </div>
                <div class="divide-y divide-white/5">
                    <div v-if="topups.data.length === 0" class="px-6 py-10 text-center text-sm text-white/40">
                        Belum ada riwayat topup.
                    </div>
                    <div
                        v-for="topup in topups.data"
                        :key="topup.id"
                        class="flex items-center justify-between px-6 py-4 transition hover:bg-white/5"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-500/20">
                                <ArrowUpRight class="h-4 w-4 text-violet-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white">{{ formatRupiah(topup.topup_amount) }}</p>
                                <p class="text-xs text-white/40">Total dibayar: {{ formatRupiah(topup.total_amount) }} • {{ formatDate(topup.created_at) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="statusBadge(topup.status).cls"
                            >
                                {{ statusBadge(topup.status).label }}
                            </span>
                            <a
                                v-if="topup.status === 'pending'"
                                :href="`/balance/topup/${topup.id}`"
                                class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-white/60 transition hover:border-violet-500/50 hover:text-violet-400"
                            >
                                Lihat QRIS
                            </a>
                        </div>
                    </div>
                </div>
                <div v-if="topups.data.length > 0" class="border-t border-white/10 px-6 py-4">
                    <Pagination :links="topups.links" />
                </div>
            </div>

            <!-- Balance Logs -->
            <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm">
                <div class="flex flex-col gap-3 border-b border-white/10 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="font-semibold text-white">Riwayat Saldo</h3>
                    <!-- Date Range Filter -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5">
                            <CalendarDays class="h-3.5 w-3.5 text-white/40" />
                            <input
                                v-model="dateFrom"
                                type="date"
                                id="log-date-from"
                                class="bg-transparent text-xs text-white/70 outline-none"
                                placeholder="Dari"
                            />
                        </div>
                        <span class="text-xs text-white/30">—</span>
                        <div class="flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5">
                            <CalendarDays class="h-3.5 w-3.5 text-white/40" />
                            <input
                                v-model="dateTo"
                                type="date"
                                id="log-date-to"
                                class="bg-transparent text-xs text-white/70 outline-none"
                                placeholder="Sampai"
                            />
                        </div>
                        <button
                            id="btn-filter-logs"
                            @click="applyLogFilter"
                            class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-violet-500"
                        >
                            Filter
                        </button>
                        <button
                            id="btn-reset-filter-logs"
                            @click="resetLogFilter"
                            class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-white/60 transition hover:border-white/20 hover:text-white"
                        >
                            Reset
                        </button>
                    </div>
                </div>
                <div class="divide-y divide-white/5">
                    <div v-if="logs.data.length === 0" class="px-6 py-10 text-center text-sm text-white/40">
                        Belum ada riwayat saldo.
                    </div>
                    <div
                        v-for="log in logs.data"
                        :key="log.id"
                        class="flex items-center justify-between px-6 py-4 transition hover:bg-white/5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl"
                                :class="log.type === 'in' ? 'bg-emerald-500/20' : 'bg-red-500/20'"
                            >
                                <ArrowUpRight v-if="log.type === 'in'" class="h-4 w-4 text-emerald-400" />
                                <ArrowDownLeft v-else class="h-4 w-4 text-red-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium" :class="log.type === 'in' ? 'text-emerald-400' : 'text-red-400'">
                                    {{ log.type === 'in' ? '+' : '-' }}{{ formatRupiah(log.amount) }}
                                </p>
                                <p class="text-xs text-white/40">
                                    {{ log.type === 'in' ? 'Masuk' : 'Keluar' }}
                                    <span v-if="log.transaction_id"> • Ref #{{ log.transaction_id }}</span>
                                    • {{ formatDate(log.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="logs.data.length > 0" class="border-t border-white/10 px-6 py-4">
                    <Pagination :links="logs.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
