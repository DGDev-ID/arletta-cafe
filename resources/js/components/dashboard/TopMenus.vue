<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    menus: {
        menu_id: number;
        total_sold: number;
        total_revenue: number;
        menu: { id: number; name: string; price: string; cafe: { name: string } | null } | null;
    }[];
}>();

const maxSold = computed(() => Math.max(...props.menus.map((m) => Number(m.total_sold)), 1));

const formatCurrency = (value: number | string) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(value));
</script>

<template>
    <Card class="h-full">
        <CardHeader class="flex flex-row items-center gap-2 pb-3">
            <TrendingUp class="h-4 w-4 text-primary" />
            <CardTitle class="text-base">Top Menu Terlaris</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="menus.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                Belum ada data transaksi
            </div>
            <ul v-else class="space-y-3">
                <li v-for="(item, index) in menus" :key="item.menu_id" class="flex items-center gap-3">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                        {{ index + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between text-sm">
                            <span class="truncate font-medium">{{ item.menu?.name ?? `Menu #${item.menu_id}` }}</span>
                            <span class="ml-2 flex-shrink-0 text-xs font-semibold text-primary">{{ item.total_sold }}x</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${(Number(item.total_sold) / maxSold) * 100}%` }"></div>
                        </div>
                        <div class="mt-0.5 flex justify-between text-[11px] text-muted-foreground">
                            <span>{{ item.menu?.cafe?.name }}</span>
                            <span>{{ formatCurrency(item.total_revenue) }}</span>
                        </div>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
