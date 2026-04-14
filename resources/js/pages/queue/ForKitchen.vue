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

    <div class="min-h-screen bg-gray-900 text-white p-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold">🍳 Kitchen Queue</h1>
                <p class="text-gray-400 mt-1">{{ cafe.name }}</p>
            </div>

            <div v-if="transactions.length === 0" class="text-center text-gray-500 text-xl mt-20">
                Tidak ada pesanan saat ini
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="trx in transactions"
                    :key="trx.id"
                    class="bg-gray-800 rounded-2xl p-5 border border-gray-700 shadow-lg"
                >
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <span class="text-lg font-bold text-yellow-400">#{{ trx.id }}</span>
                            <span v-if="trx.table" class="ml-2 text-sm bg-blue-600 px-2 py-0.5 rounded-full">
                                {{ trx.table.name }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-400">{{ formatTime(trx.created_at) }}</span>
                    </div>

                    <p v-if="trx.cust_name" class="text-sm text-gray-300 mb-3">
                        {{ trx.cust_name }}
                    </p>

                    <div class="space-y-2">
                        <div
                            v-for="detail in trx.details"
                            :key="detail.id"
                            class="flex justify-between items-center bg-gray-700/50 rounded-lg px-3 py-2"
                        >
                            <span class="font-medium">{{ detail.menu?.name ?? '-' }}</span>
                            <span class="text-yellow-300 font-bold">x{{ detail.amount }}</span>
                        </div>
                        <div v-for="detail in trx.details.filter(d => d.description)" :key="'note-' + detail.id" class="text-xs text-gray-400 italic px-3">
                            📝 {{ detail.menu?.name }}: {{ detail.description }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>