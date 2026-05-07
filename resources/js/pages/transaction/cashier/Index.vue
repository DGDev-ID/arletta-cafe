<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Eye, CheckCircle, Printer } from 'lucide-vue-next';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { Notyf } from 'notyf';
import axios from 'axios';
import qz from 'qz-tray';

// ── Audio / Speech ────────────────────────────────────────────────────────
let audioCtx: AudioContext | null = null;
const userHasInteracted = ref(false);

const unlockAudio = () => {
    userHasInteracted.value = true;
    if (!audioCtx) audioCtx = new AudioContext();
    if (audioCtx.state === 'suspended') audioCtx.resume();
    document.removeEventListener('click', unlockAudio);
    document.removeEventListener('touchstart', unlockAudio);
};

const speak = (text: string) => {
    if (!userHasInteracted.value) return;
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = 1;
        window.speechSynthesis.speak(utterance);
    }
};

const playBeep = () => {
    if (!audioCtx || audioCtx.state !== 'running') return;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.frequency.value = 700;
    osc.type = 'sine';
    gain.gain.value = 0.3;
    osc.start();
    osc.stop(audioCtx.currentTime + 0.25);
    setTimeout(() => {
        const osc2 = audioCtx!.createOscillator();
        const gain2 = audioCtx!.createGain();
        osc2.connect(gain2);
        gain2.connect(audioCtx!.destination);
        osc2.frequency.value = 900;
        osc2.type = 'sine';
        gain2.gain.value = 0.3;
        osc2.start();
        osc2.stop(audioCtx!.currentTime + 0.25);
    }, 300);
};

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

interface TransactionDetailItem {
    id: number;
    transaction: {
        id: number;
        cafe?: { id: number; name: string } | null;
        table?: { id: number; name: string } | null;
        cust_name?: string | null;
        payment_type?: string | null;
    };
    menu?: { id: number; name: string } | null;
    amount: number;
    price: string;
    description?: string | null;
    status?: string | null;
}


