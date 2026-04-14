<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Search, UserMinus } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';
import { ref, watch } from 'vue';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manage Cashier', href: '/user-management/cashier' },
];

defineProps<{
    data: any;
    search: string;
}>();

const searchQuery = ref('');

const filterSearch = () => {
    router.get('/user-management/cashier', { search: searchQuery.value }, { preserveState: true });
};

const revokeAccess = (userId: number) => {
    if (confirm('Apakah Anda yakin ingin mencabut akses Cashier dari user ini?')) {
        router.delete(`/user-management/cashier/${userId}/revoke`);
    }
};

// --- Assign user section ---
const assignSearch = ref('');
const assignResults = ref<{ id: number; name: string; email: string }[]>([]);
const assignLoading = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(assignSearch, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (!val || val.length < 2) {
        assignResults.value = [];
        return;
    }
    debounceTimer = setTimeout(async () => {
        assignLoading.value = true;
        try {
            const { data } = await axios.get('/user-management/cashier/search-users', { params: { q: val } });
            assignResults.value = data;
        } finally {
            assignLoading.value = false;
        }
    }, 300);
});

const assignUser = (userId: number) => {
    router.post('/user-management/cashier/assign', { user_id: userId }, {
        onSuccess: () => {
            assignSearch.value = '';
            assignResults.value = [];
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Manage Cashier" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex lex-row items-center justify-between gap-4">
                    <!-- Header -->
                    <Heading variant="small" title="Manage Cashier"
                        description="Kelola daftar user dengan role Cashier." />

                    <!-- Search Filter -->
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-md">
                            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="searchQuery" @keydown.enter="filterSearch" type="text"
                                placeholder="Cari cashier berdasarkan nama atau email..."
                                class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                        <button @click="filterSearch"
                            class="cursor-pointer inline-flex items-center px-4 py-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition">
                            Cari
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Nama User</th>
                                <th class="px-6 py-4 text-left font-medium">Email</th>
                                <th class="px-6 py-4 text-left font-medium">Plot Cafe</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(user, index) in data.data" :key="user.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>
                                <td class="px-6 py-4 font-medium">{{ user.name }}</td>
                                <td class="px-6 py-4">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <div v-if="user.cafe_cashiers && user.cafe_cashiers.length > 0"
                                        class="flex flex-wrap gap-1.5">
                                        <span v-for="cc in user.cafe_cashiers" :key="cc.id"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                            {{ cc.cafe?.name ?? '-' }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="revokeAccess(user.id)" type="button"
                                        class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-100 text-red-600 text-xs font-medium hover:bg-red-600 hover:text-white transition"
                                        title="Copot Akses">
                                        <UserMinus :size="14" /> Copot Akses
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="data.data.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada user dengan role Cashier.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="p-4 bg-background">
                        <Pagination :links="data.links" />
                    </div>
                </div>

                <!-- Assign User Section -->
                <div class="rounded-2xl border bg-background shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Tambah Cashier</h3>
                    <p class="text-sm text-muted-foreground">Cari user berdasarkan nama atau email untuk memberikan
                        akses Cashier.</p>

                    <div class="relative max-w-md">
                        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="assignSearch" type="text" placeholder="Ketik nama atau email user..."
                            class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                    </div>

                    <div v-if="assignLoading" class="text-sm text-muted-foreground">Mencari...</div>

                    <div v-if="assignResults.length > 0" class="rounded-xl border divide-y max-w-md">
                        <button v-for="user in assignResults" :key="user.id" @click="assignUser(user.id)" type="button"
                            class="cursor-pointer flex items-center justify-between w-full px-4 py-3 text-sm hover:bg-muted/50 transition text-left">
                            <div>
                                <div class="font-medium">{{ user.name }}</div>
                                <div class="text-muted-foreground text-xs">{{ user.email }}</div>
                            </div>
                            <span class="text-xs text-primary font-medium">Assign</span>
                        </button>
                    </div>

                    <div v-if="assignSearch.length >= 2 && !assignLoading && assignResults.length === 0"
                        class="text-sm text-muted-foreground">
                        Tidak ditemukan user yang cocok.
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
