<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Eye, Filter, Download, ChevronDown, Printer } from 'lucide-vue-next';
import { ref } from 'vue';
import axios from 'axios';
import { Notyf } from 'notyf';

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
    is_display: number;
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
        display_status?: string;
    };
    isGod: boolean;
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
const filterDisplayStatus = ref(props.filters.display_status || '');


const applyFilters = () => {
    const params: Record<string, string> = {};
    if (filterCafe.value) params.cafe_id = filterCafe.value;
    if (filterPaymentType.value) params.payment_type = filterPaymentType.value;
    if (filterDateFrom.value) params.date_from = filterDateFrom.value;
    if (filterDateTo.value) params.date_to = filterDateTo.value;
    if (filterDisplayStatus.value) params.display_status = filterDisplayStatus.value;
    router.get('/transaction/history', params, { preserveState: true });
};

const resetFilters = () => {
    filterCafe.value = '';
    filterPaymentType.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    filterDisplayStatus.value = '';
    router.get('/transaction/history', {}, { preserveState: true });
};

const exportXls = (withDetails: boolean) => {
    const params = new URLSearchParams();
    if (filterCafe.value) params.set('cafe_id', filterCafe.value);
    if (filterPaymentType.value) params.set('payment_type', filterPaymentType.value);
    if (filterDateFrom.value) params.set('date_from', filterDateFrom.value);
    if (filterDateTo.value) params.set('date_to', filterDateTo.value);
    if (filterDisplayStatus.value) params.set('display_status', filterDisplayStatus.value);
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

import { EyeOff, Eye as EyeIcon } from 'lucide-vue-next';

const toggleDisplay = (id: number) => {
    if (confirm('Apakah Anda yakin ingin mengubah status tampilan transaksi ini?')) {
        router.patch(`/transaction/history/${id}/toggle-display`, {}, {
            preserveScroll: true,
        });
    }
};

// ── Notyf ──────────────────────────────────────────────────────────────────
const notyf = new Notyf({
    duration: 4000,
    position: { x: 'right', y: 'bottom' },
    ripple: true,
    dismissible: true,
});

// ── Print Mode Toggle (manual) ────────────────────────────────────────────
// true  = print via RawBT (mobile/tablet)
// false = print via API localhost:3000 (desktop)
const isMobile = ref<boolean>(localStorage.getItem('cashier_is_mobile') === 'true');

const toggleIsMobile = () => {
    isMobile.value = !isMobile.value;
    localStorage.setItem('cashier_is_mobile', String(isMobile.value));
};

// ── RawBT Print ───────────────────────────────────────────────────────────
const PRINT_WIDTH = 48;
const PRINT_LINE = '-'.repeat(PRINT_WIDTH);
const PRINT_DOUBLE_LINE = '='.repeat(PRINT_WIDTH);

const printPadRight = (left: string, right: string): string => {
    const space = PRINT_WIDTH - (left.length + right.length);
    return left + ' '.repeat(space > 0 ? space : 1) + right;
};

const printNumber = (val: number): string => new Intl.NumberFormat('id-ID').format(val);

const sendToRawBT = (bytes: number[]) => {
    const uint8 = new Uint8Array(bytes);
    let binary = '';
    uint8.forEach((b) => (binary += String.fromCharCode(b)));
    window.location.href = 'rawbt:base64,' + btoa(binary);
};

const buildLogoBytes = async (): Promise<number[]> => {
    const PRINTER_DOT_WIDTH = 576;
    const LOGO_RENDER_WIDTH = 200;
    try {
        const response = await axios.get('/proxy/logo1', { responseType: 'blob' });
        const objectUrl = URL.createObjectURL(response.data);
        return await new Promise<number[]>((resolve) => {
            const img = new Image();
            img.onload = () => {
                URL.revokeObjectURL(objectUrl);
                try {
                    const logoHeight = Math.round(LOGO_RENDER_WIDTH * (img.height / img.width));
                    const canvas = document.createElement('canvas');
                    canvas.width = PRINTER_DOT_WIDTH;
                    canvas.height = logoHeight;
                    const ctx = canvas.getContext('2d')!;
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    const offsetX = Math.floor((PRINTER_DOT_WIDTH - LOGO_RENDER_WIDTH) / 2);
                    ctx.drawImage(img, offsetX, 0, LOGO_RENDER_WIDTH, logoHeight);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const bytes: number[] = [];
                    const bytesPerLine = Math.ceil(PRINTER_DOT_WIDTH / 8);
                    bytes.push(0x1d, 0x76, 0x30, 0x00, bytesPerLine & 0xff, (bytesPerLine >> 8) & 0xff, logoHeight & 0xff, (logoHeight >> 8) & 0xff);
                    for (let y = 0; y < logoHeight; y++) {
                        for (let x = 0; x < bytesPerLine; x++) {
                            let byte = 0;
                            for (let bit = 0; bit < 8; bit++) {
                                const px = x * 8 + bit;
                                if (px < PRINTER_DOT_WIDTH) {
                                    const i = (y * PRINTER_DOT_WIDTH + px) * 4;
                                    const gray = (imageData.data[i] + imageData.data[i + 1] + imageData.data[i + 2]) / 3;
                                    if (gray < 128) byte |= 0x80 >> bit;
                                }
                            }
                            bytes.push(byte);
                        }
                    }
                    resolve(bytes);
                } catch {
                    resolve([]);
                }
            };
            img.onerror = () => { URL.revokeObjectURL(objectUrl); resolve([]); };
            img.src = objectUrl;
        });
    } catch {
        return [];
    }
};

const printReceiptInline = async (id: number, filterType: 'all' | 'FOOD' | 'BEVERAGE' = 'all') => {
    try {
        const { data: trx } = await axios.get(`/transaction/history/${id}/receipt-data`);

        if (!isMobile.value) {
            const res = await axios.post('http://localhost:3000/print', trx);
            if (res.status === 200 || res.status === 207) {
                notyf.success('Print sukses');
            }
            return;
        }

        const fmtDate = (val: string) => new Date(val).toLocaleString('id-ID');

        let detailsToPrint = trx.details;
        if (filterType !== 'all') {
            detailsToPrint = trx.details.filter((d: any) => d.menu?.menu_type === filterType);
        }

        if (detailsToPrint.length === 0) {
            notyf.error(`Tidak ada item dengan tipe ${filterType}`);
            return;
        }

        const bytes: number[] = [];
        const encoder = new TextEncoder();
        const enc = (text: string) => bytes.push(...encoder.encode(text));

        bytes.push(0x1b, 0x40);
        bytes.push(...(await buildLogoBytes()));
        bytes.push(0x1b, 0x61, 0x01);
        bytes.push(0x1b, 0x45, 0x01);
        enc('\n');
        enc((trx.cafe?.name || 'CAFE') + '\n');
        bytes.push(0x1b, 0x45, 0x00);
        if (trx.cafe?.address) enc(trx.cafe.address + '\n');

        if (filterType === 'FOOD') {
            enc(PRINT_LINE + '\n');
            enc('--- ONLY FOOD ---\n');
        } else if (filterType === 'BEVERAGE') {
            enc(PRINT_LINE + '\n');
            enc('--- ONLY BEVERAGE ---\n');
        }

        enc(PRINT_DOUBLE_LINE + '\n');

        bytes.push(0x1b, 0x61, 0x00);
        const LABEL_W = 6;
        const fmtL = (label: string) => label.padEnd(LABEL_W) + ': ';
        enc(fmtL('No') + '#' + trx.id + '\n');
        enc(fmtL('Tgl') + fmtDate(trx.updated_at) + '\n');
        enc(fmtL('Cust') + (trx.cust_name || '-') + '\n');
        if (trx.table) enc(fmtL('Table') + trx.table.name + '\n');
        enc(PRINT_LINE + '\n');

        enc(printPadRight('Menu', 'Harga') + '\n');
        enc(PRINT_LINE + '\n');

        detailsToPrint.forEach((d: any) => {
            const menuName = (d.menu?.name || '-').substring(0, PRINT_WIDTH);
            enc(printPadRight(menuName, 'Rp ' + printNumber(Number(d.price))) + '\n');
            enc('  ' + d.amount + ' x Rp ' + printNumber(Number(d.menu?.price ?? 0)) + '\n');
            if (d.selected_variants && d.selected_variants.length > 0) {
                d.selected_variants.forEach((sv: any) => {
                    const label = sv.material_name ? sv.material_name + ': ' + (sv.variant_name || '-') : sv.variant_name || '-';
                    enc('  [' + label + ']\n');
                });
            }
            if (d.description) enc('  ' + d.description + '\n');
        });
        enc(PRINT_LINE + '\n');

        if (filterType === 'all') {
            enc(printPadRight('Subtotal', 'Rp ' + printNumber(Number(trx.price))) + '\n');
            enc(printPadRight('PPN', 'Rp ' + printNumber(Number(trx.fee))) + '\n');
            bytes.push(0x1b, 0x45, 0x01);
            if (trx.promo_id) {
                const promo = Number(trx.price) + Number(trx.fee) - Number(trx.total_price);
                enc(printPadRight('Discount', '-Rp ' + printNumber(Number(promo))) + '\n');
            }
            enc(PRINT_DOUBLE_LINE + '\n');
            bytes.push(0x1b, 0x45, 0x01);
            enc(printPadRight('TOTAL', 'Rp ' + printNumber(Number(trx.total_price))) + '\n');
            enc(printPadRight('Pay', trx.payment_type.toUpperCase()) + '\n');
            bytes.push(0x1b, 0x45, 0x00);
            enc(PRINT_DOUBLE_LINE + '\n');
        } else {
            const partialSubtotal = detailsToPrint.reduce((acc: number, d: any) => acc + Number(d.price), 0);
            enc(printPadRight('Subtotal', 'Rp ' + printNumber(partialSubtotal)) + '\n');
            enc(PRINT_LINE + '\n');
            bytes.push(0x1b, 0x45, 0x01);
            enc(PRINT_DOUBLE_LINE + '\n');
            bytes.push(0x1b, 0x45, 0x01);
            enc(printPadRight('TOTAL', 'Rp ' + printNumber(partialSubtotal)) + '\n');
            enc(printPadRight('Pay', trx.payment_type.toUpperCase()) + '\n');
            bytes.push(0x1b, 0x45, 0x00);
            enc(PRINT_DOUBLE_LINE + '\n');
        }

        bytes.push(0x1b, 0x61, 0x01);
        enc('Terima kasih\n');
        bytes.push(0x1b, 0x64, 0x05);
        bytes.push(0x1d, 0x56, 0x41, 0x00);
        sendToRawBT(bytes);
    } catch (e: any) {
        console.error(e);
        notyf.error('Print gagal: ' + (e.message || 'Error'));
    }
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

                <!-- Print Mode Toggle -->
                <button
                    type="button"
                    @click="toggleIsMobile"
                    :class="
                        isMobile
                            ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
                            : 'border-slate-300 bg-slate-50 text-slate-600 hover:bg-slate-100'
                    "
                    class="flex cursor-pointer select-none items-center gap-3 rounded-xl border px-4 py-2.5 text-sm font-medium transition"
                >
                    <span
                        :class="isMobile ? 'bg-indigo-500' : 'bg-slate-300'"
                        class="relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors"
                    >
                        <span
                            :class="isMobile ? 'translate-x-4' : 'translate-x-0.5'"
                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                        />
                    </span>
                    <span v-if="isMobile">📱 Mode Mobile — Print via RawBT</span>
                    <span v-else>🖥️ Mode Desktop — Print via API</span>
                </button>

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
                                    <option value="debit">Debit Card</option>
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

                            <!-- Display Status (GOD ONLY) -->
                            <div class="grid gap-1.5" v-if="props.isGod">
                                <label class="text-xs font-medium text-muted-foreground">Status Tampilan (GOD)</label>
                                <select v-model="filterDisplayStatus"
                                    class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                    <option value="">Semua</option>
                                    <option value="displayed">Ditampilkan</option>
                                    <option value="hidden">Disembunyikan</option>
                                </select>
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
                                        :class="trx.payment_type === 'qris' ? 'bg-orange-100 text-orange-700' : trx.payment_type === 'debit' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'">
                                        {{ trx.payment_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">{{ formatDate(trx.updated_at) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button v-if="props.isGod && trx.payment_type === 'manual'" @click="toggleDisplay(trx.id)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium hover:text-white transition cursor-pointer"
                                            :class="trx.is_display ? 'bg-orange-100 text-orange-600 hover:bg-orange-500' : 'bg-emerald-100 text-emerald-600 hover:bg-emerald-500'"
                                            :title="trx.is_display ? 'Sembunyikan' : 'Tampilkan'">
                                            <EyeOff v-if="trx.is_display" :size="14" />
                                            <EyeIcon v-else :size="14" />
                                            {{ trx.is_display ? 'Hide' : 'Show' }}
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-violet-100 px-3 py-1.5 text-xs font-medium text-violet-600 outline-none transition hover:bg-violet-500 hover:text-white"
                                            >
                                                <Printer :size="14" /> Cetak Struk <ChevronDown :size="14" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="printReceiptInline(trx.id, 'all')">Semua menu</DropdownMenuItem>
                                                <DropdownMenuItem @click="printReceiptInline(trx.id, 'FOOD')">Only food</DropdownMenuItem>
                                                <DropdownMenuItem @click="printReceiptInline(trx.id, 'BEVERAGE')">Only beverage</DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        <Link :href="`/transaction/history/${trx.id}`"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition"
                                            title="Lihat Detail">
                                            <Eye :size="14" /> Detail
                                        </Link>
                                    </div>
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
