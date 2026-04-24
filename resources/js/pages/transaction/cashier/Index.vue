<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Eye, CheckCircle, Printer } from 'lucide-vue-next';
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Notyf } from 'notyf';
import axios from 'axios';
import qz from 'qz-tray';

interface Cafe {
    id: number;
    name: string;
}

interface Transaction {
    id: number;
    cafe_id: number;
    cust_name: string | null;
    total_price: string;
    status: string;
    payment_type: string;
    cafe: Cafe;
    table: { id: number; name: string } | null;
}


const props = defineProps<{
    pendingTransactions: Transaction[];
    inOrderTransactions: Transaction[];
    successTransactions: Transaction[];
    cafes: Cafe[];
    filters: {
        cafe_id: string;
    };
}>();

// QR Code Search State
const qrCode = ref('');
const qrResult = ref<Transaction | null>(null);
const qrLoading = ref(false);

const searchByQRCode = async () => {
    if (!qrCode.value) return;
    qrLoading.value = true;
    try {
        const { data } = await axios.get(`/transaction/cashier/search-qr/${qrCode.value}`);
        qrResult.value = data;
        notyf.success('Transaksi ditemukan!');
    } catch (e: any) {
        qrResult.value = null;
        notyf.error(e?.response?.data?.message || 'QR Code tidak valid atau transaksi tidak ditemukan.');
    } finally {
        qrLoading.value = false;
    }
};

const clearQRCodeSearch = () => {
    qrCode.value = '';
    qrResult.value = null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cashier', href: '/transaction/cashier' },
];

const selectedCafe = ref(props.filters.cafe_id);

watch(selectedCafe, (val) => {
    const params: Record<string, string> = {};
    if (val) params.cafe_id = val;
    router.get('/transaction/cashier', params, { preserveState: true });
});

const formatCurrency = (val: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));

// ── Notification toast ────────────────────────────────────────────────────
const notyf = new Notyf({
    duration: 4000,
    position: { x: 'right', y: 'bottom' },
    ripple: true,
    dismissible: true,
});

// ── Polling ───────────────────────────────────────────────────────────────
let prevPendingIds = new Set(props.pendingTransactions.map(t => t.id));
let prevInOrderIds = new Set(props.inOrderTransactions.map(t => t.id));
let pollInterval: ReturnType<typeof setInterval> | null = null;

const makeSuccessInOrder = (id: number) => {
    if (confirm('Selesaikan transaksi ini? Status akan diubah ke success.')) {
        router.patch(`/transaction/cashier/${id}/success-in-order`);
    }
};

