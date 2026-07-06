<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Pencil, Trash2, Power, PowerOff } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    banners: any;
    filters: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Banner Promo',
        href: '/master/promo-banner',
    },
];

const search = ref(props.filters.search || '');

let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/master/promo-banner', { search: value }, { preserveState: true, replace: true });
    }, 300);
});

const deleteBanner = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus banner ini?')) {
        router.delete(`/master/promo-banner/${id}`);
    }
};

const toggleStatus = (id: number) => {
    if (confirm('Apakah Anda yakin ingin mengubah status banner ini?')) {
        router.patch(`/master/promo-banner/${id}/toggle-status`);
    }
};

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Banner Promo" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <Heading variant="small" title="Master Banner Promo"
                        description="Kelola banner promo yang ditampilkan di halaman menu per cabang cafe." />

                    <Link href="/master/promo-banner/create"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                        Tambah Banner
                    </Link>
                </div>

                <!-- Filters -->
                <div class="flex flex-col md:flex-row gap-4 mb-4">
                    <div class="w-full md:w-64 relative">
                        <input v-model="search" placeholder="Cari judul banner..."
                            class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                    </div>
                </div>

                <!-- Table Card -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">Gambar</th>
                                <th class="px-6 py-4 text-left font-medium">Judul</th>
                                <th class="px-6 py-4 text-left font-medium">Cafe</th>
                                <th class="px-6 py-4 text-left font-medium">Periode</th>
                                <th class="px-6 py-4 text-center font-medium">Status</th>
                                <th class="px-6 py-4 text-center font-medium">Urutan</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="banner in banners.data" :key="banner.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">
                                    <img :src="banner.image_url" :alt="banner.title"
                                        class="h-16 w-28 rounded-lg object-cover border shadow-sm" />
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    {{ banner.title }}
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="banner.cafes && banner.cafes.length > 0" class="flex flex-wrap gap-1">
                                        <span
                                            v-for="cafe in banner.cafes"
                                            :key="cafe.id"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700"
                                        >
                                            {{ cafe.name }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs italic text-muted-foreground">Tidak ada cafe</span>
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    <template v-if="banner.start_date || banner.end_date">
                                        {{ formatDate(banner.start_date) }} — {{ formatDate(banner.end_date) }}
                                    </template>
                                    <span v-else class="text-xs italic">Selalu aktif</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="banner.is_active"
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        Aktif
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        Tidak Aktif
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center font-mono">
                                    {{ banner.sort_order }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-3">
                                        <button @click="toggleStatus(banner.id)" type="button"
                                            :class="banner.is_active ? 'bg-orange-100 text-orange-600 hover:bg-orange-500 hover:text-white' : 'bg-green-100 text-green-600 hover:bg-green-500 hover:text-white'"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md transition"
                                            :title="banner.is_active ? 'Nonaktifkan' : 'Aktifkan'">
                                            <Power v-if="!banner.is_active" :size="16" />
                                            <PowerOff v-else :size="16" />
                                        </button>
                                        <Link :href="`/master/promo-banner/${banner.id}/edit`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                            title="Edit Banner">
                                            <Pencil :size="16" />
                                        </Link>
                                        <button @click="deleteBanner(banner.id)" type="button"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                            title="Hapus Banner">
                                            <Trash2 :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="banners.data.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data banner promo.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 bg-background">
                        <Pagination :links="banners.links" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
