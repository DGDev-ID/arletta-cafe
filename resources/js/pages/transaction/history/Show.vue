<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Trash2, Printer, ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import axios from 'axios';
import { Notyf } from 'notyf';

interface SelectedVariant {
    material_id: number;
    variant_id: number;
    material_name?: string | null;
    variant_name?: string | null;
}

interface TransactionDetail {
    id: number;
    menu: {
        id: number;
        name: string;
        price: string;
        category: { id: number; name: string } | null;
        is_combo?: boolean | number;
        menu_combos?: { id: number; group_id?: number | null; child_menu?: { id: number; name: string }; amount: number }[];
    } | null;
    amount: number;
    price: string;
    description: string | null;
    selected_variants?: SelectedVariant[] | null;
    selected_combo_options?: { group_id: number; menu_id: number; group_label?: string; menu_name?: string }[] | null;
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
    is_open_bill: number | boolean;
    created_at: string;
    updated_at: string;
    cafe: { id: number; name: string; address: string | null };
    table: { id: number; name: string } | null;
    details: TransactionDetail[];
}

const props = defineProps<{
    transaction: Transaction;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'History Transaction', href: '/transaction/history' },
    { title: `Transaction #${props.transaction.id}`, href: `/transaction/history/${props.transaction.id}` },
];

const formatCurrency = (val: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));

