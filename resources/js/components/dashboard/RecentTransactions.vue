<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, ClipboardList } from 'lucide-vue-next';

defineProps<{
    transactions: {
        id: number;
        total_price: string;
        payment_type: string;
        status: string;
        created_at: string;
        cafe: { name: string } | null;
        table: { name: string } | null;
    }[];
}>();

const statusClass = (status: string) => {
    switch (status) {
        case 'success':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
        case 'pending':
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'in_order':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'failed':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        default:
            return 'bg-muted text-muted-foreground';
    }
};

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        success: 'Sukses',
        pending: 'Pending',
        in_order: 'Sedang Order',
        failed: 'Gagal',
    };
    return map[status] ?? status;
};

const paymentLabel = (type: string) => (type === 'qris' ? 'QRIS' : 'Tunai');

const formatCurrency = (value: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(value));

const formatDate = (value: string) => {
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="flex flex-row items-center justify-between pb-4 border-b border-border">
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                    <ClipboardList class="h-4 w-4 text-primary" />
                </div>
                <CardTitle class="text-base font-semibold">Transaksi Terbaru</CardTitle>
            </div>
            <Link
                href="/transaction/history"
                class="inline-flex items-center gap-1 rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground"
            >
                Lihat Semua
                <ArrowUpRight class="h-3.5 w-3.5" />
            </Link>
        </CardHeader>
        <CardContent class="p-0">
            <div v-if="transactions.length === 0" class="flex flex-col items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
                <ClipboardList class="h-8 w-8 opacity-30" />
                <span>Belum ada transaksi</span>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-muted/50 text-xs text-muted-foreground uppercase tracking-wide">
                            <th class="px-5 py-3 text-left font-medium">ID</th>
                            <th class="px-5 py-3 text-left font-medium">Cafe / Meja</th>
                            <th class="px-5 py-3 text-left font-medium">Total</th>
                            <th class="px-5 py-3 text-left font-medium">Pembayaran</th>
                            <th class="px-5 py-3 text-left font-medium">Status</th>
                            <th class="px-5 py-3 text-left font-medium">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(trx, idx) in transactions"
                            :key="trx.id"
                            :class="[
                                'border-b border-border/50 transition-colors hover:bg-muted/40',
                                idx % 2 === 0 ? 'bg-background' : 'bg-muted/20',
                            ]"
                        >
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs font-semibold text-muted-foreground">#{{ trx.id }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="leading-tight">
                                    <p class="font-medium text-foreground">{{ trx.cafe?.name ?? '-' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ trx.table?.name ?? '-' }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold tabular-nums text-foreground">{{ formatCurrency(trx.total_price) }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center rounded-md border border-border bg-background px-2 py-0.5 text-xs font-medium">
                                    {{ paymentLabel(trx.payment_type) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', statusClass(trx.status)]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                    {{ statusLabel(trx.status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-muted-foreground whitespace-nowrap">{{ formatDate(trx.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="transactions.length > 0" class="flex items-center justify-between border-t border-border px-5 py-3 bg-muted/20">
                <p class="text-xs text-muted-foreground">Menampilkan {{ transactions.length }} transaksi terbaru</p>
                <Link
                    href="/transaction/history"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    Lihat semua <ArrowUpRight class="h-3 w-3" />
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
