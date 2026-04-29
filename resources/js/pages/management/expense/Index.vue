<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Notyf } from 'notyf';

interface Cafe { id: number; name: string }
interface MenuItem { id: number; name: string; price: number; available: boolean }

const props = defineProps<{ cafes: Cafe[]; expenses: any[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Expense', href: '/management/expense' },
];

const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'bottom' } });

// --- create form state (moved from Create.vue) ---
const selectedCafe = ref<number | ''>('');
const menus = ref<MenuItem[]>([]);
const loadingMenus = ref(false);
const checkingAvailability = ref(false);

const form = useForm({
    cafe_id: '' as number | '',
    details: [] as Array<{ menu_id: number; amount: number }>,
});

const cafeOptions = computed(() => props.cafes.map(c => ({ value: c.id, label: c.name })));

watch(selectedCafe, async (val) => {
    menus.value = [];
    form.details = [];
    form.cafe_id = val || '';

    if (!val) return;

    loadingMenus.value = true;
    try {
        const { data } = await axios.get('/management/expense/menus-by-cafe', { params: { cafe_id: val } });
        menus.value = data;
    } finally {
        loadingMenus.value = false;
    }
});

const addMenuToDetails = async (menu: MenuItem) => {
    const updated = form.details.map(d => ({ menu_id: d.menu_id, quantity: d.amount }));
    const found = updated.find(u => u.menu_id === menu.id);
    if (found) {
        found.quantity += 1;
    } else {
        updated.push({ menu_id: menu.id, quantity: 1 });
    }

    checkingAvailability.value = true;
    try {
        const { data } = await axios.post('/management/expense/check-availability', { items: updated });
        const unavailable = data.items.filter((i: any) => !i.available).map((i: any) => i.menu_id);
        if (unavailable.length > 0) {
            const names = unavailable.map((id: number) => (menus.value.find(m => m.id === id)?.name || `#${id}`));
            notyf.error('Stok tidak cukup untuk: ' + names.join(', '));
            return;
        }

        const existing = form.details.find(d => d.menu_id === menu.id);
        if (existing) {
            existing.amount += 1;
        } else {
            form.details.push({ menu_id: menu.id, amount: 1 });
        }
    } catch (e: any) {
        console.error(e);
        notyf.error(e?.response?.data?.message || 'Gagal memeriksa ketersediaan.');
    } finally {
        checkingAvailability.value = false;
    }
};

const removeDetail = (menuId: number) => {
    form.details = form.details.filter(d => d.menu_id !== menuId);
};

const increment = async (menuId: number) => {
    const d = form.details.find(x => x.menu_id === menuId);
    if (!d) return;

    const updated = form.details.map(x => ({ menu_id: x.menu_id, quantity: x.amount }));
    const target = updated.find(u => u.menu_id === menuId)!;
    target.quantity += 1;

    checkingAvailability.value = true;
    try {
        const { data } = await axios.post('/management/expense/check-availability', { items: updated });
        const unavailable = data.items.filter((i: any) => !i.available).map((i: any) => i.menu_id);
        if (unavailable.length > 0) {
            const names = unavailable.map((id: number) => (menus.value.find(m => m.id === id)?.name || `#${id}`));
            notyf.error('Stok tidak cukup untuk: ' + names.join(', '));
            return;
        }

        d.amount += 1;
    } catch (e: any) {
        console.error(e);
        notyf.error(e?.response?.data?.message || 'Gagal memeriksa ketersediaan.');
    } finally {
        checkingAvailability.value = false;
    }
};

const decrement = (menuId: number) => {
    const d = form.details.find(x => x.menu_id === menuId);
    if (d && d.amount > 1) d.amount -= 1;
};

const selectedMenuMap = computed(() => {
    const map: Record<number, any> = {};
    menus.value.forEach(m => map[m.id] = m);
    return map;
});

const totalPrice = computed(() => {
    return form.details.reduce((sum: number, d: any) => {
        const menu = selectedMenuMap.value[d.menu_id];
        return sum + (menu ? menu.price * d.amount : 0);
    }, 0);
});

const submit = () => {
    form.post('/management/expense', {
        preserveState: true,
        onSuccess: () => {
            notyf.success('Pengeluaran berhasil dibuat.');
            // reset form and UI state
            form.reset();
            selectedCafe.value = '';
            menus.value = [];
            // reload page so expenses table updates
            window.location.reload();
        },
        onError: (errors: any) => {
            notyf.error('Gagal membuat pengeluaran.');
        }
    });
};

