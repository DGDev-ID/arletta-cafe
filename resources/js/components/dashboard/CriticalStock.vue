<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertTriangle } from 'lucide-vue-next';

defineProps<{
    stocks: {
        id: number;
        name: string;
        stock: string;
        avg_buy_price: string;
        cafe: { name: string } | null;
        base_unit: { name: string } | null;
    }[];
}>();

const stockLevel = (stock: string) => {
    const val = parseFloat(stock);
    if (val <= 0) return 'danger';
    if (val < 5) return 'warning';
    return 'low';
};

const stockBadgeClass = (stock: string) => {
    const level = stockLevel(stock);
    if (level === 'danger') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    if (level === 'warning') return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
    return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
};
</script>

<template>
    <Card class="h-full">
        <CardHeader class="flex flex-row items-center gap-2 pb-3">
            <AlertTriangle class="h-4 w-4 text-orange-500" />
            <CardTitle class="text-base">Stok Kritis Bahan Baku</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="stocks.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                Semua stok dalam kondisi aman
            </div>
            <ul v-else class="divide-y divide-border">
                <li v-for="material in stocks" :key="material.id" class="flex items-center justify-between py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ material.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ material.cafe?.name ?? '-' }}</p>
                    </div>
                    <div class="ml-4 flex-shrink-0 text-right">
                        <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold', stockBadgeClass(material.stock)]">
                            {{ parseFloat(material.stock) }} {{ material.base_unit?.name }}
                        </span>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
