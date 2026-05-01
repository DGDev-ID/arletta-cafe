<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Eye, Filter, Download, ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

interface Cafe {
    id: number;
    name: string;
}

interface Transaction {
    id: number;
    cafe_id: number;
    table_id: number | null;
    cust_name: string;
    price: string;
    fee: string;
    total_price: string;
    payment_type: string;
    status: string;
    updated_at: string;
    cafe: { id: number; name: string };
    table: { id: number; name: string } | null;
}

const props = defineProps<{
    data: any;
    cafes: Cafe[];
    filters: {
        cafe_id: string;
        payment_type: string;
        date_from: string;
        date_to: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'History Transaction', href: '/transaction/history' },
];

const showFilter = ref(
    !!(props.filters.cafe_id || props.filters.payment_type || props.filters.date_from || props.filters.date_to),
);
const showExportDropdown = ref(false);

const filterCafe = ref(props.filters.cafe_id);
const filterPaymentType = ref(props.filters.payment_type);
const filterDateFrom = ref(props.filters.date_from);
const filterDateTo = ref(props.filters.date_to);


const applyFilters = () => {
    const params: Record<string, string> = {};
    if (filterCafe.value) params.cafe_id = filterCafe.value;
    if (filterPaymentType.value) params.payment_type = filterPaymentType.value;
    if (filterDateFrom.value) params.date_from = filterDateFrom.value;
    if (filterDateTo.value) params.date_to = filterDateTo.value;
    router.get('/transaction/history', params, { preserveState: true });
};

const resetFilters = () => {
    filterCafe.value = '';
    filterPaymentType.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    router.get('/transaction/history', {}, { preserveState: true });
};

const exportXls = (withDetails: boolean) => {
    const params = new URLSearchParams();
    if (filterCafe.value) params.set('cafe_id', filterCafe.value);
    if (filterPaymentType.value) params.set('payment_type', filterPaymentType.value);
    if (filterDateFrom.value) params.set('date_from', filterDateFrom.value);
    if (filterDateTo.value) params.set('date_to', filterDateTo.value);
    if (withDetails) params.set('with_details', '1');

    window.location.href = `/transaction/history/export?${params.toString()}`;
    showExportDropdown.value = false;
};

const formatCurrency = (val: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));

const formatDate = (val: string) => {
    // If val is yyyy-mm-dd or ISO, format to dd/mm/yyyy
    const d = new Date(val);
    if (isNaN(d.getTime())) return val;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hour = String(d.getHours()).padStart(2, '0');
    const minute = String(d.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hour}:${minute}`;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="History Transaction" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <Heading variant="small" title="History Transaction"
                    description="Riwayat transaksi yang telah berhasil (success)." />

                <!-- Toolbar: Filter toggle + Export -->
                <div class="flex flex-wrap items-center gap-3">
                    <button @click="showFilter = !showFilter"
                        class="cursor-pointer inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-sm font-medium hover:bg-muted/60 transition"
                        :class="showFilter ? 'bg-muted border-border' : 'bg-background'">
                        <Filter :size="16" />
                        {{ showFilter ? 'Sembunyikan Filter' : 'Filter' }}
                    </button>

                    <div class="flex-1"></div>

                    <!-- Export Dropdown -->
                    <div class="relative">
                        <button @click="showExportDropdown = !showExportDropdown"
                            class="cursor-pointer inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary hover:opacity-90 text-sm font-medium text-white shadow-sm transition">
                            <Download :size="16" /> Export XLS
                            <ChevronDown :size="14" />
                        </button>
                        <Transition enter-active-class="transition ease-out duration-100"
                            enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75" leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95">
                            <div v-if="showExportDropdown"
                                class="absolute right-0 mt-2 w-56 rounded-xl border bg-popover text-popover-foreground shadow-lg z-30 overflow-hidden">
                                <button @click="exportXls(false)"
                                    class="cursor-pointer w-full px-4 py-2.5 text-sm text-left hover:bg-muted transition">
                                    Transaction Only
                                </button>
                                <button @click="exportXls(true)"
                                    class="cursor-pointer w-full px-4 py-2.5 text-sm text-left hover:bg-muted transition border-t">
                                    With Transaction Detail
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Filter Section (toggle) -->
                <Transition enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="showFilter" class="rounded-2xl border bg-background shadow-sm p-6 space-y-4">
                        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Filter</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Cafe -->
                            <div class="grid gap-1.5">
                                <label class="text-xs font-medium text-muted-foreground">Cafe</label>
                                <select v-model="filterCafe"
                                    class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                    <option value="">Semua Cafe</option>
                                    <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">{{ cafe.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Payment Type -->
                            <div class="grid gap-1.5">
                                <label class="text-xs font-medium text-muted-foreground">Payment Type</label>
                                <select v-model="filterPaymentType"
                                    class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                    <option value="">Semua Tipe</option>
                                    <option value="manual">Cash</option>
                                    <option value="qris">QRIS</option>
                                </select>
                            </div>

                            <!-- Date From -->
                            <div class="grid gap-1.5">
                                <label class="text-xs font-medium text-muted-foreground">Dari Tanggal</label>
                                <input v-model="filterDateFrom" type="date" lang="id-ID"
                                    class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>

                            <!-- Date To -->
                            <div class="grid gap-1.5">
                                <label class="text-xs font-medium text-muted-foreground">Sampai Tanggal</label>
                                <input v-model="filterDateTo" type="date" lang="id-ID"
                                    class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button @click="applyFilters"
                                class="cursor-pointer inline-flex items-center px-4 py-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition">
                                Terapkan Filter
                            </button>
                            <button @click="resetFilters"
                                class="cursor-pointer inline-flex items-center px-4 py-2 rounded-xl border text-sm font-medium hover:bg-muted/60 transition">
                                Reset
                            </button>
                        </div>
                    </div>
                </Transition>

                <!-- Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Cafe</th>
                                <th class="px-6 py-4 text-left font-medium">Customer Name</th>
                                <th class="px-6 py-4 text-left font-medium">Total Price</th>
                                <th class="px-6 py-4 text-left font-medium">Payment Type</th>
                                <th class="px-6 py-4 text-left font-medium">Tanggal</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(trx, index) in data.data" :key="trx.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>
                                <td class="px-6 py-4 font-medium">{{ trx.cafe?.name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ trx.cust_name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ formatCurrency(trx.total_price) }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                        :class="trx.payment_type === 'manual' ? 'bg-emerald-100 text-emerald-700' : 'bg-violet-100 text-violet-700'">
                                        {{ trx.payment_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">{{ formatDate(trx.updated_at) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="`/transaction/history/${trx.id}`"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition"
                                        title="Lihat Detail">
                                        <Eye :size="14" /> Detail
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="data.data.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data transaksi.
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
