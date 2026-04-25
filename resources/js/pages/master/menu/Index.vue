<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Pencil, Trash2, ToggleLeft, ToggleRight, Search } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Menu',
        href: '/master/menu',
    },
];

const deleteMenu = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus menu ini?')) {
        router.delete(`/master/menu/${id}?page=${props.data.current_page}`);
    }
};

const toggleStatus = (id: number) => {
    router.patch(`/master/menu/${id}/toggle-status`);
};

const props = defineProps<{
    data: any;
    allCafe: any;
    search: string;
    status: string;
    cafe_id: string;
}>();

const selectedCafe = ref(props.cafe_id || '');
const searchQuery = ref(props.search);
const selectedStatus = ref(props.status || '');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (selectedCafe.value) params.cafe_id = selectedCafe.value;
    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedStatus.value) params.status = selectedStatus.value;
    router.get('/master/menu', params, { preserveState: true });
};

const filterByCafe = () => applyFilters();
const filterByStatus = () => applyFilters();

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

const formatPrice = (price: string | number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(price));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Menu" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex-col">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <Heading variant="small" title="Master Menu"
                            description="Kelola daftar menu untuk setiap cafe." />

                        <Link :href="`/master/menu/create?page=${data.current_page}`"
                            class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                            Tambah Menu
                        </Link>
                    </div>

                    <!-- Filter Cafe + Search -->
                    <div class="flex flex-col mt-4 sm:flex-row gap-3 w-full items-center justify-between">
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
                                <select v-model="selectedStatus" @change="filterByStatus"
                                    class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="">Semua Status</option>
                                    <option value="available">Available</option>
                                    <option value="unavailable">Unavailable</option>
                                </select>
                            </div>
                        </div>
                        <div class="relative flex-1 max-w-sm w-full">
                            <Search :size="16"
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="searchQuery" type="text"
                                placeholder="Cari nama menu..."
                                class="w-full pl-10 pr-4 h-10 rounded-xl border border-input bg-background text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
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
                                <th class="px-6 py-4 text-left font-medium">Nama Menu</th>
                                <th class="px-6 py-4 text-left font-medium">Deskripsi</th>
                                <th class="px-6 py-4 text-left font-medium">Harga</th>
                                <th class="px-6 py-4 text-left font-medium">Status</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(menu, index) in data.data" :key="menu.id"
                                class="border-t hover:bg-muted/40 transition">
                                <!-- No -->
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>

                                <!-- Cafe Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ menu.cafe.name }}
                                </td>

                                <!-- Menu Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ menu.name }}
                                </td>

                                <!-- Description -->
                                <td class="px-6 py-4">
                                    {{ menu.description ?? '-' }}
                                </td>

                                <!-- Price -->
                                <td class="px-6 py-4 font-medium">
                                    {{ formatPrice(menu.price) }}
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4">
                                    <span :class="menu.status === 'available'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ menu.status === 'available' ? 'Available' : 'Unavailable' }}
                                    </span>
                                </td>

                                <!-- Action -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-3">

                                        <!-- Toggle Status -->
                                        <button @click="toggleStatus(menu.id)" type="button" :class="menu.status === 'available'
                                            ? 'bg-green-100 text-green-600 hover:bg-green-500 hover:text-white'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-500 hover:text-white'"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md transition"
                                            :title="menu.status === 'available' ? 'Set Unavailable' : 'Set Available'">
                                            <ToggleRight v-if="menu.status === 'available'" :size="16" />
                                            <ToggleLeft v-else :size="16" />
                                        </button>

                                        <!-- Edit -->
                                        <Link :href="`/master/menu/${menu.id}/edit?page=${data.current_page}`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                            title="Edit Menu">
                                            <Pencil :size="16" />
                                        </Link>

                                        <!-- Delete -->
                                        <button @click="deleteMenu(menu.id)" type="button"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                            title="Hapus Menu">
                                            <Trash2 :size="16" />
                                        </button>

                                    </div>
                                </td>
                            </tr>

                            <!-- Empty -->
                            <tr v-if="data.data.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data menu.
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
