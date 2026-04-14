<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ClipboardList } from 'lucide-vue-next';

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
    <Card>
        <CardHeader class="flex flex-row items-center gap-2 pb-3">
            <ClipboardList class="h-4 w-4 text-primary" />
            <CardTitle class="text-base">Transaksi Terbaru</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <div v-if="transactions.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                Belum ada transaksi
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 text-xs text-muted-foreground">
                            <th class="px-4 py-2.5 text-left font-medium">#</th>
                            <th class="px-4 py-2.5 text-left font-medium">Cafe</th>
                            <th class="px-4 py-2.5 text-left font-medium">Meja</th>
                            <th class="px-4 py-2.5 text-left font-medium">Total</th>
                            <th class="px-4 py-2.5 text-left font-medium">Pembayaran</th>
                            <th class="px-4 py-2.5 text-left font-medium">Status</th>
                            <th class="px-4 py-2.5 text-left font-medium">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="trx in transactions" :key="trx.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">#{{ trx.id }}</td>
                            <td class="px-4 py-2.5">{{ trx.cafe?.name ?? '-' }}</td>
                            <td class="px-4 py-2.5">{{ trx.table?.name ?? '-' }}</td>
                            <td class="px-4 py-2.5 font-semibold">{{ formatCurrency(trx.total_price) }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs">
                                    {{ paymentLabel(trx.payment_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold', statusClass(trx.status)]">
                                    {{ statusLabel(trx.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ formatDate(trx.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
