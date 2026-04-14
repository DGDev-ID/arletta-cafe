<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

const props = defineProps<{
    data: { date: string; revenue: number }[];
}>();

const maxRevenue = computed(() => Math.max(...props.data.map((d) => d.revenue), 1));

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);

const formatShort = (value: number) => {
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}jt`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(0)}rb`;
    return value.toString();
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Revenue 7 Hari Terakhir</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="flex items-end gap-2 h-40">
                <div
                    v-for="(item, index) in data"
                    :key="index"
                    class="flex flex-1 flex-col items-center gap-1"
                >
                    <span class="text-[10px] font-medium text-muted-foreground leading-none">
                        {{ item.revenue > 0 ? formatShort(item.revenue) : '' }}
                    </span>
                    <div class="w-full rounded-t-md bg-primary/20 hover:bg-primary/40 transition-colors relative group" :style="{ height: `${(item.revenue / maxRevenue) * 128}px`, minHeight: '4px' }">
                        <!-- Tooltip -->
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs text-background shadow">
                            {{ formatCurrency(item.revenue) }}
                        </div>
                        <div v-if="item.revenue > 0" class="w-full h-full rounded-t-md bg-primary opacity-80"></div>
                    </div>
                    <span class="text-[10px] text-muted-foreground">{{ item.date }}</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
