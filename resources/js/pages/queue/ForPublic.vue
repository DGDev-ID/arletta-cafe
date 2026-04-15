<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { Coffee, Clock, User, Loader, Armchair } from 'lucide-vue-next';

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

    <!-- Main wrapper: warm cream/beige base -->
    <div class="relative min-h-screen overflow-auto bg-[#faf6f0]" style="scrollbar-gutter: stable;">
        <!-- Dot pattern overlay -->
        <div
            class="pointer-events-none fixed inset-0"
            style="background-image: radial-gradient(circle, rgba(120,80,40,0.09) 1.5px, transparent 1.5px); background-size: 32px 32px;"
        />
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_at_top_center,rgba(193,154,100,0.14)_0%,transparent_60%)]" />

        <div class="relative z-10 mx-auto max-w-[1600px] px-8 py-10">

            <!-- ─── Header ─── -->
            <div class="mb-10 flex items-center justify-between">
                <!-- Left: branding -->
                <div class="flex items-center gap-5">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#8B5E3C] to-[#5C3A1E] shadow-lg shadow-[#8B5E3C]/30">
                        <Coffee class="h-8 w-8 text-[#f5e6d0]" :stroke-width="1.75" />
                    </div>
                    <div>
                        <h1 class="text-4xl font-extrabold leading-none tracking-tight text-[#3B2314]">Antrian Pesanan</h1>
                    </div>
                </div>
                <!-- Right: cafe name -->
                <div class="text-right">
                    <p class="text-xl font-semibold text-[#5C3A1E]">{{ cafe.name }}</p>
                    <p class="mt-1 text-base text-[#8B5E3C]/50">Diperbarui setiap 3 detik</p>
                </div>
            </div>

            <!-- Divider -->
            <div class="mb-10 h-px bg-gradient-to-r from-transparent via-[#c19a64]/40 to-transparent" />

            <!-- ─── Empty State ─── -->
            <div v-if="transactions.length === 0" class="flex flex-col items-center justify-center py-32 gap-6">
                <div class="flex h-32 w-32 items-center justify-center rounded-3xl border-2 border-[#d4c4a8]/50 bg-white/60 shadow-xl">
                    <Coffee class="h-16 w-16 text-[#8B5E3C]/25" :stroke-width="1.25" />
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-[#3B2314]/30">Tidak Ada Pesanan</p>
                    <p class="mt-2 text-lg text-[#8B5E3C]/30">Pesanan baru akan muncul otomatis</p>
                </div>
            </div>

            <!-- ─── Transaction List ─── -->
            <div class="space-y-5">
                <div
                    v-for="trx in transactions"
                    :key="trx.id"
                    :class="[
                        'relative rounded-3xl shadow-xl transition-all duration-500',
                        trx.status === 'in_order'
                            ? 'queue-card-spinning overflow-visible'
                            : 'overflow-hidden border-2 border-green-400/60 shadow-green-200/40'
                    ]"
                >
                    <!-- Inner wrapper: always present to clip content inside rounded corners -->
                    <div :class="['relative z-10', trx.status === 'in_order' ? 'queue-card-inner' : 'bg-white/80']">
                    <!-- Gold/Green accent bar at left -->
                    <div
                        :class="[
                            'absolute inset-y-0 left-0 w-1.5',
                            trx.status === 'in_order'
                                ? 'bg-gradient-to-b via-[#c19a64] to-[#c19a64]/50'
                                : 'bg-gradient-to-b from-green-400/50 via-green-500 to-green-400/50'
                        ]"
                    />

                    <div class="flex items-center justify-between gap-6 py-7 pl-10 pr-8">
                        <!-- Left: order badge + info -->
                        <div class="flex items-center gap-7">
                            <!-- Order number -->
                            <div
                                :class="[
                                    'flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl shadow-lg',
                                    trx.status === 'in_order'
                                        ? 'bg-gradient-to-br from-[#8B5E3C] to-[#5C3A1E] shadow-[#8B5E3C]/25'
                                        : 'bg-gradient-to-br from-green-500 to-green-700 shadow-green-600/25'
                                ]"
                            >
                                <span class="text-4xl font-black leading-none text-white">{{ trx.id }}</span>
                            </div>

                            <div>
                                <!-- Customer name -->
                                <div class="flex items-center gap-3">
                                    <User class="h-6 w-6 shrink-0 text-[#8B5E3C]/40" :stroke-width="2" />
                                    <p class="text-3xl font-bold text-[#3B2314]">{{ trx.cust_name ?? 'Tanpa Nama' }}</p>
                                </div>
                                <!-- Meta: table + time -->
                                <div class="mt-2 flex items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <Armchair class="h-5 w-5 text-[#8B5E3C]/40" :stroke-width="2" />
                                        <span class="text-xl font-semibold text-[#8B5E3C]/70">
                                            {{ trx.table?.name ?? 'Tanpa Meja' }}
                                        </span>
                                    </div>
                                    <span class="text-[#d4c4a8] text-xl">·</span>
                                    <div class="flex items-center gap-2">
                                        <Clock class="h-5 w-5 text-[#8B5E3C]/40" :stroke-width="2" />
                                        <span class="text-xl font-semibold text-[#8B5E3C]/60">{{ formatTime(trx.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: status badge -->
                        <div class="shrink-0">
                            <!-- In order: spinning loader -->
                            <span
                                v-if="trx.status === 'in_order'"
                                class="inline-flex items-center gap-3 rounded-2xl border border-[#c19a64]/40 bg-[#c19a64]/10 px-6 py-3 text-xl font-bold text-[#5C3A1E] shadow-sm"
                            >
                                <Loader class="h-5 w-5 animate-spin text-[#c19a64]" :stroke-width="2.5" />
                                Sedang Dibuat
                            </span>
                            <!-- Success: done badge -->
                            <span
                                v-else
                                class="inline-flex items-center gap-3 rounded-2xl border border-green-400/50 bg-green-50 px-6 py-3 text-xl font-bold text-green-700 shadow-sm"
                            >
                                <Armchair class="h-5 w-5 text-green-500" :stroke-width="2.5" />
                                Siap Diambil
                            </span>
                        </div>
                    </div>
                    </div><!-- end inner clip wrapper -->
                </div>
            </div>

            <!-- ─── Footer count ─── -->
            <div v-if="transactions.length > 0" class="mt-10 text-center">
                <p class="text-lg text-[#8B5E3C]/40">
                    Total <span class="font-bold text-[#8B5E3C]/60">{{ transactions.length }}</span> pesanan dalam antrian
                </p>
            </div>

        </div>
    </div>
</template>