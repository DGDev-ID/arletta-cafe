<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex items-center">
        <div class="flex aspect-square size-8 items-center justify-center rounded-md text-sidebar-primary-foreground">
            <img src="/logo_v2.png" alt="Arletta Cafe" class="w-25 h-25" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate font-semibold leading-tight">Arletta Cafe</span>
        </div>
    </div>
</template>
