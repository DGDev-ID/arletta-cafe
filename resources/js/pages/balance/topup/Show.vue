<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { QrCode, CheckCircle2, XCircle, ArrowLeft, RefreshCw } from 'lucide-vue-next';
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
    qris_content: string;
}

const props = defineProps<{ topup: BalanceTopup }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Balance', href: '/balance' },
    { title: 'Detail Topup', href: '#' },
];

const status       = ref<'pending' | 'success' | 'failed'>(props.topup.status);
const isLoading    = ref(false);
const errorMsg     = ref('');
const pollInterval = ref<ReturnType<typeof setInterval> | null>(null);
const pollCount    = ref(0);
const MAX_POLLS    = 60; // 60 × 5s = 5 minutes

const qrImageUrl = computed(() =>
    props.topup.qris_content
        ? `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(props.topup.qris_content)}`
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
    checkStatus();
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
        <div class="min-h-screen bg-muted/40 py-8">
            <div class="mx-auto max-w-lg space-y-4 px-4">

                <!-- Back link + heading -->
                <div>
                    <a href="/balance" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground">
                        <ArrowLeft class="h-4 w-4" />
                        Kembali ke Balance
                    </a>
                    <div class="mt-3">
                        <Heading title="Topup QRIS" description="Scan QRIS di bawah ini untuk menyelesaikan pembayaran" />
                    </div>
                </div>

                <!-- Main Card -->
                <div class="overflow-hidden rounded-2xl border bg-background shadow-sm">

                    <!-- Status Banner -->
                    <div
                        class="flex items-center gap-3 px-6 py-4 text-sm font-medium"
                        :class="{
                            'bg-yellow-50 text-yellow-700 border-b border-yellow-100': status === 'pending',
                            'bg-green-50 text-green-700 border-b border-green-100':   status === 'success',
                            'bg-red-50 text-red-700 border-b border-red-100':         status === 'failed',
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
                            <div class="flex items-center justify-center rounded-2xl border bg-white p-5 shadow-sm">
                                <img
                                    v-if="qrImageUrl"
                                    :src="qrImageUrl"
                                    alt="QRIS Code"
                                    class="h-56 w-56 rounded-lg"
                                />
                                <div v-else class="flex h-56 w-56 flex-col items-center justify-center gap-2 text-muted-foreground">
                                    <QrCode class="h-16 w-16" />
                                    <span class="text-xs">QRIS tidak tersedia</span>
                                </div>
                            </div>

                            <!-- Amount Info -->
                            <div class="w-full rounded-xl border bg-muted/40 p-4 text-center">
                                <p class="mb-1 text-xs text-muted-foreground">Total yang harus dibayar</p>
                                <p class="text-3xl font-bold text-primary">{{ formatRupiah(topup.total_amount) }}</p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Topup: {{ formatRupiah(topup.topup_amount) }} + Kode unik: Rp {{ formatRupiahRaw(topup.unique_amount) }}
                                </p>
                            </div>

                            <p class="text-center text-xs text-muted-foreground">
                                QR berlaku selama 5 menit • QRIS History ID: <code class="font-mono text-primary">{{ topup.qris_history_id }}</code>
                            </p>
                        </div>

                        <!-- Success State -->
                        <div v-else-if="status === 'success'" class="flex flex-col items-center gap-4 py-4 text-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                                <CheckCircle2 class="h-10 w-10 text-green-600" />
                            </div>
                            <div>
                                <p class="text-lg font-bold text-foreground">Pembayaran Berhasil!</p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Saldo <span class="font-semibold text-green-600">{{ formatRupiah(topup.topup_amount) }}</span> telah ditambahkan ke akun.
                                </p>
                            </div>
                            <a
                                href="/balance"
                                class="mt-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                            >
                                Lihat Saldo
                            </a>
                        </div>

                        <!-- Failed State -->
                        <div v-else class="flex flex-col items-center gap-4 py-4 text-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-100">
                                <XCircle class="h-10 w-10 text-red-500" />
                            </div>
                            <div>
                                <p class="text-lg font-bold text-foreground">Pembayaran Gagal</p>
                                <p class="mt-1 text-sm text-muted-foreground">QRIS kadaluarsa atau pembayaran dibatalkan.</p>
                            </div>
                            <a
                                href="/balance"
                                class="mt-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                            >
                                Coba Topup Lagi
                            </a>
                        </div>
                    </div>

                    <!-- Transaction Details -->
                    <div class="border-t px-6 py-5">
                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Detail Transaksi</h4>
                        <dl class="grid grid-cols-2 gap-y-3 text-sm">
                            <dt class="text-muted-foreground">ID Topup</dt>
                            <dd class="text-right font-mono text-foreground">#{{ topup.id }}</dd>
                            <dt class="text-muted-foreground">Jumlah Topup</dt>
                            <dd class="text-right text-foreground">{{ formatRupiah(topup.topup_amount) }}</dd>
                            <dt class="text-muted-foreground">Kode Unik</dt>
                            <dd class="text-right text-foreground">Rp {{ formatRupiahRaw(topup.unique_amount) }}</dd>
                            <dt class="text-muted-foreground">Total Bayar</dt>
                            <dd class="text-right font-semibold text-primary">{{ formatRupiah(topup.total_amount) }}</dd>
                            <dt class="text-muted-foreground">QRIS History ID</dt>
                            <dd class="text-right font-mono text-foreground">{{ topup.qris_history_id ?? '-' }}</dd>
                            <dt class="text-muted-foreground">Dibuat</dt>
                            <dd class="text-right text-muted-foreground">{{ formatDate(topup.created_at) }}</dd>
                        </dl>
                    </div>

                    <div v-if="errorMsg" class="px-6 pb-4">
                        <p class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{{ errorMsg }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
