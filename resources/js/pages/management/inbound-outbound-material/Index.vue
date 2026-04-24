<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { HelpCircle, Plus, Search, Pencil } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';

interface Cafe {
    id: number;
    name: string;
}

interface Material {
    id: number;
    name: string;
    cafe: Cafe;
}

interface TransactionDetail {
    id: number;
    menu: { id: number; name: string } | null;
    amount: number;
    price: string;
    description: string | null;
}

interface InboundOutbound {
    id: number;
    material_id: number;
    type: 'inbound' | 'outbound';
    amount: string;
    base_unit_id: number;
    inbound_buy_price: string | null;
    transaction_detail_id: number | null;
    material: Material;
    base_unit: { id: number; name: string };
    transaction_detail: TransactionDetail | null;
}

const props = defineProps<{
    data: any;
    cafes: Cafe[];
    materials: { id: number; name: string }[];
    filters: {
        cafe_id: string | null;
        material_id: string | null;
        type: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/management/inbound-outbound-material' },
];

const selectedCafe = ref(props.filters.cafe_id || '');
const selectedMaterial = ref(props.filters.material_id || '');
const selectedType = ref(props.filters.type || '');
const cafeSearch = ref('');
const materialSearch = ref('');

const cafeOptions = computed(() => {
    const defaultOpt = [{ value: '', label: 'Semua Cafe' }];
    return defaultOpt.concat(props.cafes.map(c => ({ value: c.id, label: c.name })));
});

const materialOptions = computed(() => {
    const defaultOpt = [{ value: '', label: 'Semua Material' }];
    return defaultOpt.concat(props.materials.map(m => ({ value: m.id, label: m.name })));
});

const typeOptions = [
    { value: '', label: 'Semua Tipe' },
    { value: 'inbound', label: 'Inbound' },
    { value: 'outbound', label: 'Outbound' }
];

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (selectedCafe.value) params.cafe_id = selectedCafe.value;
    if (selectedMaterial.value) params.material_id = selectedMaterial.value;
    if (selectedType.value) params.type = selectedType.value;
    router.get('/management/inbound-outbound-material', params, { preserveState: true });
};

watch(selectedCafe, (val) => {
    selectedMaterial.value = '';
    selectedType.value = '';
    applyFilters();
});

watch(selectedMaterial, (val) => {
    if (!val) selectedType.value = '';
    applyFilters();
});

watch(selectedType, () => {
    applyFilters();
});

const formatCurrency = (val: string | number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Purchase" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <Heading variant="small" title="Purchase"
                    description="Lihat riwayat inbound dan outbound material." />

                <!-- Filters + Add Button -->
                <div class="flex flex-wrap items-end gap-3">
                    <!-- Filter Cafe -->
                    <div class="grid gap-1.5 min-w-[200px]">
                        <label class="text-xs font-medium text-muted-foreground">Cafe</label>
                            <SearchableSelect
                                v-model="selectedCafe"
                                :options="cafeOptions"
                                placeholder="Semua Cafe"
                            />
                    </div>

                    <!-- Filter Material (only when cafe selected) -->
                    <div v-if="selectedCafe" class="grid gap-1.5 min-w-[200px]">
                        <label class="text-xs font-medium text-muted-foreground">Material</label>
                        <SearchableSelect
                            v-model="selectedMaterial"
                            :options="materialOptions"
                            placeholder="Semua Material"
                        />
                    </div>

                    <!-- Filter Type (only when material selected) -->
                    <div v-if="selectedMaterial" class="grid gap-1.5 min-w-[180px]">
                        <label class="text-xs font-medium text-muted-foreground">Tipe</label>
                        <SearchableSelect
                            v-model="selectedType"
                            :options="typeOptions"
                            placeholder="Semua Tipe"
                        />
                    </div>

                    <div class="flex-1"></div>

                    <!-- Add Inbound Button -->
                    <Link href="/management/inbound-outbound-material/create"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition">
                        <Plus :size="16" /> Tambah Inbound
                    </Link>

                    <!-- Add Outbound Button -->
                    <Link href="/management/inbound-outbound-material/create-outbound"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-600 text-sm font-medium text-white shadow-sm hover:opacity-90 transition">
                        <Plus :size="16" /> Tambah Spoil Stock (Outbound)
                    </Link>
                </div>

                <!-- Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Material</th>
                                <th class="px-6 py-4 text-left font-medium">Tipe</th>
                                <th class="px-6 py-4 text-left font-medium">Amount</th>
                                <th class="px-6 py-4 text-left font-medium">Unit</th>
                                <th class="px-6 py-4 text-left font-medium">Detail</th>
                                <th class="px-6 py-4 text-left font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in data.data" :key="row.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    {{ row.material.name }}
                                    <span class="text-muted-foreground text-xs">({{ row.material.cafe.name }})</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="row.type === 'inbound'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize">
                                        {{ row.type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">{{ row.amount }}</td>
                                <td class="px-6 py-4">{{ row.base_unit.name }}</td>
                                <td class="px-6 py-4">
                                    <!-- Outbound: show transaction detail on hover -->
                                    <div v-if="row.type === 'outbound' && row.transaction_detail"
                                        class="relative group inline-block">
                                        <span
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-600 cursor-help">
                                            <HelpCircle :size="14" />
                                        </span>
                                        <div
                                            class="absolute z-20 bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-56 bg-popover text-popover-foreground text-xs rounded-lg shadow-lg border p-3 space-y-1">
                                            <p><span class="font-semibold">Menu:</span> {{
                                                row.transaction_detail.menu?.name ?? '-' }}</p>
                                            <p><span class="font-semibold">Qty:</span> {{ row.transaction_detail.amount
                                            }}</p>
                                            <p><span class="font-semibold">Harga:</span> {{
                                                formatCurrency(row.transaction_detail.price) }}</p>
                                            <p v-if="row.transaction_detail.description"><span
                                                    class="font-semibold">Desc:</span> {{
                                                        row.transaction_detail.description }}</p>
                                        </div>
                                    </div>

                                    <!-- Inbound: show buy price on hover -->
                                    <div v-else-if="row.type === 'inbound' && row.inbound_buy_price"
                                        class="relative group inline-block">
                                        <span
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 text-green-600 cursor-help">
                                            <HelpCircle :size="14" />
                                        </span>
                                        <div
                                            class="absolute z-20 bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-48 bg-popover text-popover-foreground text-xs rounded-lg shadow-lg border p-3">
                                            <p><span class="font-semibold">Harga Beli:</span> {{
                                                formatCurrency(row.inbound_buy_price) }}</p>
                                        </div>
                                    </div>

                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Link v-if="row.transaction_detail_id === null"
                                            :href="`/management/inbound-outbound-material/${row.id}/edit`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-orange-100 text-orange-600 hover:bg-orange-200 transition"
                                            title="Edit">
                                            <Pencil :size="16" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="data.data.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data inbound / outbound.
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