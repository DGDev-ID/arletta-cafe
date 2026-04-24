<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Logs, Pencil, Trash2 } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Material',
        href: '/master/material',
    },
];

const deleteMaterial = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus material ini?')) {
        router.delete(`/master/material/${id}`);
    }
};

const props = defineProps<{
    data: any;
    allCafe: any;
    search?: string;
    cafe_id?: string;
    stock_status?: string;
}>();

const selectedCafe = ref(props.cafe_id || '');
const searchQuery = ref(props.search || '');
const selectedStockStatus = ref(props.stock_status || '');

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (selectedCafe.value) params.cafe_id = selectedCafe.value;
    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedStockStatus.value) params.stock_status = selectedStockStatus.value;
    router.get('/master/material', params, { preserveState: true });
};

const filterByCafe = () => applyFilters();
const filterByStockStatus = () => applyFilters();
const searchMaterial = () => applyFilters();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Material" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex-col">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <Heading variant="small" title="Master Material"
                            description="Kelola daftar material, unit, dan stok material." />

                        <Link href="/master/material/create"
                            class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                            Tambah Material
                        </Link>
                    </div>

                    <!-- Filter Cafe & Stock -->
                    <div class="flex flex-col mt-4 sm:flex-row gap-4 w-full justify-between items-center">
                        <div class="flex gap-3 w-full sm:w-auto flex-col sm:flex-row">
                            <div class="w-full sm:w-48">
                                <select v-model="selectedCafe" @change="filterByCafe"
                                    class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="">All Cafes</option>
                                    <option v-for="cafe in allCafe" :key="cafe.id" :value="cafe.id">
                                        {{ cafe.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="w-full sm:w-48">
                                <select v-model="selectedStockStatus" @change="filterByStockStatus"
                                    class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="">Semua Stok</option>
                                    <option value="empty">Stok Kosong</option>
                                </select>
                            </div>
                        </div>
                        <div class="w-full sm:max-w-sm">
                            <input v-model="searchQuery" @keyup.enter="searchMaterial" type="text" placeholder="Cari nama material..."
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
                                <th class="px-6 py-4 text-left font-medium">Nama Material</th>
                                <th class="px-6 py-4 text-left font-medium">Base Unit</th>
                                <th class="px-6 py-4 text-left font-medium">Stok</th>
                                <th class="px-6 py-4 text-left font-medium">Stok Kritis</th>
                                <th class="px-6 py-4 text-left font-medium">Rata-Rata Harga Beli</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(material, index) in data.data" :key="material.id"
                                class="border-t hover:bg-muted/40 transition">
                                <!-- No -->
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>

                                <!-- Cafe Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ material.cafe.name }}
                                </td>

                                <!-- Material Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ material.name }}
                                </td>

                                <!-- Base Unit -->
                                <td class="px-6 py-4 font-medium">
                                    {{ material.base_unit.name }}
                                </td>

                                <!-- Stock -->
                                <td class="px-6 py-4 font-medium">
                                    <span :class="Number(material.stock) <= Number(material.critical_stock) && Number(material.critical_stock) > 0 ? 'text-red-600 font-bold' : ''">
                                        {{ material.stock }}
                                    </span>
                                </td>

                                <!-- Critical Stock -->
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ material.critical_stock }}
                                </td>

                                <!-- Average Purchase Price -->
                                <td class="px-6 py-4 font-medium">
                                    {{ material.avg_buy_price }}
                                </td>

                                <!-- Action -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-3">

                                        <!-- Edit -->
                                        <Link :href="`/master/material/${material.id}/edit`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                            title="Edit Material">
                                            <Pencil :size="16" />
                                        </Link>

                                        <!-- Log Inbound Outbound Material -->
                                        <Link :href="`/master/material/${material.id}`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-blue-100 text-blue-600 hover:bg-blue-500 hover:text-white transition"
                                            title="Log Inbound Outbound Material">
                                            <Logs :size="16" />
                                        </Link>

                                        <!-- Delete -->
                                        <button @click="deleteMaterial(material.id)" type="button"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                            title="Hapus Material">
                                            <Trash2 :size="16" />
                                        </button>

                                    </div>
                                </td>
                            </tr>

                            <!-- Empty -->
                            <tr v-if="data.data.length === 0">
                                <td colspan="8" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data cafe.
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