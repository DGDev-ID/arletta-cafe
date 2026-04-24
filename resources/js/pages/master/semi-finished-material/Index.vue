<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Pencil, Trash2 } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Production',
        href: '/master/semi-finished-material',
    },
];

const deleteSfm = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus Production ini?')) {
        router.delete(`/master/semi-finished-material/${id}`);
    }
};

defineProps<{
    data: any;
    allCafe: any;
    search: string;
}>();

const selectedCafe = ref('');
const searchQuery = ref('');

const filterByCafe = () => {
    router.get('/master/semi-finished-material', { cafe_id: selectedCafe.value, search: searchQuery.value }, { preserveState: true });
};

const searchSfm = () => {
    router.get('/master/semi-finished-material', { cafe_id: selectedCafe.value, search: searchQuery.value }, { preserveState: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Production" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex-col">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <Heading variant="small" title="Production"
                            description="Kelola daftar bahan setengah jadi (bundling material)." />

                        <Link href="/master/semi-finished-material/create"
                            class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                            Tambah Production
                        </Link>
                    </div>

                    <!-- Filter Cafe + Search -->
                    <div class="flex flex-col mt-4 sm:flex-row gap-4 w-full md:flex-1 justify-between">
                        <div class="w-full sm:w-48">
                            <select v-model="selectedCafe" @change="filterByCafe"
                                class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                <option value="">All Cafes</option>
                                <option v-for="cafe in allCafe" :key="cafe.id" :value="cafe.id">
                                    {{ cafe.name }}
                                </option>
                            </select>
                        </div>
                        <div class="w-full sm:w-64">
                            <input v-model="searchQuery" @keyup.enter="searchSfm" type="text" placeholder="Cari nama..."
                                class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" />
                        </div>
                    </div>
                </div>


                <!-- Table Card -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Nama Cafe</th>
                                <th class="px-6 py-4 text-left font-medium">Nama</th>
                                <th class="px-6 py-4 text-left font-medium">Jumlah Material</th>
                                <th class="px-6 py-4 text-left font-medium">Satuan</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(sfm, index) in data.data" :key="sfm.id"
                                class="border-t hover:bg-muted/40 transition">
                                <!-- No -->
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>

                                <!-- Cafe Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ sfm.cafe?.name ?? '-' }}
                                </td>

                                <!-- Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ sfm.name }}
                                </td>

                                <!-- Detail Count -->
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ sfm.details_count ?? 0 }} material
                                </td>

                                <!-- Satuan -->
                                <td class="px-6 py-4 font-medium">
                                    {{ sfm.unit?.name ?? '-' }}
                                </td>

                                <!-- Action -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-3">

                                        <!-- Edit -->
                                        <Link :href="`/master/semi-finished-material/${sfm.id}/edit`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                            title="Edit">
                                            <Pencil :size="16" />
                                        </Link>

                                        <!-- Delete -->
                                        <button @click="deleteSfm(sfm.id)" type="button"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                            title="Hapus">
                                            <Trash2 :size="16" />
                                        </button>

                                    </div>
                                </td>
                            </tr>

                            <!-- Empty -->
                            <tr v-if="data.data.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data Production.
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