const formatDate = (val: string) => {
    const d = new Date(val);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const makeFailed = () => {
    if (confirm('Tolak transaksi ini? Status akan diubah ke failed.')) {
        router.patch(`/transaction/history/${props.transaction.id}/failed`);
    }
};

const voidDetail = (detailId: number, menuName: string, qty: number) => {
    const msg = qty > 1
        ? `Kurangi qty "${menuName}" sebanyak 1?\nQty akan berkurang dari ${qty} menjadi ${qty - 1}.`
        : `Void item "${menuName}"?\nItem akan dihapus dari transaksi karena qty sudah 1.`;
    if (confirm(msg)) {
        router.patch(`/transaction/history/detail/${detailId}/void`, {}, {
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

// ── Print Mode Toggle ────────────────────────────────────────────────
const isMobile = ref<boolean>(localStorage.getItem('cashier_is_mobile') === 'true');

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

const printReceiptInline = async (filterType: 'all' | 'FOOD' | 'BEVERAGE' = 'all') => {
    try {
        const { data: trx } = await axios.get(`/transaction/history/${props.transaction.id}/receipt-data`);

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

        <Head :title="`Transaction #${transaction.id}`" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex items-center justify-between">
                    <Heading variant="small" :title="`Transaction #${transaction.id}`"
                        description="Detail lengkap transaksi." />

                    <div class="flex items-center gap-2">
                        <button v-if="transaction.status === 'success'" @click="makeFailed"
                            class="inline-flex items-center px-3 py-1.5 rounded-md bg-red-100 text-red-700 text-sm font-medium hover:bg-red-200 transition">
                            Tolak Transaksi
                        </button>
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-violet-100 px-3 py-1.5 text-sm font-medium text-violet-600 outline-none transition hover:bg-violet-500 hover:text-white"
                            >
                                <Printer :size="15" /> Cetak Struk <ChevronDown :size="14" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="printReceiptInline('all')">Semua menu</DropdownMenuItem>
                                <DropdownMenuItem @click="printReceiptInline('FOOD')">Only food</DropdownMenuItem>
                                <DropdownMenuItem @click="printReceiptInline('BEVERAGE')">Only beverage</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Link href="/transaction/history"
                            class="text-sm text-muted-foreground hover:text-foreground transition">
                            ← Kembali
                        </Link>
                    </div>
                </div>

                <!-- Transaction Info Card -->
                <div class="rounded-2xl border bg-background shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-4">Informasi
                        Transaksi</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 text-sm">
                        <div>
                            <span class="text-muted-foreground">Customer Name</span>
                            <p class="font-medium">{{ transaction.cust_name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Cafe</span>
                            <p class="font-medium">{{ transaction.cafe?.name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Table</span>
                            <p class="font-medium">{{ transaction.table?.name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Price (Subtotal)</span>
                            <p class="font-medium">{{ formatCurrency(transaction.price) }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Fee</span>
                            <p class="font-medium">{{ formatCurrency(transaction.fee) }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Total Price</span>
                            <p class="font-medium">{{ formatCurrency(transaction.total_price) }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Payment Type</span>
                            <p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                    :class="transaction.payment_type === 'qris' ? 'bg-orange-100 text-orange-700' : transaction.payment_type === 'debit' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'">
                                    {{ transaction.payment_type }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Status</span>
                            <p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 capitalize">
                                    {{ transaction.status }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Tipe Order</span>
                            <p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="transaction.is_open_bill ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'">
                                    {{ transaction.is_open_bill ? 'Open Bill' : 'Sekali Bayar' }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Tanggal</span>
                            <p class="font-medium">{{ formatDate(transaction.updated_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Transaction Details Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Transaction
                            Details</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Menu</th>
                                <th class="px-6 py-4 text-left font-medium">Kategori</th>
                                <th class="px-6 py-4 text-left font-medium">Qty</th>
                                <th class="px-6 py-4 text-left font-medium">Harga</th>
                                <th class="px-6 py-4 text-left font-medium">Subtotal</th>
                                <th class="px-6 py-4 text-left font-medium">Keterangan</th>
                                <th class="px-6 py-4 text-left font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(detail, index) in transaction.details" :key="detail.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium">
                                    <div>{{ detail.menu?.name ?? '-' }}</div>
                                    <!-- Variant biji kopi / bahan selectable -->
                                    <div v-if="detail.selected_variants && detail.selected_variants.length > 0" class="flex flex-wrap gap-1 mt-1">
                                        <span
                                            v-for="sv in detail.selected_variants"
                                            :key="sv.variant_id"
                                            class="inline-flex items-center gap-1 text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-300 px-2 py-0.5 rounded-full"
                                        >
                                            <span class="text-[7px]">●</span>
                                            {{ sv.material_name ? sv.material_name + ': ' : '' }}{{ sv.variant_name ?? `#${sv.variant_id}` }}
                                        </span>
                                    </div>
                                    <!-- Daftar Menu Combo (Fixed) -->
                                    <div v-if="detail.menu?.is_combo && detail.menu?.menu_combos && detail.menu.menu_combos.some(c => !c.group_id)" class="mt-2 pl-2 border-l-2 border-gray-200 text-xs">
                                        <div class="font-medium text-gray-500 mb-1">Menu Tetap:</div>
                                        <ul class="list-none space-y-0.5 text-gray-500">
                                            <li v-for="combo in detail.menu.menu_combos.filter(c => !c.group_id)" :key="combo.id" class="flex items-center gap-1.5">
                                                <span class="w-1 h-1 rounded-full bg-gray-400"></span>
                                                <span>{{ combo.child_menu?.name }} <span class="text-gray-400">({{ combo.amount }}x)</span></span>
                                            </li>
                                        </ul>
                                    </div>
                                    <!-- Pilihan Combo Pelanggan -->
                                    <div v-if="detail.selected_combo_options && detail.selected_combo_options.length > 0" class="flex flex-wrap gap-1 mt-1.5">
                                        <span
                                            v-for="co in detail.selected_combo_options"
                                            :key="co.group_id"
                                            class="inline-flex items-center gap-1 text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-300 px-2 py-0.5 rounded-full"
                                        >
                                            <span class="text-[7px]">●</span>
                                            {{ co.group_label ? co.group_label + ': ' : '' }}{{ co.menu_name ?? `#${co.menu_id}` }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">{{ detail.menu?.category?.name ?? '-' }}
                                </td>
                                <td class="px-6 py-4">{{ detail.amount }}</td>
                                <td class="px-6 py-4">{{ formatCurrency(Number(detail.price) / detail.amount) }}</td>
                                <td class="px-6 py-4 font-medium">{{ formatCurrency(detail.price) }}</td>
                                <td class="px-6 py-4 text-muted-foreground">{{ detail.description ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <button
                                        v-if="detail.amount > 1 || transaction.details.length > 1"
                                        @click="voidDetail(detail.id, detail.menu?.name ?? '-', detail.amount)"
                                        type="button"
                                        class="cursor-pointer inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-100 text-red-600 text-xs font-medium hover:bg-red-500 hover:text-white transition">
                                        <Trash2 :size="13" /> Void
                                    </button>
                                    <span v-else class="text-xs text-muted-foreground italic">Min 1 item</span>
                                </td>
                            </tr>
                            <tr v-if="transaction.details.length === 0">
                                <td colspan="8" class="px-6 py-10 text-center text-muted-foreground">
                                    Tidak ada detail transaksi.
                                </td>
                            </tr>
                        </tbody>
                        <!-- Summary Footer -->
                        <tfoot v-if="transaction.details.length > 0" class="bg-muted/30">
                            <tr class="border-t">
                                <td colspan="6" class="px-6 py-3 text-right font-medium text-muted-foreground">Subtotal
                                </td>
                                <td colspan="2" class="px-6 py-3 font-semibold">{{ formatCurrency(transaction.price) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" class="px-6 py-3 text-right font-medium text-muted-foreground">Fee</td>
                                <td colspan="2" class="px-6 py-3 font-semibold">{{ formatCurrency(transaction.fee) }}
                                </td>
                            </tr>
                            <tr class="border-t">
                                <td colspan="6" class="px-6 py-3 text-right font-medium text-muted-foreground">Total
                                </td>
                                <td colspan="2" class="px-6 py-3 font-bold">{{ formatCurrency(transaction.total_price)
                                    }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
