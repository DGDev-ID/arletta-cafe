<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { QrCode, CheckCircle2, XCircle, Clock, ArrowLeft, RefreshCw } from 'lucide-vue-next';
import axios from 'axios';

interface BalanceTopup {
    id: number;
    balance_id: number;
    topup_amount: string;
    unique_amount: string;
    total_amount: string;
    qris_history_id: number | null;
    status: 'pending' | 'success' | 'failed';
    created_at: string;
    updated_at: string;
}

const props = defineProps<{ topup: BalanceTopup }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Balance', href: '/balance' },
    { title: 'Detail Topup', href: '#' },
];

const status      = ref<'pending' | 'success' | 'failed'>(props.topup.status);
const isLoading   = ref(false);
const errorMsg    = ref('');
const pollInterval = ref<ReturnType<typeof setInterval> | null>(null);
const pollCount   = ref(0);
const MAX_POLLS   = 60; // Max 60 × 5s = 5 menit

// Generate QR code image URL using a public QR generator service based on the topup amount
// (The actual QRIS string would come from the backend; here we show the amount info prominently)
const qrisString = computed(() => props.topup.qris_history_id
    ? `QRIS #${props.topup.qris_history_id} • Rp ${formatRupiahRaw(props.topup.total_amount)}`
    : null
);

const qrImageUrl = computed(() =>
    props.topup.qris_history_id
        ? `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=QRISLY-${props.topup.qris_history_id}-TOPUP-${props.topup.total_amount}&color=7c3aed&bgcolor=fff`
        : null
);

function formatRupiahRaw(val: string | number) {
    return new Intl.NumberFormat('id-ID').format(Number(val));
}

function formatRupiah(val: string | number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));
}

