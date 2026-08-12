<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { ref, watch, computed } from 'vue';
import { ChevronLeft, Save } from 'lucide-vue-next';

interface Cafe {
    id: number;
    name: string;
}

interface Menu {
    id: number;
    name: string;
    category_name: string;
    price: string | number;
    admin_fee: string | number | null;
    is_manual_price: boolean;
    override_price: string | number | null;
}

const props = defineProps<{
    channel: { id: number; name: string };
    cafes: Cafe[];
    menus: Menu[];
    activeCafeId: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Saluran Pihak Ketiga', href: '/master/third-party-channel' },
    { title: 'Kelola Menu', href: '#' },
];

const formatCurrency = (val: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));

const selectedCafeId = ref<number | ''>(props.activeCafeId || '');

// Local state for editing admin fees before saving
const editableMenus = ref<Menu[]>([]);

watch(() => props.menus, (newMenus) => {
    // Clone to make it deeply reactive for v-model
    editableMenus.value = newMenus.map(m => ({ ...m }));
}, { immediate: true });

const searchQuery = ref('');

const filteredMenus = computed(() => {
    if (!searchQuery.value) return editableMenus.value;
    const q = searchQuery.value.toLowerCase();
    return editableMenus.value.filter(m => m.name.toLowerCase().includes(q) || m.category_name.toLowerCase().includes(q));
});

const currentPage = ref(1);
const itemsPerPage = 10;

watch([searchQuery, selectedCafeId], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => Math.ceil(filteredMenus.value.length / itemsPerPage));

const paginatedMenus = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredMenus.value.slice(start, end);
});

