<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { ref, watch } from 'vue';
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

const submit = () => {
    if (!selectedCafeId.value) return;

    const payload = {
        cafe_id: selectedCafeId.value,
        menus: editableMenus.value.map(m => ({
            menu_id: m.id,
            admin_fee: m.admin_fee
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
            <div class="max-w-5xl mx-auto px-6 space-y-6">

                <!-- Header -->
                <div class="flex items-center gap-4 mb-2">
                    <Link href="/master/third-party-channel"
                        class="p-2 rounded-xl bg-background border hover:bg-muted transition text-muted-foreground">
                        <ChevronLeft :size="20" />
                    </Link>
                    <Heading variant="small" :title="`Kelola Harga: ${channel.name}`"
                        description="Atur biaya admin (markup) untuk setiap menu. Kosongkan harga admin jika menu tidak tersedia di saluran ini." />
                </div>

                <!-- Filter & Actions -->
                <div class="p-5 rounded-2xl bg-background border flex items-end justify-between shadow-sm">
                    <div class="grid gap-1.5 min-w-[250px]">
                        <label class="text-xs font-medium text-muted-foreground">Pilih Cafe</label>
                        <select v-model="selectedCafeId" @change="changeCafe"
                            class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            <option value="">-- Pilih Cafe --</option>
                            <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">{{ cafe.name }}</option>
                        </select>
                    </div>

                    <button v-if="selectedCafeId && menus.length > 0" @click="submit" type="button"
                        class="cursor-pointer inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-primary text-primary-foreground text-sm font-semibold hover:bg-primary/90 transition shadow-sm">
                        <Save :size="16" /> Simpan Perubahan
                    </button>
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
                                <th class="px-6 py-4 text-left font-medium w-16">No</th>
                                <th class="px-6 py-4 text-left font-medium">Kategori</th>
                                <th class="px-6 py-4 text-left font-medium">Nama Menu</th>
                                <th class="px-6 py-4 text-left font-medium">Harga Asli (Dine-in)</th>
                                <th class="px-6 py-4 text-left font-medium">Harga Tambahan (Admin Pihak Ketiga)</th>
                                <th class="px-6 py-4 text-left font-medium">Total Harga Jual Platform</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(menu, index) in editableMenus" :key="menu.id"
                                class="border-t hover:bg-muted/30 transition">
                                <td class="px-6 py-4 text-muted-foreground">{{ index + 1 }}</td>
                                <td class="px-6 py-4 text-muted-foreground text-xs">{{ menu.category_name }}</td>
                                <td class="px-6 py-4 font-semibold">{{ menu.name }}</td>
                                <td class="px-6 py-4">{{ formatCurrency(menu.price) }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2 max-w-[200px]">
                                        <span class="text-sm font-medium text-muted-foreground">Rp</span>
                                        <input type="number" min="0" step="100" v-model="menu.admin_fee" placeholder="Kosong = Tidak dijual"
                                            class="w-full px-3 py-1.5 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring text-orange-600 font-semibold" />
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="menu.admin_fee !== null && menu.admin_fee !== ''" class="font-bold text-lg text-emerald-600">
                                        {{ formatCurrency(Number(menu.price) + Number(menu.admin_fee)) }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground italic">Tidak tersedia</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