function formatDate(val: string) {
    return new Date(val).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

async function checkStatus() {
    if (status.value !== 'pending') return;
    if (pollCount.value >= MAX_POLLS) {
        stopPolling();
        return;
    }

    try {
        isLoading.value = true;
        errorMsg.value  = '';
        const { data } = await axios.get(`/api/balance/topup/${props.topup.id}/check`);
        if (data?.data?.status) {
            status.value = data.data.status;
        }
        if (status.value !== 'pending') {
            stopPolling();
        }
    } catch (e: any) {
        errorMsg.value = e?.response?.data?.message ?? 'Gagal memeriksa status.';
    } finally {
        isLoading.value = false;
        pollCount.value++;
    }
}

function startPolling() {
    if (status.value !== 'pending') return;
    pollInterval.value = setInterval(checkStatus, 5000);
    checkStatus(); // immediate first check
}

function stopPolling() {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
}

onMounted(startPolling);
onUnmounted(stopPolling);
</script>

<template>
    <Head title="Detail Topup" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col items-center gap-6 p-4 md:p-8">
            <div class="w-full max-w-lg">
                <div class="mb-4 flex items-center gap-2">
                    <a href="/balance" class="flex items-center gap-1.5 text-sm text-white/50 transition hover:text-white">
                        <ArrowLeft class="h-4 w-4" />
                        Kembali ke Balance
                    </a>
                </div>
                <Heading title="Topup QRIS" description="Scan QRIS di bawah ini untuk menyelesaikan pembayaran" />
            </div>

            <!-- Main Card -->
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm">

                <!-- Status Banner -->
                <div
                    class="flex items-center gap-3 px-6 py-4 text-sm font-medium transition-all"
                    :class="{
                        'bg-amber-500/15 text-amber-300':  status === 'pending',
                        'bg-emerald-500/15 text-emerald-300': status === 'success',
                        'bg-red-500/15 text-red-300':      status === 'failed',
                    }"
                >
                    <RefreshCw v-if="status === 'pending'" class="h-4 w-4 animate-spin" />
                    <CheckCircle2 v-else-if="status === 'success'" class="h-4 w-4" />
                    <XCircle v-else class="h-4 w-4" />
                    <span>
                        <template v-if="status === 'pending'">Menunggu pembayaran… (otomatis memeriksa setiap 5 detik)</template>
                        <template v-else-if="status === 'success'">✅ Pembayaran berhasil! Saldo telah ditambahkan.</template>
                        <template v-else>❌ Pembayaran gagal atau kadaluarsa.</template>
                    </span>
                </div>

                <div class="px-6 py-8">
                    <!-- QR Code Display -->
                    <div v-if="status === 'pending'" class="flex flex-col items-center gap-6">
                        <div class="relative flex items-center justify-center rounded-2xl bg-white p-5 shadow-2xl">
                            <img
                                v-if="qrImageUrl"
                                :src="qrImageUrl"
                                alt="QRIS Code"
                                class="h-56 w-56 rounded-lg"
                            />
                            <div v-else class="flex h-56 w-56 flex-col items-center justify-center gap-2 text-gray-400">
                                <QrCode class="h-16 w-16" />
                                <span class="text-xs">QRIS tidak tersedia</span>
                            </div>
                        </div>

                        <!-- Amount Info -->
                        <div class="w-full rounded-xl bg-white/5 p-4 text-center">
                            <p class="mb-1 text-xs text-white/40">Total yang harus dibayar</p>
                            <p class="text-3xl font-bold text-violet-400">{{ formatRupiah(topup.total_amount) }}</p>
                            <p class="mt-2 text-xs text-white/30">
                                Topup: {{ formatRupiah(topup.topup_amount) }} + Kode unik: Rp {{ formatRupiahRaw(topup.unique_amount) }}
                            </p>
                        </div>

                        <p class="text-center text-xs text-white/30">
                            QR berlaku selama 5 menit • QRIS History ID: <code class="text-violet-400">{{ topup.qris_history_id }}</code>
                        </p>
                    </div>

                    <!-- Success State -->
                    <div v-else-if="status === 'success'" class="flex flex-col items-center gap-4 py-4 text-center">
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500/20">
                            <CheckCircle2 class="h-10 w-10 text-emerald-400" />
                        </div>
                        <div>
                            <p class="text-lg font-bold text-white">Pembayaran Berhasil!</p>
                            <p class="mt-1 text-sm text-white/50">
                                Saldo <span class="font-semibold text-emerald-400">{{ formatRupiah(topup.topup_amount) }}</span> telah ditambahkan ke akun.
                            </p>
                        </div>
                        <a
                            href="/balance"
                            class="mt-2 rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white transition hover:bg-emerald-500"
                        >
                            Lihat Saldo
                        </a>
                    </div>

                    <!-- Failed State -->
                    <div v-else class="flex flex-col items-center gap-4 py-4 text-center">
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-500/20">
                            <XCircle class="h-10 w-10 text-red-400" />
                        </div>
                        <div>
                            <p class="text-lg font-bold text-white">Pembayaran Gagal</p>
                            <p class="mt-1 text-sm text-white/50">QRIS kadaluarsa atau pembayaran dibatalkan.</p>
                        </div>
                        <a
                            href="/balance"
                            class="mt-2 rounded-xl bg-violet-600 px-6 py-3 font-semibold text-white transition hover:bg-violet-500"
                        >
                            Coba Topup Lagi
                        </a>
                    </div>
                </div>

                <!-- Detail Info -->
                <div class="border-t border-white/10 px-6 py-4">
                    <h4 class="mb-3 text-xs font-medium uppercase tracking-widest text-white/40">Detail Transaksi</h4>
                    <dl class="grid grid-cols-2 gap-y-3 text-sm">
                        <dt class="text-white/50">ID Topup</dt>
                        <dd class="text-right font-mono text-white">#{{ topup.id }}</dd>
                        <dt class="text-white/50">Jumlah Topup</dt>
                        <dd class="text-right text-white">{{ formatRupiah(topup.topup_amount) }}</dd>
                        <dt class="text-white/50">Kode Unik</dt>
                        <dd class="text-right text-white">Rp {{ formatRupiahRaw(topup.unique_amount) }}</dd>
                        <dt class="text-white/50">Total Bayar</dt>
                        <dd class="text-right font-semibold text-violet-400">{{ formatRupiah(topup.total_amount) }}</dd>
                        <dt class="text-white/50">QRIS History ID</dt>
                        <dd class="text-right font-mono text-white">{{ topup.qris_history_id ?? '-' }}</dd>
                        <dt class="text-white/50">Dibuat</dt>
                        <dd class="text-right text-white/70">{{ formatDate(topup.created_at) }}</dd>
                    </dl>
                </div>

                <div v-if="errorMsg" class="px-6 py-3">
                    <p class="rounded-lg bg-red-500/10 px-3 py-2 text-xs text-red-400">{{ errorMsg }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
