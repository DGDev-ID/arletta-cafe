<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Pencil, Trash2, Power, PowerOff } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    promos: any;
    cafes: any[];
    filters: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Promo',
        href: '/master/cafe-promo',
    },
];

const deletePromo = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus promo ini?')) {
        router.delete(`/master/cafe-promo/${id}`);
    }
};

const toggleStatus = (id: number) => {
    if (confirm('Apakah Anda yakin ingin mengubah status promo ini?')) {
        router.patch(`/master/cafe-promo/${id}/toggle-status`);
    }
};

const search = ref(props.filters.search || '');
const selectedCafe = ref(props.filters.cafe_id || 'all');

let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/master/cafe-promo', { search: value, cafe_id: selectedCafe.value }, { preserveState: true, replace: true });
    }, 300);
});

watch(selectedCafe, (value) => {
    router.get('/master/cafe-promo', { search: search.value, cafe_id: value }, { preserveState: true, replace: true });
});

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Promo" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <Heading variant="small" title="Master Promo" description="Kelola daftar promo cafe." />

                    <Link href="/master/cafe-promo/create"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                        Tambah Promo
                    </Link>
                </div>

                <!-- Filters -->
                <div class="flex flex-col md:flex-row gap-4 mb-4">
                    <div class="w-full md:w-64">
                        <select v-model="selectedCafe" class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            <option value="all">Semua Cafe</option>
                            <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id.toString()">
                                {{ cafe.name }}
                            </option>
                        </select>
                    </div>
                    <div class="w-full md:w-64 relative">
                        <input v-model="search" placeholder="Cari Kode Promo..." class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                    </div>
                </div>

                <!-- Table Card -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Cafe</th>
                                <th class="px-6 py-4 text-left font-medium">Kode Promo</th>
                                <th class="px-6 py-4 text-left font-medium">Tipe</th>
                                <th class="px-6 py-4 text-left font-medium">Nilai</th>
                                <th class="px-6 py-4 text-center font-medium">Status</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(promo, index) in promos.data" :key="promo.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">
                                    {{ index + 1 + (promos.current_page - 1) * promos.per_page }}
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    {{ promo.cafe?.name || '-' }}
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    {{ promo.promo_code }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ promo.type === 'discount_percent' ? 'Persentase (%)' : 'Nominal Langsung' }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ promo.type === 'discount_percent' ? promo.value + '%' : 'Rp ' + Number(promo.value).toLocaleString('id-ID') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="promo.status" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Aktif</span>
                                    <span v-else class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Tidak Aktif</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-3">
                                        <button @click="toggleStatus(promo.id)" type="button"
                                            :class="promo.status ? 'bg-orange-100 text-orange-600 hover:bg-orange-500 hover:text-white' : 'bg-green-100 text-green-600 hover:bg-green-500 hover:text-white'"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md transition"
                                            :title="promo.status ? 'Nonaktifkan' : 'Aktifkan'">
                                            <Power v-if="!promo.status" :size="16" />
                                            <PowerOff v-else :size="16" />
                                        </button>
                                        <Link :href="`/master/cafe-promo/${promo.id}/edit`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                            title="Edit Promo">
                                            <Pencil :size="16" />
                                        </Link>
                                        <button @click="deletePromo(promo.id)" type="button"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                            title="Hapus Promo">
                                            <Trash2 :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="promos.data.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data promo.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 bg-background">
                        <Pagination :links="promos.links" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