const printReceiptInline = async (id: number) => {
    // Jika display adalah tablet/mobile (<= 1024px), gunakan Bluetooth Print
    if (window.innerWidth <= 1024) {
        const responseUrl = `${window.location.origin}/bluetooth-receipt/${id}`;
        window.location.href = `my.bluetoothprint.scheme://${responseUrl}`;
        return;
    }

    // Jika display laptop (> 1024px), gunakan QZ Tray
    try {
        const { data: trx } = await axios.get(`/transaction/cashier/${id}/receipt-data`);

        // ===== FORMAT =====
        const cleanNumber = (val: number) =>
            new Intl.NumberFormat('id-ID')
                .format(val)
                .replace(/[^\d]/g, '');

        const fmtDate = (val: string) => {
            const d = new Date(val);
            return d.toLocaleString('id-ID');
        };

        // ===== CONNECT QZ =====
        if (!qz.websocket.isActive()) {
            qz.security.setCertificatePromise(resolve => resolve(null));
            qz.security.setSignaturePromise(() => resolve => resolve(null));
            await qz.websocket.connect();
        }

        const printers = await qz.printers.find();
        let printerName = await qz.printers.getDefault();

        const posPrinter = printers.find((p: string) =>
            p.toLowerCase().includes('thermal') ||
            p.toLowerCase().includes('pos') ||
            p.toLowerCase().includes('58')
        );

        if (posPrinter) printerName = posPrinter;

        if (!printerName) {
            notyf.error('Printer tidak ditemukan');
            return;
        }

        // ===== CONFIG =====
        const config = qz.configs.create(printerName, {
            encoding: 'ISO-8859-1',
            scaleContent: true
        });

        // ===== ESC/POS =====
        const init = '\x1B\x40';
        const normal = '\x1B\x21\x00';
        const alignLeft = '\x1B\x61\x00';
        const alignCenter = '\x1B\x61\x01';
        const boldOn = '\x1B\x45\x01';
        const boldOff = '\x1B\x45\x00';
        const cut = '\x1D\x56\x41\x00';
        const codepage = '\x1B\x74\x00';

        const WIDTH = 32;
        const line = '-'.repeat(WIDTH) + '\n';

        const padRight = (left: string, right: string) => {
            const space = WIDTH - (left.length + right.length);
            return left + ' '.repeat(space > 0 ? space : 1) + right + '\n';
        };

        // ===== BUILD TEXT =====
        let str = '';
        str += init;
        str += codepage;
        str += normal;

        // HEADER
        str += alignCenter;
        str += boldOn;
        str += (trx.cafe.name || 'CAFE') + '\n';
        str += boldOff;
        if (trx.cafe.address) str += trx.cafe.address + '\n';
        if (trx.cafe.phone_number) str += trx.cafe.phone_number + '\n';
        str += line;

        // INFO
        str += alignLeft;
        str += padRight('No', `#${trx.id}`);
        str += padRight('Tgl', fmtDate(trx.updated_at));
        str += padRight('Cust', trx.cust_name || '-');
        if (trx.table) str += padRight('Table', trx.table.name);
        str += padRight('Pay', trx.payment_type);
        str += line;

        // ITEMS
        trx.details.forEach((d: any) => {
            const name = (d.menu?.name || '-').substring(0, WIDTH);
            str += name + '\n';

            const qtyPrice = `${d.amount}x${cleanNumber(Number(d.price))}`;
            const subtotal = cleanNumber(Number(d.price) * d.amount);

            str += padRight(qtyPrice, subtotal);

            if (d.description) {
                str += ' ' + d.description + '\n';
            }
        });

        str += line;

        // TOTAL
        str += padRight('Subtotal', cleanNumber(Number(trx.price)));
        str += padRight('Fee', cleanNumber(Number(trx.fee)));

        str += line;
        str += boldOn;
        str += padRight('TOTAL', cleanNumber(Number(trx.total_price)));
        str += boldOff;
        str += line;

        // FOOTER
        str += alignCenter;
        str += 'Terima kasih\n';
        str += '\n\n\n\n';
        str += cut;

        // ===== PRINT (URUTAN DIPERBAIKI) =====
        await qz.print(config, [
            // 🔥 INIT + CENTER dulu (penting biar logo ikut center)
            {
                type: 'raw',
                format: 'command',
                data: init + alignCenter
            },

            {
                type: 'raw',
                format: 'command',
                data: '\n' + str
            }
        ]);

        notyf.success('Struk berhasil dicetak');

    } catch (e: any) {
        console.error(e);
        notyf.error('Print gagal: ' + (e.message || 'QZ error'));
    }
};

onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({
            only: ['pendingTransactions', 'inOrderTransactions', 'flash'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                const newPendingIds = new Set(props.pendingTransactions.map(t => t.id));
                const newInOrderIds = new Set(props.inOrderTransactions.map(t => t.id));

                const hasPendingNew = [...newPendingIds].some(id => !prevPendingIds.has(id));
                const hasInOrderNew = [...newInOrderIds].some(id => !prevInOrderIds.has(id));

                if (hasPendingNew) notyf.success('Data transaksi pending baru terdeteksi');
                else if (hasInOrderNew) notyf.success('Data in order baru terdeteksi');

                prevPendingIds = newPendingIds;
                prevInOrderIds = newInOrderIds;
            },
        });
    }, 3000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Cashier" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <Heading variant="small" title="Cashier" description="Kelola transaksi pending manual dan in order." />

                <!-- Filter Cafe -->
                <div class="flex items-end gap-3">
                    <div class="grid gap-1.5 min-w-[220px]">
                        <label class="text-xs font-medium text-muted-foreground">Cafe</label>
                        <select v-model="selectedCafe"
                            class="w-full px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            <option value="">Semua Cafe</option>
                            <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">{{ cafe.name }}</option>
                        </select>
                    </div>
                </div>


                <!-- Pending Manual Transactions -->
                <div class="space-y-3">
                    <h2 class="text-base font-semibold">Pending Manual Transactions</h2>

                    <!-- QR Code Search -->
                    <form @submit.prevent="searchByQRCode" class="flex items-center gap-2 mb-2">
                        <input v-model="qrCode" type="text" placeholder="Cari transaksi dengan QR Code..."
                            class="px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-ring w-64"
                            :disabled="qrLoading" />
                        <button type="submit"
                            class="px-3 py-2 rounded-lg bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 transition disabled:opacity-60"
                            :disabled="qrLoading || !qrCode">
                            {{ qrLoading ? 'Mencari...' : 'Cari QR Code' }}
                        </button>
                        <button v-if="qrResult" type="button" @click="clearQRCodeSearch"
                            class="px-3 py-2 rounded-lg bg-gray-200 text-gray-700 text-xs font-medium hover:bg-gray-300 transition">
                            Reset
                        </button>
                    </form>

                    <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-muted-foreground">
                                    <th class="px-6 py-4 text-left font-medium">No</th>
                                    <th class="px-6 py-4 text-left font-medium">Cafe</th>
                                    <th class="px-6 py-4 text-left font-medium">Customer Name</th>
                                    <th class="px-6 py-4 text-left font-medium">Total Price</th>
                                    <th class="px-6 py-4 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="qrResult">
                                    <tr :key="qrResult.id" class="border-t hover:bg-muted/40 transition">
                                        <td class="px-6 py-4">1</td>
                                        <td class="px-6 py-4 font-medium">{{ qrResult.cafe?.name ?? '-' }}</td>
                                        <td class="px-6 py-4">{{ qrResult.cust_name ?? '-' }}</td>
                                        <td class="px-6 py-4 font-medium">{{ formatCurrency(qrResult.total_price) }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <Link :href="`/transaction/cashier/${qrResult.id}`"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition">
                                                <Eye :size="14" /> Detail
                                            </Link>
                                        </td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr v-for="(trx, index) in pendingTransactions" :key="trx.id"
                                        class="border-t hover:bg-muted/40 transition">
                                        <td class="px-6 py-4">{{ index + 1 }}</td>
                                        <td class="px-6 py-4 font-medium">{{ trx.cafe?.name ?? '-' }}</td>
                                        <td class="px-6 py-4">{{ trx.cust_name ?? '-' }}</td>
                                        <td class="px-6 py-4 font-medium">{{ formatCurrency(trx.total_price) }}</td>
                                        <td class="px-6 py-4 text-right">
                                            <Link :href="`/transaction/cashier/${trx.id}`"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition">
                                                <Eye :size="14" /> Detail
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="pendingTransactions.length === 0">
                                        <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">
                                            Tidak ada transaksi pending.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- In Order Transactions -->
                <div class="space-y-3">
                    <h2 class="text-base font-semibold">In Order Transactions</h2>
                    <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-muted-foreground">
                                    <th class="px-6 py-4 text-left font-medium">No</th>
                                    <th class="px-6 py-4 text-left font-medium">Customer Name</th>
                                    <th class="px-6 py-4 text-left font-medium">Table</th>
                                    <th class="px-6 py-4 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(trx, index) in inOrderTransactions" :key="trx.id"
                                    class="border-t hover:bg-muted/40 transition">
                                    <td class="px-6 py-4">{{ index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ trx.cust_name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ trx.table?.name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end items-center gap-2">
                                            <button @click="printReceiptInline(trx.id)" type="button"
                                                class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-100 text-violet-600 text-xs font-medium hover:bg-violet-500 hover:text-white transition">
                                                <Printer :size="14" /> Cetak Struk
                                            </button>
                                            <button @click="makeSuccessInOrder(trx.id)" type="button"
                                                class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-100 text-green-600 text-xs font-medium hover:bg-green-500 hover:text-white transition">
                                                <CheckCircle :size="14" /> Selesai
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="inOrderTransactions.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-muted-foreground">
                                        Tidak ada transaksi in order.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pesanan Hari Ini (Success Transactions) -->
                <div class="space-y-3">
                    <h2 class="text-base font-semibold">Pesanan Hari Ini</h2>
                    <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-muted-foreground">
                                    <th class="px-6 py-4 text-left font-medium">No</th>
                                    <th class="px-6 py-4 text-left font-medium">Customer Name</th>
                                    <th class="px-6 py-4 text-left font-medium">Table</th>
                                    <th class="px-6 py-4 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(trx, index) in successTransactions" :key="trx.id"
                                    class="border-t hover:bg-muted/40 transition">
                                    <td class="px-6 py-4">{{ index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ trx.cust_name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ trx.table?.name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end items-center gap-2">
                                            <button @click="printReceiptInline(trx.id)" type="button"
                                                class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-100 text-violet-600 text-xs font-medium hover:bg-violet-500 hover:text-white transition">
                                                <Printer :size="14" /> Cetak Struk
                                            </button>
                                            <Link :href="`/transaction/cashier/${trx.id}`"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition">
                                                <Eye :size="14" /> Detail
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="successTransactions.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-muted-foreground">
                                        Tidak ada pesanan hari ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