const props = defineProps<{
    pendingTransactions: Transaction[];
    openBillPendingTransactions: Transaction[];
    inOrderTransactions: Transaction[];
    successTransactions: Transaction[];
    openBillPendingDetails: TransactionDetailItem[];
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
let prevOpenBillIds = new Set((props.openBillPendingTransactions || []).map((t: any) => t.id));
let prevOpenBillDetailIds = new Set((props.openBillPendingDetails || []).map((d: any) => d.id));

// Maps to resolve context (table/menu) for new items

let pollInterval: ReturnType<typeof setInterval> | null = null;

const makeSuccessInOrder = (id: number) => {
    if (confirm('Selesaikan transaksi ini? Status akan diubah ke success.')) {
        router.patch(`/transaction/cashier/${id}/success-in-order`);
    }
};

const makeDetailSuccess = (id: number) => {
    if (confirm('Tandai pesanan ini selesai?')) {
        router.patch(`/transaction/cashier/detail/${id}/success`);
    }
};

const selectedOpenBillDetailIds = ref<number[]>([]);

const selectedOpenBillDetailIdSet = computed(() => new Set(selectedOpenBillDetailIds.value));
const selectedOpenBillDetailCount = computed(() => selectedOpenBillDetailIds.value.length);
const isAllOpenBillDetailsSelected = computed(() => {
    if (!props.openBillPendingDetails.length) return false;
    return props.openBillPendingDetails.every((d) => selectedOpenBillDetailIdSet.value.has(d.id));
});

const toggleOpenBillDetailSelection = (id: number) => {
    if (selectedOpenBillDetailIdSet.value.has(id)) {
        selectedOpenBillDetailIds.value = selectedOpenBillDetailIds.value.filter((selectedId) => selectedId !== id);
        return;
    }

    selectedOpenBillDetailIds.value = [...selectedOpenBillDetailIds.value, id];
};

const toggleSelectAllOpenBillDetails = () => {
    if (isAllOpenBillDetailsSelected.value) {
        selectedOpenBillDetailIds.value = [];
        return;
    }

    selectedOpenBillDetailIds.value = props.openBillPendingDetails.map((d) => d.id);
};

watch(
    () => props.openBillPendingDetails,
    (details) => {
        const currentIds = new Set(details.map((d) => d.id));
        selectedOpenBillDetailIds.value = selectedOpenBillDetailIds.value.filter((id) => currentIds.has(id));
    },
);

const isMobileOrTabletDevice = () =>
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
    || ((navigator.userAgent.includes('Mac') || navigator.userAgent.includes('Linux')) && navigator.maxTouchPoints > 1);

const getQzPrinterConfig = async () => {
    if (!qz.websocket.isActive()) {
        qz.security.setCertificatePromise(resolve => resolve(null));
        qz.security.setSignaturePromise(() => resolve => resolve(null));
        await qz.websocket.connect();
    }

    const printers = await qz.printers.find();
    let printerName = await qz.printers.getDefault();

    const posPrinter = printers.find((p: string) =>
        p.toLowerCase().includes('thermal')
        || p.toLowerCase().includes('pos')
        || p.toLowerCase().includes('58')
    );

    if (posPrinter) printerName = posPrinter;

    if (!printerName) {
        notyf.error('Printer tidak ditemukan');
        return null;
    }

    return qz.configs.create(printerName, { encoding: 'ISO-8859-1', scaleContent: true });
};

const buildDetailReceiptRaw = (detail: any) => {
    const init = '\x1B\x40';
    const alignCenter = '\x1B\x61\x01';
    const alignLeft = '\x1B\x61\x00';
    const cut = '\x1D\x56\x41\x00';

    let str = '';
    str += init + alignCenter;
    str += (detail.transaction.cafe?.name || 'CAFE') + '\n';
    str += alignLeft;
    str += `No: #${detail.transaction.id}` + '\n';
    str += `Cust: ${detail.transaction.cust_name || '-'} ` + '\n';
    if (detail.transaction.table) str += `Table: ${detail.transaction.table.name}` + '\n';
    str += '-------------------------------' + '\n';
    str += (detail.menu?.name || '-') + '\n';
    str += `${detail.amount} x ${detail.price}` + '\n';
    if (detail.description) str += detail.description + '\n';
    str += '-------------------------------' + '\n';
    str += 'Terima kasih\n\n\n' + cut;

    return str;
};

const printDetailReceiptInline = async (detailId: number) => {
    const isMobileOrTablet = isMobileOrTabletDevice();

    if (isMobileOrTablet) {
        const responseUrl = `${window.location.origin}/bluetooth-receipt/detail/${detailId}`;
        window.location.href = `my.bluetoothprint.scheme://${responseUrl}`;
        return;
    }

    try {
        const { data: detail } = await axios.get(`/transaction/cashier/detail/${detailId}/receipt-data`);

        const config = await getQzPrinterConfig();
        if (!config) return;

        await qz.print(config, [{ type: 'raw', format: 'command', data: buildDetailReceiptRaw(detail) }]);
        notyf.success('Struk berhasil dicetak');
    } catch (e: any) {
        console.error(e);
        notyf.error('Print gagal: ' + (e.message || 'QZ error'));
    }
};

const printSelectedDetailReceiptsInline = async () => {
    if (!selectedOpenBillDetailIds.value.length) {
        notyf.error('Pilih minimal 1 item detail untuk dicetak');
        return;
    }

    const selectedIds = [...selectedOpenBillDetailIds.value];
    const isMobileOrTablet = isMobileOrTabletDevice();

    if (isMobileOrTablet) {
        const responseUrl = `${window.location.origin}/bluetooth-receipt/detail-bulk?detail_ids=${encodeURIComponent(selectedIds.join(','))}`;
        window.location.href = `my.bluetoothprint.scheme://${responseUrl}`;
        return;
    }

    try {
        const details = await Promise.all(
            selectedIds.map(async (id) => {
                const { data } = await axios.get(`/transaction/cashier/detail/${id}/receipt-data`);
                return data;
            })
        );

        const config = await getQzPrinterConfig();
        if (!config) return;

        const combinedReceipt = details.map((detail) => buildDetailReceiptRaw(detail)).join('');
        await qz.print(config, [{ type: 'raw', format: 'command', data: combinedReceipt }]);

        notyf.success(`${details.length} struk berhasil dicetak`);
    } catch (e: any) {
        console.error(e);
        notyf.error('Print bulk gagal: ' + (e.message || 'QZ error'));
    }
};

const printReceiptInline = async (id: number) => {
    // Deteksi perangkat mobile/tablet menggunakan User Agent & Touch Support
    const isMobileOrTablet = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) 
        || ((navigator.userAgent.includes('Mac') || navigator.userAgent.includes('Linux')) && navigator.maxTouchPoints > 1);

    // BUKA KOMENTAR DI BAWAH INI UNTUK DEBUGGING (Akan memunculkan pop-up info device)
    // alert("UserAgent: " + navigator.userAgent + "\nTouchPoints: " + navigator.maxTouchPoints + "\nIsMobile: " + isMobileOrTablet);

    if (isMobileOrTablet) {
        const responseUrl = `${window.location.origin}/bluetooth-receipt/${id}`;
        window.location.href = `my.bluetoothprint.scheme://${responseUrl}`;
        return;
    }

    // Jika display desktop, gunakan QZ Tray
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
    document.addEventListener('click', unlockAudio);
    document.addEventListener('touchstart', unlockAudio);

    pollInterval = setInterval(() => {
        router.reload({
            only: ['pendingTransactions', 'inOrderTransactions', 'openBillPendingTransactions', 'openBillPendingDetails', 'flash'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                const newPendingIds = new Set(props.pendingTransactions.map(t => t.id));
                const newInOrderIds = new Set(props.inOrderTransactions.map(t => t.id));
                const newOpenBillIds = new Set((props.openBillPendingTransactions || []).map((t: any) => t.id));
                const newOpenBillDetailIds = new Set((props.openBillPendingDetails || []).map((d: any) => d.id));

                // New pending manual transactions
                const newPending = props.pendingTransactions.filter(t => !prevPendingIds.has(t.id));
                if (newPending.length > 0) {
                    playBeep();
                    newPending.forEach(t => {
                        const table = t.table?.name;
                        const msg = table
                            ? `Pesanan baru masuk dari meja ${table}`
                            : `Pesanan baru masuk`;
                        notyf.success(msg);
                        speak(msg);
                    });
                }

                // New open bill pending transactions
                const newOpenBill = (props.openBillPendingTransactions || []).filter((t: any) => !prevOpenBillIds.has(t.id));
                if (newOpenBill.length > 0) {
                    playBeep();
                    newOpenBill.forEach((t: any) => {
                        const table = t.table?.name;
                        const msg = table
                            ? `Open bill baru dari meja ${table}`
                            : `Open bill baru masuk`;
                        notyf.success(msg);
                        speak(msg);
                    });
                }

                // New open bill detail items (tambah menu di open bill)
                const newOpenBillDetails = (props.openBillPendingDetails || []).filter((d: any) => !prevOpenBillDetailIds.has(d.id));
                if (newOpenBillDetails.length > 0) {
                    playBeep();
                    newOpenBillDetails.forEach((d: any) => {
                        const table = d.transaction?.table?.name;
                        const menu = d.menu?.name;
                        const msg = table && menu
                            ? `Menu baru dari meja ${table}, ${menu}`
                            : table
                                ? `Menu baru dari meja ${table}`
                                : `Menu baru masuk`;
                        notyf.success(msg);
                        speak(msg);
                    });
                }

                if ([...newInOrderIds].some(id => !prevInOrderIds.has(id))) {
                    notyf.success('Data in order baru terdeteksi');
                }

                prevPendingIds = newPendingIds;
                prevInOrderIds = newInOrderIds;
                prevOpenBillIds = newOpenBillIds;
                prevOpenBillDetailIds = newOpenBillDetailIds;
            },
        });
    }, 3000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    document.removeEventListener('click', unlockAudio);
    document.removeEventListener('touchstart', unlockAudio);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Cashier" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <Heading variant="small" title="Cashier" description="Kelola transaksi pending manual dan in order." />

                <!-- Audio unlock banner -->
                <div v-if="!userHasInteracted"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm cursor-pointer select-none"
                    @click="unlockAudio">
                    <span class="text-lg">🔔</span>
                    <span>Klik di sini untuk mengaktifkan notifikasi audio pesanan masuk.</span>
                </div>
                <div v-else class="flex items-center gap-2 px-4 py-2 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm">
                    <span class="text-base">🔊</span>
                    <span>Notifikasi audio aktif — akan berbunyi saat ada pesanan baru.</span>
                </div>

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

                <!-- Open Bill Pending Transactions -->
                <div class="space-y-3">
                    <h2 class="text-base font-semibold">Open Bill - Pending Transactions</h2>
                    <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-muted-foreground">
                                    <th class="px-6 py-4 text-left font-medium">No</th>
                                    <th class="px-6 py-4 text-left font-medium">Cafe</th>
                                    <th class="px-6 py-4 text-left font-medium">Table</th>
                                    <th class="px-6 py-4 text-left font-medium">Customer Name</th>
                                    <th class="px-6 py-4 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(trx, index) in openBillPendingTransactions" :key="trx.id" class="border-t hover:bg-muted/40 transition">
                                    <td class="px-6 py-4">{{ index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ trx.cafe?.name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ trx.table?.name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ trx.cust_name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <Link :href="`/transaction/cashier/${trx.id}`" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition">
                                            <Eye :size="14" /> Detail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="openBillPendingTransactions.length === 0">
                                    <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">Tidak ada open bill pending.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Open Bill Pending Details -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <h2 class="text-base font-semibold">Open Bill - Pending Details</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">{{ selectedOpenBillDetailCount }} dipilih</span>
                            <button @click="printSelectedDetailReceiptsInline" type="button"
                                :disabled="selectedOpenBillDetailCount === 0"
                                class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-100 text-violet-600 text-xs font-medium hover:bg-violet-500 hover:text-white transition disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-violet-100 disabled:hover:text-violet-600">
                                <Printer :size="14" /> Cetak Terpilih
                            </button>
                        </div>
                    </div>
                    <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-muted-foreground">
                                    <th class="px-4 py-4 text-center font-medium w-[52px]">
                                        <input
                                            type="checkbox"
                                            :checked="isAllOpenBillDetailsSelected"
                                            @change="toggleSelectAllOpenBillDetails"
                                            class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                        />
                                    </th>
                                    <th class="px-6 py-4 text-left font-medium">No</th>
                                    <th class="px-6 py-4 text-left font-medium">Cafe</th>
                                    <th class="px-6 py-4 text-left font-medium">Table</th>
                                    <th class="px-6 py-4 text-left font-medium">Menu & Deskripsi</th>
                                    <th class="px-6 py-4 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(d, idx) in openBillPendingDetails" :key="d.id" class="border-t hover:bg-muted/40 transition">
                                    <td class="px-4 py-4 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="selectedOpenBillDetailIdSet.has(d.id)"
                                            @change="toggleOpenBillDetailSelection(d.id)"
                                            class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                        />
                                    </td>
                                    <td class="px-6 py-4">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ d.transaction?.cafe?.name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ d.transaction?.table?.name ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">{{ d.menu?.name ?? '-' }}</div>
                                        <div class="text-xs text-muted-foreground">{{ d.description ?? '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end items-center gap-2">
                                            <button @click="printDetailReceiptInline(d.id)" type="button" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-100 text-violet-600 text-xs font-medium hover:bg-violet-500 hover:text-white transition">
                                                <Printer :size="14" /> Cetak Struk
                                            </button>
                                            <button @click="makeDetailSuccess(d.id)" type="button" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-100 text-green-600 text-xs font-medium hover:bg-green-500 hover:text-white transition">
                                                <CheckCircle :size="14" /> Selesai
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="openBillPendingDetails.length === 0">
                                    <td colspan="6" class="px-6 py-10 text-center text-muted-foreground">Tidak ada detail open bill pending.</td>
                                </tr>
                            </tbody>
                        </table>
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