const changeCafe = () => {
    if (selectedCafeId.value) {
        router.get(`/master/third-party-channel/${props.channel.id}/menus`, { cafe_id: selectedCafeId.value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    } else {
        router.get(`/master/third-party-channel/${props.channel.id}/menus`, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    }
};

/**
 * Saat toggle manual diubah, reset field yang tidak relevan
 */
const onToggleManual = (menu: Menu) => {
    if (menu.is_manual_price) {
        // Mode manual aktif: kosongkan admin_fee
        menu.admin_fee = null;
    } else {
        // Mode manual dimatikan: kosongkan override_price
        menu.override_price = null;
    }
};

/**
 * Hitung total harga tampilan di kolom "Total Harga Jual Platform"
 * Dipakai hanya untuk mode normal (bukan manual)
 */
const computedTotal = (menu: Menu): number | null => {
    if (menu.admin_fee === null || menu.admin_fee === '') return null;
    return Number(menu.price) + Number(menu.admin_fee);
};

const submit = () => {
    if (!selectedCafeId.value) return;

    const payload = {
        cafe_id: selectedCafeId.value,
        menus: editableMenus.value.map(m => ({
            menu_id:         m.id,
            admin_fee:       m.is_manual_price ? null : m.admin_fee,
            is_manual_price: m.is_manual_price,
            override_price:  m.is_manual_price ? m.override_price : null,
        }))
    };

    router.put(`/master/third-party-channel/${props.channel.id}/menus`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            // Success handled globally/flash message
        }
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Kelola Menu - ${channel.name}`" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-6xl mx-auto px-6 space-y-6">

                <!-- Header -->
                <div class="flex items-center gap-4 mb-2">
                    <Link href="/master/third-party-channel"
                        class="p-2 rounded-xl bg-background border hover:bg-muted transition text-muted-foreground">
                        <ChevronLeft :size="20" />
                    </Link>
                    <Heading variant="small" :title="`Kelola Harga: ${channel.name}`"
                        description="Atur biaya admin (markup) untuk setiap menu. Aktifkan 'Set Manual' untuk mengisi harga jual platform secara bebas." />
                </div>

                <!-- Filter & Actions -->
                <div class="p-5 rounded-2xl bg-background border flex items-end justify-between shadow-sm">
                    <div class="flex flex-col sm:flex-row gap-4 items-end flex-1">
                        <div class="grid gap-1.5 min-w-[250px]">
                            <label class="text-xs font-medium text-muted-foreground">Pilih Cafe</label>
                            <select v-model="selectedCafeId" @change="changeCafe"
                                class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="">-- Pilih Cafe --</option>
                                <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">{{ cafe.name }}</option>
                            </select>
                        </div>

                        <div class="grid gap-1.5 flex-1 max-w-sm" v-if="selectedCafeId && menus.length > 0">
                            <label class="text-xs font-medium text-muted-foreground">Cari Menu</label>
                            <input v-model="searchQuery" type="text" placeholder="Ketik nama menu atau kategori..."
                                class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                    </div>

                    <button v-if="selectedCafeId && menus.length > 0" @click="submit" type="button"
                        class="cursor-pointer inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-primary text-primary-foreground text-sm font-semibold hover:bg-primary/90 transition shadow-sm">
                        <Save :size="16" /> Simpan Perubahan
                    </button>
                </div>

                <!-- Legend -->
                <div v-if="selectedCafeId && menus.length > 0" class="flex items-center gap-6 px-1 text-xs text-muted-foreground">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full bg-orange-400"></span>
                        <span>Mode Normal: Harga Asli + Admin Fee = Total</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                        <span>Mode Manual: Isi Total Harga Jual secara bebas</span>
                    </div>
                </div>

                <!-- Menu List -->
                <div v-if="!selectedCafeId" class="py-20 text-center text-muted-foreground bg-background rounded-2xl border border-dashed">
                    Pilih Cafe terlebih dahulu untuk menampilkan daftar menu.
                </div>

                <div v-else-if="menus.length === 0" class="py-20 text-center text-muted-foreground bg-background rounded-2xl border border-dashed">
                    Tidak ada menu tersedia di Cafe ini.
                </div>

                <div v-else class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-4 py-4 text-left font-medium w-12">No</th>
                                <th class="px-4 py-4 text-left font-medium">Kategori</th>
                                <th class="px-4 py-4 text-left font-medium">Nama Menu</th>
                                <th class="px-4 py-4 text-left font-medium">Harga Asli (Dine-in)</th>
                                <th class="px-4 py-4 text-center font-medium w-32">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span>Set Manual</span>
                                        <span class="text-[10px] font-normal text-muted-foreground/70 normal-case">Harga bebas</span>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-left font-medium">Harga Tambahan (Admin)</th>
                                <th class="px-4 py-4 text-left font-medium">Total Harga Jual Platform</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(menu, index) in paginatedMenus" :key="menu.id"
                                :class="menu.is_manual_price ? 'border-t bg-blue-50/40 hover:bg-blue-50/60 dark:bg-blue-950/20 dark:hover:bg-blue-950/30' : 'border-t hover:bg-muted/30'"
                                class="transition">
                                <!-- No -->
                                <td class="px-4 py-4 text-muted-foreground text-xs">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                                <!-- Kategori -->
                                <td class="px-4 py-4 text-muted-foreground text-xs">{{ menu.category_name }}</td>
                                <!-- Nama Menu -->
                                <td class="px-4 py-4 font-semibold">{{ menu.name }}</td>
                                <!-- Harga Asli -->
                                <td class="px-4 py-4 text-muted-foreground">{{ formatCurrency(menu.price) }}</td>

                                <!-- Toggle Set Manual -->
                                <td class="px-4 py-4 text-center">
                                    <label :for="`toggle-manual-${menu.id}`" class="inline-flex items-center cursor-pointer">
                                        <div class="relative">
                                            <input
                                                :id="`toggle-manual-${menu.id}`"
                                                type="checkbox"
                                                v-model="menu.is_manual_price"
                                                @change="onToggleManual(menu)"
                                                class="sr-only peer"
                                            />
                                            <div class="w-10 h-5 bg-muted rounded-full peer peer-checked:bg-blue-500 transition-colors duration-200 ease-in-out border border-border"></div>
                                            <div class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform duration-200 ease-in-out peer-checked:translate-x-5"></div>
                                        </div>
                                    </label>
                                </td>

                                <!-- Harga Tambahan (Admin Fee) - disabled jika manual -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2 max-w-[180px]">
                                        <span class="text-sm font-medium text-muted-foreground">Rp</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="100"
                                            v-model="menu.admin_fee"
                                            placeholder="Kosong = Tidak dijual"
                                            :disabled="menu.is_manual_price"
                                            :class="menu.is_manual_price
                                                ? 'w-full px-3 py-1.5 text-sm rounded-lg border bg-muted/50 text-muted-foreground/50 cursor-not-allowed'
                                                : 'w-full px-3 py-1.5 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring text-orange-600 font-semibold'"
                                        />
                                    </div>
                                </td>

                                <!-- Total Harga Jual Platform -->
                                <td class="px-4 py-4">
                                    <!-- MODE MANUAL: input bebas -->
                                    <div v-if="menu.is_manual_price" class="flex items-center gap-2 max-w-[180px]">
                                        <span class="text-sm font-medium text-blue-500">Rp</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="500"
                                            v-model="menu.override_price"
                                            placeholder="Isi harga jual..."
                                            class="w-full px-3 py-1.5 text-sm rounded-lg border-2 border-blue-400 bg-background focus:outline-none focus:ring-2 focus:ring-blue-400 text-blue-600 font-bold"
                                        />
                                    </div>

                                    <!-- MODE NORMAL: tampilkan kalkulasi otomatis -->
                                    <template v-else>
                                        <span v-if="computedTotal(menu) !== null" class="font-bold text-lg text-emerald-600">
                                            {{ formatCurrency(computedTotal(menu)!) }}
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground italic">Tidak tersedia</span>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Controls -->
                    <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t bg-muted/20">
                        <span class="text-sm text-muted-foreground">
                            Menampilkan {{ (currentPage - 1) * itemsPerPage + 1 }} - 
                            {{ Math.min(currentPage * itemsPerPage, filteredMenus.length) }} 
                            dari {{ filteredMenus.length }} menu
                        </span>
                        <div class="flex items-center gap-1">
                            <button @click="currentPage > 1 && currentPage--" type="button" :disabled="currentPage === 1"
                                class="px-3 py-1.5 text-sm rounded-lg border bg-background hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed transition">
                                Prev
                            </button>
                            <div class="flex items-center gap-1 px-2">
                                <button v-for="page in totalPages" :key="page" @click="currentPage = page" type="button"
                                    :class="currentPage === page ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:bg-muted'"
                                    class="w-8 h-8 flex items-center justify-center text-sm rounded-lg border transition">
                                    {{ page }}
                                </button>
                            </div>
                            <button @click="currentPage < totalPages && currentPage++" type="button" :disabled="currentPage === totalPages"
                                class="px-3 py-1.5 text-sm rounded-lg border bg-background hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed transition">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
