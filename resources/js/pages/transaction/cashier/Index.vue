<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Eye, CheckCircle, Printer } from 'lucide-vue-next';
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Notyf } from 'notyf';
import axios from 'axios';

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
    try {
        const { data: trx } = await axios.get(`/transaction/cashier/${id}/receipt-data`);

        const fmt = (val: number) =>
            new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);

        const fmtDate = (val: string) => {
            const d = new Date(val);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        };

        const itemsHtml = trx.details.map((d: any) => `
            <div style="margin-bottom:1.5mm">
                <div class="row">
                    <span>${d.menu?.name ?? '-'}</span>
                    <span>${fmt(Number(d.price) * d.amount)}</span>
                </div>
                <div style="padding-left:1mm;font-size:11px">${d.amount} x ${fmt(Number(d.price))}</div>
                ${d.description ? `<div style="padding-left:1mm;font-style:italic;font-size:11px">${d.description}</div>` : ''}
            </div>`).join('');

        const html = `<!DOCTYPE html><html><head><title>Struk #${trx.id}</title>
<style>
  @page{size:58mm 210mm;margin:0}
  body{font-family:'Consolas','Courier New',monospace;font-size:12px;line-height:1.2;margin:0;padding:0;color:#000;-webkit-font-smoothing:none}
  .r{width:44mm;padding:2mm;margin:0 auto}
  .tc{text-align:center}
  .row{display:flex;justify-content:space-between;gap:1mm;word-break:break-word}
  hr{border:none;border-top:1px dashed #000;margin:2mm 0}
  .sub{font-size:11px}
  @media print{body{padding:0}.r{width:44mm;padding:2mm}}
</style></head><body><div class="r">
  <div class="tc" style="margin-bottom:2mm">
    <h2 style="margin:0;font-size:14px">${trx.cafe.name}</h2>
    ${trx.cafe.address ? `<p class="sub" style="margin:0">${trx.cafe.address}</p>` : ''}
    <p class="sub" style="margin:0">main@arlettaluxury.com</p>
    <p class="sub" style="margin:0">085742089646</p>
  </div>
  <hr>
  <div style="margin-bottom:1.5mm">
    <div class="row"><span>No. Transaksi</span><span>#${trx.id}</span></div>
    <div class="row"><span>Tanggal</span><span>${fmtDate(trx.updated_at)}</span></div>
    <div class="row"><span>Customer</span><span>${trx.cust_name ?? '-'}</span></div>
    ${trx.table ? `<div class="row"><span>Table</span><span>${trx.table.name}</span></div>` : ''}
    <div class="row"><span>Pembayaran</span><span>${trx.payment_type}</span></div>
  </div>
  <hr>
  <div style="margin-bottom:1.5mm">${itemsHtml}</div>
  <hr>
  <div>
    <div class="row"><span>Subtotal</span><span>${fmt(Number(trx.price))}</span></div>
    <div class="row"><span>Fee</span><span>${fmt(Number(trx.fee))}</span></div>
    <hr>
    <div class="row" style="font-weight:bold;font-size:13px">
      <span>Total</span><span>${fmt(Number(trx.total_price))}</span>
    </div>
  </div>
  <hr>
  <div class="tc sub"><p>Terima kasih atas kunjungan Anda!</p></div>
</div>
<script>window.onload=function(){window.print();window.onafterprint=function(){window.close()}}<\/script>
</body></html>`;

        const popup = window.open('', '_blank', 'width=300,height=500,scrollbars=yes');
        if (popup) {
            popup.document.write(html);
            popup.document.close();
            notyf.success('Struk berhasil dicetak');
        } else {
            notyf.error('Popup diblokir browser. Izinkan popup untuk mencetak struk.');
        }
    } catch {
        notyf.error('Gagal memuat data struk');
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
