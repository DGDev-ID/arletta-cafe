<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

interface Transaction {
    id: number;
    cust_name: string | null;
    status: string;
    created_at: string;
    table: { id: number; name: string } | null;
}

interface Cafe {
    id: number;
    unique_id: string;
    name: string;
    address: string;
}

const props = defineProps<{ cafe: Cafe }>();

const transactions = ref<Transaction[]>([]);
const previousIds = ref<Set<number>>(new Set());
const previousData = ref<Map<number, Transaction>>(new Map());
let interval: ReturnType<typeof setInterval> | null = null;

const speak = (text: string) => {
    if ('speechSynthesis' in window) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = 1;
        window.speechSynthesis.speak(utterance);
    }
};

const playNotification = () => {
    const ctx = new AudioContext();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 660;
    osc.type = 'sine';
    gain.gain.value = 0.3;
    osc.start();
    osc.stop(ctx.currentTime + 0.3);
};

const fetchQueue = async () => {
    try {
        const { data } = await axios.get<Transaction[]>(`/api/queue/public/${props.cafe.unique_id}`);
        const newIds = new Set(data.map(t => t.id));

        // Detect removed orders (completed)
        for (const [id, trx] of previousData.value) {
            if (!newIds.has(id)) {
                playNotification();
                const custName = trx.cust_name ?? 'Pelanggan';
                const tableName = trx.table?.name ?? 'tanpa meja';
                speak(`Pesanan atas nama ${custName} dengan nomor meja ${tableName} selesai dibuat`);
                break;
            }
        }

        previousIds.value = newIds;
        previousData.value = new Map(data.map(t => [t.id, t]));
        transactions.value = data;
    } catch (e) {
        // silently retry on next interval
    }
};

onMounted(() => {
    fetchQueue();
    interval = setInterval(fetchQueue, 3000);
});

onUnmounted(() => {
    if (interval) clearInterval(interval);
});

const formatTime = (dateStr: string) => {
    return new Date(dateStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head :title="`Queue - ${cafe.name}`" />

    <div
        class="relative min-h-screen overflow-auto"
        style="background-color: #1c1008; background-image: radial-gradient(circle, rgba(180,100,40,0.12) 1.5px, transparent 1.5px); background-size: 28px 28px;"
    >
        <!-- Warm vignette overlay -->
        <div class="pointer-events-none fixed inset-0 bg-gradient-to-br from-amber-950/60 via-transparent to-amber-950/60" />

        <div class="relative z-10 max-w-4xl mx-auto px-6 py-10">
            <!-- Header -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-3 mb-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-800/90 shadow-lg shadow-amber-900/40">
                        <span class="text-lg">☕</span>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-widest text-amber-400/80">Order Display</span>
                </div>
                <h1 class="text-3xl font-bold text-white tracking-tight">Antrian Pesanan</h1>
                <p class="text-amber-300/60 mt-1 text-sm">{{ cafe.name }}</p>
            </div>

            <!-- Empty state -->
            <div v-if="transactions.length === 0" class="flex flex-col items-center justify-center mt-24 gap-4">
                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-900/30 border border-amber-800/40">
                    <span class="text-4xl">🍵</span>
                </div>
                <p class="text-amber-300/50 text-lg font-medium">Tidak ada pesanan saat ini</p>
            </div>

            <!-- Transaction list -->
            <div class="space-y-4">
                <div
                    v-for="trx in transactions"
                    :key="trx.id"
                    class="rounded-2xl border border-amber-800/40 bg-amber-950/60 p-5 shadow-xl shadow-black/30 backdrop-blur-sm flex items-center justify-between gap-4"
                >
                    <!-- Left: order number + info -->
                    <div class="flex items-center gap-5">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-800/50 border border-amber-700/40 shadow-inner">
                            <span class="text-lg font-bold text-amber-400">#{{ trx.id }}</span>
                        </div>
                        <div>
                            <p class="text-base font-semibold text-white">{{ trx.cust_name ?? 'Tanpa Nama' }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span v-if="trx.table" class="text-xs font-medium text-amber-300/70">
                                    {{ trx.table.name }}
                                </span>
                                <span v-else class="text-xs font-medium text-amber-300/50">Tanpa Meja</span>
                                <span class="text-amber-800/80">·</span>
                                <span class="text-xs text-amber-300/50">{{ formatTime(trx.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: status badge -->
                    <div class="shrink-0">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-800/60 border border-amber-700/50 px-4 py-1.5 text-sm font-semibold text-amber-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse" />
                            Sedang Dibuat
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>