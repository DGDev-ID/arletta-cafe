<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
  links: Array<{ url: string | null; label: string; active: boolean }>;
}>();

const formatLabel = (label: string) => {
  if (label.includes('Previous')) return '‹';
  if (label.includes('Next')) return '›';
  if (label === '...') return '...';
  return label.replace(/&laquo;|&raquo;/g, '');
};
</script>

<template>
  <div v-if="links.length > 3" class="flex justify-end">
    <nav class="inline-flex rounded-md shadow-sm -space-x-px">
      
      <template v-for="(link, key) in links" :key="key">
        
        <!-- Disabled -->
        <span
          v-if="link.url === null"
          class="px-3 py-2 text-sm text-gray-400 bg-gray-100 border border-gray-300 cursor-not-allowed"
        >
          {{ formatLabel(link.label) }}
        </span>

        <!-- Active & Normal -->
        <Link
          v-else
          :href="link.url"
          class="px-3 py-2 text-sm border border-gray-300"
          :class="{
            'z-10 bg-primary border-primary text-white': link.active,
            'bg-white text-gray-700 hover:bg-gray-100': !link.active
          }"
        >
          {{ formatLabel(link.label) }}
        </Link>

      </template>

    </nav>
  </div>
</template>