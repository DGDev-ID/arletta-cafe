<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Eye, CheckCircle, Printer, ChevronDown } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { Notyf } from 'notyf';
import axios from 'axios';

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

// Beep berbeda (lebih tinggi) untuk notifikasi in_order QRIS
const playBeepQris = () => {
    if (!audioCtx || audioCtx.state !== 'running') return;
    const freqs = [800, 1000, 1200];
    freqs.forEach((freq, i) => {
        setTimeout(() => {
            const osc = audioCtx!.createOscillator();
            const gain = audioCtx!.createGain();
            osc.connect(gain);
            gain.connect(audioCtx!.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            gain.gain.value = 0.3;
            osc.start();
            osc.stop(audioCtx!.currentTime + 0.2);
        }, i * 250);
    });
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
    is_promo: boolean;
}

interface SelectedVariant {
    material_id: number;
    variant_id: number;
    material_name?: string | null;
    variant_name?: string | null;
}

interface TransactionDetailItem {
    id: number;
    transaction: {
        id: number;
        cafe?: { id: number; name: string } | null;
        table?: { id: number; name: string } | null;
        cust_name?: string | null;
        payment_type?: string | null;
        is_promo?: boolean;
    };
    menu?: { id: number; name: string } | null;
    amount: number;
    price: string;
    description?: string | null;
    status?: string | null;
    selected_variants?: SelectedVariant[] | null;
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

// ── RawBT Print (80mm = 48 chars wide) ────────────────────────────────────
const PRINT_WIDTH = 48;
const PRINT_LINE = '-'.repeat(PRINT_WIDTH);

const printPadRight = (left: string, right: string): string => {
    const space = PRINT_WIDTH - (left.length + right.length);
    return left + ' '.repeat(space > 0 ? space : 1) + right;
};

const printNumber = (val: number): string => new Intl.NumberFormat('id-ID').format(val);

const sendToRawBT = (bytes: number[]) => {
    const uint8 = new Uint8Array(bytes);
    let binary = '';
    uint8.forEach(b => (binary += String.fromCharCode(b)));
    window.location.href = 'rawbt:base64,' + btoa(binary);
};

const buildLogoBytes = async (): Promise<number[]> => {
    const PRINTER_DOT_WIDTH = 576;
    const LOGO_RENDER_WIDTH = 200;

    try {
        const response = await axios.get('/proxy/logo', { responseType: 'blob' });
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
                    bytes.push(
                        0x1D, 0x76, 0x30, 0x00,
                        bytesPerLine & 0xFF, (bytesPerLine >> 8) & 0xFF,
                        logoHeight & 0xFF, (logoHeight >> 8) & 0xFF
                    );
                    for (let y = 0; y < logoHeight; y++) {
                        for (let x = 0; x < bytesPerLine; x++) {
                            let byte = 0;
                            for (let bit = 0; bit < 8; bit++) {
                                const px = x * 8 + bit;
                                if (px < PRINTER_DOT_WIDTH) {
                                    const i = (y * PRINTER_DOT_WIDTH + px) * 4;
                                    const gray = (imageData.data[i] + imageData.data[i + 1] + imageData.data[i + 2]) / 3;
                                    if (gray < 128) byte |= (0x80 >> bit);
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

const printDetailReceiptInline = async (detailId: number) => {
    try {
        const { data: detail } = await axios.get(`/transaction/cashier/detail/${detailId}/receipt-data`);

        if (window.innerWidth >= 1024) {
            const res = await axios.post('http://localhost:3000/print', detail);
            if (res.status === 200 || res.status === 207) {
                notyf.success('Print sukses');
            }
            return;
        }

        const bytes: number[] = [];
        const encoder = new TextEncoder();
        const enc = (text: string) => bytes.push(...encoder.encode(text));

        bytes.push(0x1B, 0x40);
        bytes.push(...await buildLogoBytes());
        bytes.push(0x1B, 0x61, 0x01);
        enc('\n');
        enc((detail.transaction?.cafe?.name || 'CAFE') + '\n');
        enc(PRINT_LINE + '\n');
        bytes.push(0x1B, 0x61, 0x00);
        enc('No: #' + detail.transaction?.id + '\n');
        enc('Cust: ' + (detail.transaction?.cust_name || '-') + '\n');
        if (detail.transaction?.table) enc('Table: ' + detail.transaction.table.name + '\n');
        enc(PRINT_LINE + '\n');
        enc((detail.menu?.name || '-') + '\n');
        enc(detail.amount + ' x ' + printNumber(Number(detail.price)) + '\n');
        if (detail.selected_variants && detail.selected_variants.length > 0) {
            detail.selected_variants.forEach((sv: any) => {
                const label = sv.material_name ? sv.material_name + ': ' + (sv.variant_name || '-') : (sv.variant_name || '-');
                enc('  [' + label + ']\n');
            });
        }
        if (detail.description) enc(detail.description + '\n');
        enc(PRINT_LINE + '\n');
        bytes.push(0x1B, 0x61, 0x01);
        enc('Terima kasih\n');
        bytes.push(0x1B, 0x64, 0x05);
        bytes.push(0x1D, 0x56, 0x41, 0x00);
        sendToRawBT(bytes);
    } catch (e: any) {
        console.error(e);
        notyf.error('Print gagal: ' + (e.message || 'Error'));
    }
};

const printSelectedDetailReceiptsInline = async () => {
    if (!selectedOpenBillDetailIds.value.length) {
        notyf.error('Pilih minimal 1 item detail untuk dicetak');
        return;
    }

    try {
        const selectedIds = [...selectedOpenBillDetailIds.value];
        const details = await Promise.all(
            selectedIds.map(async (id) => {
                const { data } = await axios.get(`/transaction/cashier/detail/${id}/receipt-data`);
                return data;
            })
        );

        if (window.innerWidth >= 1024) {
            const res = await axios.post('http://localhost:3000/print', details);
            if (res.status === 200 || res.status === 207) {
                notyf.success('Print bulk sukses');
            }
            return;
        }

        const bytes: number[] = [];
        const encoder = new TextEncoder();
        const enc = (text: string) => bytes.push(...encoder.encode(text));

        bytes.push(0x1B, 0x40);
        bytes.push(...await buildLogoBytes());
        bytes.push(0x1B, 0x61, 0x01);
        enc('\n');
        enc((details[0]?.transaction?.cafe?.name || 'CAFE') + '\n');
        enc('OPEN BILL - BULK ITEM\n');
        enc(PRINT_LINE + '\n');
        bytes.push(0x1B, 0x61, 0x00);
        details.forEach((detail, index) => {
            enc((index + 1) + '. ' + (detail.menu?.name || '-') + '\n');
            enc('No: #' + (detail.transaction?.id || '-') + '\n');
            enc('Cust: ' + (detail.transaction?.cust_name || '-') + '\n');
            if (detail.transaction?.table?.name) enc('Table: ' + detail.transaction.table.name + '\n');
            enc(detail.amount + ' x ' + printNumber(Number(detail.price)) + '\n');
            if (detail.selected_variants && detail.selected_variants.length > 0) {
                detail.selected_variants.forEach((sv: any) => {
                    const label = sv.material_name ? sv.material_name + ': ' + (sv.variant_name || '-') : (sv.variant_name || '-');
                    enc('  [' + label + ']\n');
                });
            }
            if (detail.description) enc(detail.description + '\n');
            enc(PRINT_LINE + '\n');
        });
        bytes.push(0x1B, 0x61, 0x01);
        enc('Terima kasih\n');
        bytes.push(0x1B, 0x64, 0x05);
        bytes.push(0x1D, 0x56, 0x41, 0x00);
        sendToRawBT(bytes);
    } catch (e: any) {
        console.error(e);
        notyf.error('Print bulk gagal: ' + (e.message || 'Error'));
    }
};

const printReceiptInline = async (id: number, filterType: 'all' | 'FOOD' | 'BEVERAGE' = 'all') => {
    try {
        const { data: trx } = await axios.get(`/transaction/cashier/${id}/receipt-data`);

        if (window.innerWidth >= 1024) {
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

        bytes.push(0x1B, 0x40);
        bytes.push(...await buildLogoBytes());
        bytes.push(0x1B, 0x61, 0x01);
        bytes.push(0x1B, 0x45, 0x01);
        enc('\n');
        enc((trx.cafe?.name || 'CAFE') + '\n');
        bytes.push(0x1B, 0x45, 0x00);
        if (trx.cafe?.address) enc(trx.cafe.address + '\n');
        
        if (filterType === 'FOOD') {
            enc(PRINT_LINE + '\n');
            enc('--- ONLY FOOD ---\n');
        } else if (filterType === 'BEVERAGE') {
            enc(PRINT_LINE + '\n');
            enc('--- ONLY BEVERAGE ---\n');
        }
        
        enc(PRINT_LINE + '\n');
        bytes.push(0x1B, 0x61, 0x00);
        enc(printPadRight('No', '#' + trx.id) + '\n');
        enc(printPadRight('Tgl', fmtDate(trx.updated_at)) + '\n');
        enc(printPadRight('Cust', trx.cust_name || '-') + '\n');
        if (trx.table) enc(printPadRight('Table', trx.table.name) + '\n');
        enc(printPadRight('Pay', trx.payment_type) + '\n');
        enc(PRINT_LINE + '\n');
        detailsToPrint.forEach((d: any) => {
            enc((d.menu?.name || '-').substring(0, PRINT_WIDTH) + '\n');
            const qtyPrice = `${d.amount}x${printNumber(Number(d.menu?.price ?? 0))}`;
            enc(printPadRight(qtyPrice, printNumber(Number(d.price))) + '\n');
            if (d.selected_variants && d.selected_variants.length > 0) {
                d.selected_variants.forEach((sv: any) => {
                    const label = sv.material_name ? sv.material_name + ': ' + (sv.variant_name || '-') : (sv.variant_name || '-');
                    enc('  [' + label + ']\n');
                });
            }
            if (d.description) enc(' ' + d.description + '\n');
        });
        enc(PRINT_LINE + '\n');
        
        if (filterType === 'all') {
            enc(printPadRight('Subtotal', printNumber(Number(trx.price))) + '\n');
            enc(printPadRight('Fee', printNumber(Number(trx.fee))) + '\n');
            enc(PRINT_LINE + '\n');
            bytes.push(0x1B, 0x45, 0x01);
            if (trx.promo_id) {
                const promo = Number(trx.price) + Number(trx.fee) - Number(trx.total_price);
                enc(printPadRight('Discount', '-' + printNumber(Number(promo))) + '\n');
            }
            enc(printPadRight('TOTAL', printNumber(Number(trx.total_price))) + '\n');
        } else {
            const partialSubtotal = detailsToPrint.reduce((acc: number, d: any) => acc + Number(d.price), 0);
            enc(printPadRight('Subtotal', printNumber(partialSubtotal)) + '\n');
            enc(PRINT_LINE + '\n');
            bytes.push(0x1B, 0x45, 0x01);
            enc(printPadRight('TOTAL', printNumber(partialSubtotal)) + '\n');
        }
        
        bytes.push(0x1B, 0x45, 0x00);
        enc(PRINT_LINE + '\n');
        bytes.push(0x1B, 0x61, 0x01);
        enc('Terima kasih\n');
        bytes.push(0x1B, 0x64, 0x05);
        bytes.push(0x1D, 0x56, 0x41, 0x00);
        sendToRawBT(bytes);
    } catch (e: any) {
        console.error(e);
        notyf.error('Print gagal: ' + (e.message || 'Error'));
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
                const newPendingIds     = new Set(props.pendingTransactions.map(t => t.id));
                const newInOrderIds     = new Set(props.inOrderTransactions.map(t => t.id));
                const newOpenBillIds    = new Set((props.openBillPendingTransactions || []).map((t: any) => t.id));
                const newOpenBillDetailIds = new Set((props.openBillPendingDetails || []).map((d: any) => d.id));

                // ── Pending baru (manual atau QRIS belum dikonfirmasi) ──────
                const newPending = props.pendingTransactions.filter(t => !prevPendingIds.has(t.id));
                if (newPending.length > 0) {
                    playBeep();
                    newPending.forEach(t => {
                        const table    = t.table?.name;
                        const isQris   = t.payment_type?.toLowerCase() === 'qris';
                        const payLabel = isQris ? ' via QRIS' : '';
                        const msg = table
                            ? `Pesanan baru masuk dari meja ${table}${payLabel}`
                            : `Pesanan baru masuk${payLabel}`;
                        notyf.success(msg);
                        speak(msg);
                    });
                }

                // ── In order baru (termasuk QRIS yang baru dikonfirmasi kasir) ──
                const newInOrder = props.inOrderTransactions.filter(t => !prevInOrderIds.has(t.id));
                if (newInOrder.length > 0) {
                    newInOrder.forEach(t => {
                        const isQris = t.payment_type?.toLowerCase() === 'qris';
                        if (isQris) {
                            // Beep khusus QRIS — 3 nada naik
                            playBeepQris();
                            const table = t.table?.name;
                            const msg   = table
                                ? `Pesanan QRIS dari meja ${table} masuk antrian`
                                : `Pesanan QRIS masuk antrian`;
                            notyf.success(msg);
                            speak(msg);
                        } else {
                            playBeep();
                            notyf.success('Data in order baru terdeteksi');
                        }
                    });
                }

                // ── Open bill pending baru ─────────────────────────────────
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

                // ── Open bill detail baru (tambah menu) ───────────────────
                const newOpenBillDetails = (props.openBillPendingDetails || []).filter((d: any) => !prevOpenBillDetailIds.has(d.id));
                if (newOpenBillDetails.length > 0) {
                    playBeep();
                    newOpenBillDetails.forEach((d: any) => {
                        const table = d.transaction?.table?.name;
                        const menu  = d.menu?.name;
                        const msg   = table && menu
                            ? `Menu baru dari meja ${table}, ${menu}`
                            : table
                                ? `Menu baru dari meja ${table}`
                                : `Menu baru masuk`;
                        notyf.success(msg);
                        speak(msg);
                    });
                }

                prevPendingIds        = newPendingIds;
                prevInOrderIds        = newInOrderIds;
                prevOpenBillIds       = newOpenBillIds;
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
                                        <div v-if="d.selected_variants && d.selected_variants.length > 0" class="flex flex-wrap gap-1 mt-1">
                                            <span
                                                v-for="sv in d.selected_variants"
                                                :key="sv.variant_id"
                                                class="inline-flex items-center gap-1 text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-300 px-2 py-0.5 rounded-full"
                                            >
                                                <span class="text-[7px]">●</span>
                                                {{ sv.material_name ? sv.material_name + ': ' : '' }}{{ sv.variant_name ?? `#${sv.variant_id}` }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-0.5">{{ d.description ?? '-' }}</div>
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

                <!-- Pending Manual & QRIS Transactions -->
                <div class="space-y-3">
                    <h2 class="text-base font-semibold">Pending Transactions</h2>

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
                                    <th class="px-6 py-4 text-left font-medium">Payment</th>
                                    <th class="px-6 py-4 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="qrResult">
                                    <tr :key="qrResult.id" class="border-t hover:bg-muted/40 transition">
                                        <td class="px-6 py-4">1</td>
                                        <td class="px-6 py-4 font-medium">{{ qrResult.cafe?.name ?? '-' }}</td>
                                        <td class="px-6 py-4">{{ qrResult.cust_name ?? '-' }}</td>
                                        <td class="px-6 py-4 font-medium">{{ formatCurrency(qrResult.total_price) }}</td>
                                        <td class="px-6 py-4">
                                            <span :class="qrResult.payment_type === 'qris'
                                                ? 'bg-orange-100 text-orange-600 border border-orange-300'
                                                : qrResult.payment_type === 'debit'
                                                ? 'bg-blue-100 text-blue-600 border border-blue-300'
                                                : 'bg-gray-100 text-gray-600 border border-gray-300'"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase">
                                                {{ qrResult.payment_type }}
                                            </span>
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
                                        <td class="px-6 py-4">
                                            <span :class="trx.payment_type === 'qris'
                                                ? 'bg-orange-100 text-orange-600 border border-orange-300'
                                                : trx.payment_type === 'debit'
                                                ? 'bg-blue-100 text-blue-600 border border-blue-300'
                                                : 'bg-gray-100 text-gray-600 border border-gray-300'"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase">
                                                {{ trx.payment_type }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <Link :href="`/transaction/cashier/${trx.id}`"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition">
                                                <Eye :size="14" /> Detail
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="pendingTransactions.length === 0">
                                        <td colspan="6" class="px-6 py-10 text-center text-muted-foreground">
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
                                    <th class="px-6 py-4 text-left font-medium">Payment</th>
                                    <th class="px-6 py-4 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(trx, index) in inOrderTransactions" :key="trx.id"
                                    class="border-t hover:bg-muted/40 transition">
                                    <td class="px-6 py-4">{{ index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ trx.cust_name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ trx.table?.name ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <span :class="trx.payment_type === 'qris'
                                            ? 'bg-orange-100 text-orange-600 border border-orange-300'
                                            : trx.payment_type === 'debit'
                                            ? 'bg-blue-100 text-blue-600 border border-blue-300'
                                            : 'bg-gray-100 text-gray-600 border border-gray-300'"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase">
                                            {{ trx.payment_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end items-center gap-2">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-100 text-violet-600 text-xs font-medium hover:bg-violet-500 hover:text-white transition outline-none">
                                                    <Printer :size="14" /> Cetak Struk <ChevronDown :size="14" />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem @click="printReceiptInline(trx.id, 'all')">Semua menu</DropdownMenuItem>
                                                    <DropdownMenuItem @click="printReceiptInline(trx.id, 'FOOD')">Only food</DropdownMenuItem>
                                                    <DropdownMenuItem @click="printReceiptInline(trx.id, 'BEVERAGE')">Only beverage</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                            <button @click="makeSuccessInOrder(trx.id)" type="button"
                                                class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-100 text-green-600 text-xs font-medium hover:bg-green-500 hover:text-white transition">
                                                <CheckCircle :size="14" /> Selesai
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="inOrderTransactions.length === 0">
                                    <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">
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
                                            <DropdownMenu>
                                                <DropdownMenuTrigger class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-100 text-violet-600 text-xs font-medium hover:bg-violet-500 hover:text-white transition outline-none">
                                                    <Printer :size="14" /> Cetak Struk <ChevronDown :size="14" />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem @click="printReceiptInline(trx.id, 'all')">Semua menu</DropdownMenuItem>
                                                    <DropdownMenuItem @click="printReceiptInline(trx.id, 'FOOD')">Only food</DropdownMenuItem>
                                                    <DropdownMenuItem @click="printReceiptInline(trx.id, 'BEVERAGE')">Only beverage</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
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