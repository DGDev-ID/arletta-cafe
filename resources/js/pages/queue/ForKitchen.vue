<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

interface Menu {
    id: number;
    name: string;
}

interface Detail {
    id: number;
    amount: string;
    description: string | null;
    menu: Menu | null;
}

interface Transaction {
    id: number;
    cust_name: string | null;
    status: string;
    created_at: string;
    table: { id: number; name: string } | null;
    details: Detail[];
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
    osc.frequency.value = 880;
    osc.type = 'sine';
    gain.gain.value = 0.3;
    osc.start();
    osc.stop(ctx.currentTime + 0.3);
};

const fetchQueue = async () => {
    try {
        const { data } = await axios.get<Transaction[]>(`/api/queue/kitchen/${props.cafe.unique_id}`);
        const newIds = new Set(data.map(t => t.id));

        // Detect new orders
        for (const id of newIds) {
            if (!previousIds.value.has(id)) {
                playNotification();
                speak('Pesanan diterima');
                break;
            }
        }

        previousIds.value = newIds;
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
    <Head :title="`Kitchen Queue - ${cafe.name}`" />

    <div
        class="relative min-h-screen overflow-auto"
        style="background-color: #1c1008; background-image: radial-gradient(circle, rgba(180,100,40,0.12) 1.5px, transparent 1.5px); background-size: 28px 28px;"
    >
        <!-- Warm vignette overlay -->
        <div class="pointer-events-none fixed inset-0 bg-gradient-to-br from-amber-950/60 via-transparent to-amber-950/60" />

        <div class="relative z-10 max-w-7xl mx-auto px-6 py-8">
            <!-- Header -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-3 mb-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-800/90 shadow-lg shadow-amber-900/40">
                        <span class="text-lg">🍳</span>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-widest text-amber-400/80">Kitchen Display</span>
                </div>
                <h1 class="text-3xl font-bold text-white tracking-tight">Antrian Dapur</h1>
                <p class="text-amber-300/60 mt-1 text-sm">{{ cafe.name }}</p>
            </div>

            <!-- Empty state -->
            <div v-if="transactions.length === 0" class="flex flex-col items-center justify-center mt-24 gap-4">
                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-900/30 border border-amber-800/40">
                    <span class="text-4xl">☕</span>
                </div>
                <p class="text-amber-300/50 text-lg font-medium">Tidak ada pesanan saat ini</p>
            </div>

            <!-- Transaction grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="trx in transactions"
                    :key="trx.id"
                    class="rounded-2xl border border-amber-800/40 bg-amber-950/60 p-5 shadow-xl shadow-black/30 backdrop-blur-sm"
                >
                    <!-- Card header -->
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-bold text-amber-400">#{{ trx.id }}</span>
                            <span v-if="trx.table" class="text-xs font-semibold bg-amber-800/60 text-amber-300 border border-amber-700/50 px-2.5 py-0.5 rounded-full">
                                {{ trx.table.name }}
                            </span>
                        </div>
                        <span class="text-xs text-amber-400/50 font-medium">{{ formatTime(trx.created_at) }}</span>
                    </div>

                    <!-- Customer name -->
                    <p v-if="trx.cust_name" class="text-sm font-medium text-amber-100/80 mb-3">
                        {{ trx.cust_name }}
                    </p>

                    <!-- Divider -->
                    <div class="h-px bg-amber-800/30 mb-3" />

                    <!-- Menu items -->
                    <div class="space-y-2">
                        <div
                            v-for="detail in trx.details"
                            :key="detail.id"
                            class="flex justify-between items-center rounded-lg bg-amber-900/30 border border-amber-800/20 px-3 py-2"
                        >
                            <span class="text-sm font-medium text-white/90">{{ detail.menu?.name ?? '-' }}</span>
                            <span class="text-amber-400 font-bold text-sm">x{{ detail.amount }}</span>
                        </div>
                        <div
                            v-for="detail in trx.details.filter(d => d.description)"
                            :key="'note-' + detail.id"
                            class="flex items-start gap-1.5 px-3 pt-1"
                        >
                            <span class="text-amber-500 text-xs mt-0.5">📝</span>
                            <p class="text-xs text-amber-300/60 italic leading-relaxed">
                                <span class="font-medium not-italic text-amber-300/80">{{ detail.menu?.name }}:</span>
                                {{ detail.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>