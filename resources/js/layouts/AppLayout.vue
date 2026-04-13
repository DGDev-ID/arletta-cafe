<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Notyf } from 'notyf';
import { watch, onMounted } from 'vue';
import 'notyf/notyf.min.css';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const notyf = new Notyf({
    duration: 3000,
    position: { x: 'right', y: 'bottom' },
    ripple: true,
    dismissible: true,
});

const page = usePage();

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            notyf.success(flash.success);
        }
        if (flash?.error) {
            notyf.error(flash.error);
        }
    },
    { deep: true }
);

onMounted(() => {
    const flash = page.props.flash as any;
    if (flash?.success) notyf.success(flash.success);
    if (flash?.error) notyf.error(flash.error);
});
</script>

<template>
    <AppSidebarLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>