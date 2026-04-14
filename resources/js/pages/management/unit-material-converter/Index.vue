<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Eye, Search } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Unit Material Converter', href: '/management/unit-material-converter' },
];

defineProps<{
    data: any;
    search: string;
}>();

const searchQuery = ref('');

const filterSearch = () => {
    router.get('/management/unit-material-converter', { search: searchQuery.value }, { preserveState: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Unit Material Converter" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <Heading variant="small" title="Unit Material Converter"
                    description="Kelola data konversi satuan untuk setiap material." />

                <!-- Search -->
                <div class="flex items-center gap-3">
                    <div class="relative flex-1 max-w-md">
                        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="searchQuery" @keydown.enter="filterSearch" type="text"
                            placeholder="Cari berdasarkan nama material atau cafe..."
                            class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                    </div>
                    <button @click="filterSearch"
                        class="cursor-pointer inline-flex items-center px-4 py-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition">
                        Cari
                    </button>
                </div>

                <!-- Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Nama Material</th>
                                <th class="px-6 py-4 text-left font-medium">Base Unit</th>
                                <th class="px-6 py-4 text-left font-medium">Jumlah Konversi</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(material, index) in data.data" :key="material.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    {{ material.name }}
                                    <span class="text-muted-foreground text-xs">({{ material.cafe.name }})</span>
                                </td>
                                <td class="px-6 py-4">{{ material.base_unit.name }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        {{ material.converters_count }} konversi
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="`/management/unit-material-converter/${material.id}`"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition"
                                        title="Lihat & Kelola Converter">
                                        <Eye :size="14" /> Lihat & Kelola
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="data.data.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data material.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="p-4 bg-background">
                        <Pagination :links="data.links" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
