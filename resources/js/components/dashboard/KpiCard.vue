<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    value: string;
    subtitle?: string;
    change?: number;
    icon: LucideIcon;
    iconClass?: string;
}>();

const changeLabel = computed(() => {
    if (props.change === undefined || props.change === null) return null;
    if (props.change > 0) return `+${props.change.toFixed(1)}%`;
    if (props.change < 0) return `${props.change.toFixed(1)}%`;
    return '0%';
});

const changeColor = computed(() => {
    if (!props.change) return 'text-muted-foreground';
    return props.change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
});
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">{{ title }}</CardTitle>
            <div :class="['p-2 rounded-lg', iconClass ?? 'bg-primary/10']">
                <component :is="icon" class="h-4 w-4 text-primary" />
            </div>
        </CardHeader>
        <CardContent>
            <div class="text-2xl font-bold">{{ value }}</div>
            <div class="mt-1 flex items-center gap-1 text-xs">
                <span v-if="changeLabel !== null" :class="changeColor">{{ changeLabel }}</span>
                <span class="text-muted-foreground">{{ subtitle }}</span>
            </div>
        </CardContent>
    </Card>
</template>
