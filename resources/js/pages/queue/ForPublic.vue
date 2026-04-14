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

    <div class="min-h-screen bg-gray-900 text-white p-6">
        <div class="max-w-5xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold">📋 Antrian Pesanan</h1>
                <p class="text-gray-400 mt-1">{{ cafe.name }}</p>
            </div>

            <div v-if="transactions.length === 0" class="text-center text-gray-500 text-xl mt-20">
                Tidak ada pesanan saat ini
            </div>

            <div class="space-y-4">
                <div
                    v-for="trx in transactions"
                    :key="trx.id"
                    class="bg-gray-800 rounded-2xl p-5 border border-gray-700 shadow-lg flex items-center justify-between"
                >
                    <div class="flex items-center gap-5">
                        <span class="text-2xl font-bold text-yellow-400">#{{ trx.id }}</span>
                        <div>
                            <p class="text-lg font-semibold">{{ trx.cust_name ?? 'Tanpa Nama' }}</p>
                            <p class="text-sm text-gray-400">
                                <span v-if="trx.table">{{ trx.table.name }}</span>
                                <span v-else>Tanpa Meja</span>
                                <span class="ml-3">{{ formatTime(trx.created_at) }}</span>
                            </p>
                        </div>
                    </div>
                    <div>
                        <span class="bg-orange-500 text-white text-sm font-semibold px-4 py-1.5 rounded-full">
                            Sedang Dibuat
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>