<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Coffee } from 'lucide-vue-next';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex items-center">
        <div class="aspect-square size-8 flex items-center justify-center rounded-md text-sidebar-primary-foreground">
            <img src="/logo.svg" alt="Arletta Cafe" class="w-25 h-25" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">Arletta Cafe</span>
        </div>
    </div>
</template>