// --- quick mark-by-id (kept) ---
const transactionId = ref('');
const loading = ref(false);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Expense" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div>
                    <div class="flex items-center justify-between mb-6">
                        <Heading variant="small" title="Expense" description="Buat pengeluaran dengan memilih menu dan jumlahnya. Stok akan dikurangi sesuai kebutuhan." />
                    </div>

                    <div class="rounded-2xl border bg-background shadow-sm p-8">
                        <form @submit.prevent="submit" class="grid gap-6">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium leading-none">Cafe</label>
                                <SearchableSelect v-model="selectedCafe" :options="cafeOptions" placeholder="Pilih Cafe" required />
                            </div>

                            <div>
                                <label class="text-sm font-medium leading-none">Menu</label>
                                <div v-if="loadingMenus" class="text-sm text-muted-foreground">Memuat menu...</div>
                                <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">
                                    <div v-for="menu in menus" :key="menu.id" class="p-3 rounded-lg border bg-white">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="font-medium">{{ menu.name }}</div>
                                                <div class="text-sm text-muted-foreground">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(menu.price) }}</div>
                                            </div>
                                            <div class="text-right">
                                                <button type="button" :disabled="!menu.available || checkingAvailability" @click="addMenuToDetails(menu)" class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-primary text-white text-sm">
                                                    Tambah
                                                </button>
                                                <div v-if="!menu.available" class="text-xs text-red-600 mt-1">Stok tidak cukup</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.details.length > 0" class="rounded-lg border p-4 bg-muted/5">
                                <table class="min-w-full text-sm">
                                    <thead class="text-muted-foreground text-left">
                                        <tr>
                                            <th class="py-2">Menu</th>
                                            <th class="py-2">Jumlah</th>
                                            <th class="py-2">Subtotal</th>
                                            <th class="py-2">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="d in form.details" :key="d.menu_id" class="border-t">
                                            <td class="py-2 font-medium">{{ selectedMenuMap[d.menu_id]?.name }}</td>
                                            <td class="py-2">
                                                <div class="inline-flex items-center gap-2">
                                                    <button type="button" @click="decrement(d.menu_id)" class="px-2 py-1 rounded bg-gray-100">-</button>
                                                    <div class="px-3">{{ d.amount }}</div>
                                                    <button type="button" @click="increment(d.menu_id)" class="px-2 py-1 rounded bg-gray-100">+</button>
                                                </div>
                                            </td>
                                            <td class="py-2">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format((selectedMenuMap[d.menu_id]?.price || 0) * d.amount) }}</td>
                                            <td class="py-2"><button type="button" @click="removeDetail(d.menu_id)" class="text-sm text-red-600">Hapus</button></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="1" class="py-3 text-right font-medium">Total</td>
                                            <td class="py-3 text-right font-semibold">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(totalPrice) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center px-6 py-2.5 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition disabled:opacity-50" :disabled="form.processing || form.details.length === 0">
                                    {{ form.processing ? 'Menyimpan...' : 'Simpan Pengeluaran' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div>
                    <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-muted-foreground">
                                    <th class="px-6 py-4 text-left font-medium">ID</th>
                                    <th class="px-6 py-4 text-left font-medium">Kode</th>
                                    <th class="px-6 py-4 text-left font-medium">Cafe</th>
                                    <!-- <th class="px-6 py-4 text-left font-medium">Meja</th> -->
                                    <th class="px-6 py-4 text-left font-medium">Nama</th>
                                    <th class="px-6 py-4 text-left font-medium">Total</th>
                                    <th class="px-6 py-4 text-left font-medium">Profit</th>
                                    <th class="px-6 py-4 text-left font-medium">Status</th>
                                    <th class="px-6 py-4 text-left font-medium">Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in props.expenses" :key="e.id" class="border-t hover:bg-muted/40 transition">
                                    <td class="px-6 py-4">{{ e.id }}</td>
                                    <td class="px-6 py-4 font-medium">{{ e.unique_code }}</td>
                                    <td class="px-6 py-4">{{ e.cafe?.name ?? e.cafe_id }}</td>
                                    <!-- <td class="px-6 py-4">{{ e.table?.id ?? e.table_id }}</td> -->
                                    <td class="px-6 py-4">{{ e.cust_name }}</td>
                                    <td class="px-6 py-4 font-medium">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(e.total_price) }}</td>
                                    <td class="px-6 py-4">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(e.profit_margin || 0) }}</td>
                                    <td class="px-6 py-4">
                                        <span :class="e.status === 'success' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                            {{ e.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">{{ new Date(e.created_at).toLocaleString() }}</td>
                                </tr>
                                <tr v-if="!props.expenses || props.expenses.length === 0">
                                    <td colspan="9" class="px-6 py-10 text-center text-muted-foreground">Belum ada pengeluaran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
