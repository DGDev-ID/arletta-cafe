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

interface Notification {
    uid: number;
    cust_name: string | null;
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
const previousData = ref<Map<number, Transaction>>(new Map());
const notifications = ref<Notification[]>([]);
let interval: ReturnType<typeof setInterval> | null = null;
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
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = 1;
        window.speechSynthesis.speak(utterance);
    }
};

const playNotification = () => {
    if (!audioCtx || audioCtx.state !== 'running') return;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.frequency.value = 660;
    osc.type = 'sine';
    gain.gain.value = 0.3;
    osc.start();
    osc.stop(audioCtx.currentTime + 0.3);
};

const dismissNotification = (uid: number) => {
    notifications.value = notifications.value.filter(n => n.uid !== uid);
};

const scheduleAutoDismiss = (uid: number) => {
    setTimeout(() => dismissNotification(uid), 8000);
};

const fetchQueue = async () => {
    try {
        const { data } = await axios.get<Transaction[]>(`/api/queue/public/${props.cafe.unique_id}`);

        const currentMap = new Map<number, Transaction>(data.map((t: Transaction) => [t.id, t]));

        if (previousData.value.size > 0) {
            for (const [id, trx] of previousData.value) {
                if (!currentMap.has(id)) {
                    playNotification();
                    const custName = trx.cust_name ?? 'Pelanggan';
                    const tableName = trx.table?.name ?? 'tanpa meja';
                    speak(`Pesanan atas nama ${custName}, meja ${tableName}, sudah selesai`);

                    const uid = Date.now() + Math.random();
                    notifications.value.push({ uid, cust_name: trx.cust_name, table: trx.table });
                    scheduleAutoDismiss(uid);
                }
            }
        }

        previousData.value = currentMap;
        transactions.value = data;
    } catch (_e) {
        // silently retry
    }
};

onMounted(() => {
    document.addEventListener('click', unlockAudio);
    document.addEventListener('touchstart', unlockAudio);
    fetchQueue();
    interval = setInterval(fetchQueue, 3000);
});

onUnmounted(() => {
    document.removeEventListener('click', unlockAudio);
    document.removeEventListener('touchstart', unlockAudio);
    if (interval) clearInterval(interval);
});

const formatTime = (dateStr: string) => {
    return new Date(dateStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head :title="`Queue - ${cafe.name}`" />

    <!-- Tap-to-enable overlay -->
    <Transition name="fade">
        <div
            v-if="!userHasInteracted"
            class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 bg-[#1c1008]/80 backdrop-blur-sm"
            @click="unlockAudio"
        >
            <div class="flex h-24 w-24 animate-pulse items-center justify-center rounded-3xl bg-gradient-to-br from-[#8B5E3C] to-[#5C3A1E] shadow-2xl shadow-[#8B5E3C]/40">
                <Coffee class="h-12 w-12 text-[#f5e6d0]" :stroke-width="1.5" />
            </div>
            <div class="text-center">
                <p class="text-3xl font-extrabold text-[#f5e6d0]">Ketuk untuk Mengaktifkan</p>
                <p class="mt-2 text-lg text-[#c19a64]/70">Notifikasi suara &amp; pengumuman akan aktif</p>
            </div>
        </div>
    </Transition>

    <!-- Main wrapper -->
    <div class="relative min-h-screen overflow-auto bg-[#faf6f0]" style="scrollbar-gutter: stable;">
        <div
            class="pointer-events-none fixed inset-0"
            style="background-image: radial-gradient(circle, rgba(120,80,40,0.09) 1.5px, transparent 1.5px); background-size: 32px 32px;"
        />
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_at_top_center,rgba(193,154,100,0.14)_0%,transparent_60%)]" />

        <div class="relative z-10 mx-auto max-w-[1600px] px-8 py-10">

            <!-- Header -->
            <div class="mb-10 flex items-center justify-between">
                <div class="flex items-center gap-5">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#8B5E3C] to-[#5C3A1E] shadow-lg shadow-[#8B5E3C]/30">
                        <Coffee class="h-8 w-8 text-[#f5e6d0]" :stroke-width="1.75" />
                    </div>
                    <div>
                        <h1 class="text-4xl font-extrabold leading-none tracking-tight text-[#3B2314]">Antrian Pesanan</h1>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xl font-semibold text-[#5C3A1E]">{{ cafe.name }}</p>
                    <p class="mt-1 text-base text-[#8B5E3C]/50">Diperbarui setiap 3 detik</p>
                </div>
            </div>

            <div class="mb-10 h-px bg-gradient-to-r from-transparent via-[#c19a64]/40 to-transparent" />

            <!-- Empty State -->
            <div v-if="transactions.length === 0" class="flex flex-col items-center justify-center py-32 gap-6">
                <div class="flex h-32 w-32 items-center justify-center rounded-3xl border-2 border-[#d4c4a8]/50 bg-white/60 shadow-xl">
                    <Coffee class="h-16 w-16 text-[#8B5E3C]/25" :stroke-width="1.25" />
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-[#3B2314]/30">Tidak Ada Pesanan</p>
                    <p class="mt-2 text-lg text-[#8B5E3C]/30">Pesanan baru akan muncul otomatis</p>
                </div>
            </div>

            <!-- Transaction List -->
            <div class="space-y-5">
                <div
                    v-for="trx in transactions"
                    :key="trx.id"
                    :class="[
                        'relative rounded-3xl shadow-xl transition-all duration-500',
                        trx.status === 'in_order'
                            ? 'queue-card-spinning overflow-visible'
                            : 'queue-card-static overflow-hidden bg-white/80'
                    ]"
                >
                    <div :class="['relative z-10', trx.status === 'in_order' ? 'queue-card-inner' : 'bg-white/80']">
                        <div
                            :class="[
                                'absolute inset-y-0 left-0 w-1.5',
                                trx.status === 'in_order'
                                    ? 'bg-gradient-to-b via-[#c19a64] to-[#c19a64]/50'
                                    : 'bg-gradient-to-b from-[#c19a64]/40 via-[#c19a64] to-[#c19a64]/40'
                            ]"
                        />

                        <div class="flex items-center justify-between gap-6 py-7 pl-10 pr-8">
                            <div class="flex items-center gap-7">
                                <div
                                    :class="[
                                        'flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl shadow-lg',
                                        trx.status === 'in_order'
                                            ? 'bg-gradient-to-br from-[#8B5E3C] to-[#5C3A1E] shadow-[#8B5E3C]/25'
                                            : 'bg-gradient-to-br from-[#c19a64] to-[#8B5E3C] shadow-[#8B5E3C]/20'
                                    ]"
                                >
                                    <span class="text-4xl font-black leading-none text-white">{{ trx.id }}</span>
                                </div>

                                <div>
                                    <div class="flex items-center gap-3">
                                        <User class="h-6 w-6 shrink-0 text-[#8B5E3C]/40" :stroke-width="2" />
                                        <p class="text-3xl font-bold text-[#3B2314]">{{ trx.cust_name ?? 'Tanpa Nama' }}</p>
                                    </div>
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

                            <div class="shrink-0">
                                <span
                                    v-if="trx.status === 'in_order'"
                                    class="inline-flex items-center gap-3 rounded-2xl border border-[#c19a64]/40 bg-[#c19a64]/10 px-6 py-3 text-xl font-bold text-[#5C3A1E] shadow-sm"
                                >
                                    <Loader class="h-5 w-5 animate-spin text-[#c19a64]" :stroke-width="2.5" />
                                    Sedang Dibuat
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-3 rounded-2xl border border-[#c19a64]/50 bg-[#c19a64]/10 px-6 py-3 text-xl font-bold text-[#5C3A1E] shadow-sm"
                                >
                                    <Armchair class="h-5 w-5 text-[#c19a64]" :stroke-width="2.5" />
                                    Siap Diambil
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer count -->
            <div v-if="transactions.length > 0" class="mt-10 text-center">
                <p class="text-lg text-[#8B5E3C]/40">
                    Total <span class="font-bold text-[#8B5E3C]/60">{{ transactions.length }}</span> pesanan dalam antrian
                </p>
            </div>

        </div>
    </div>

    <!-- ─── Notification Stack (sticky bottom) ─── -->
    <div
        v-if="notifications.length > 0"
        class="fixed bottom-0 left-0 right-0 z-40 flex flex-col gap-2"
        style="pointer-events: none;"
    >
        <TransitionGroup name="notif" tag="div" class="flex flex-col gap-2">
            <div
                v-for="notif in notifications"
                :key="notif.uid"
                class="notif-card"
                style="pointer-events: auto;"
            >
                <!-- Top content row -->
                <div class="flex items-center justify-between gap-6 px-8 py-5">
                    <!-- Left: icon + text -->
                    <div class="flex items-center gap-5">
                        <!-- Pulse ring + icon -->
                        <div class="relative shrink-0">
                            <div class="notif-pulse-ring absolute inset-0 rounded-2xl" />
                            <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#c19a64] to-[#8B5E3C] shadow-lg shadow-[#8B5E3C]/40">
                                <Coffee class="h-7 w-7 text-white" :stroke-width="1.75" />
                            </div>
                        </div>
                        <!-- Text -->
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-widest text-[#c19a64]">✓ Pesanan Siap Diambil!</p>
                            <p class="mt-0.5 text-2xl font-extrabold text-[#f5e6d0]">
                                {{ notif.cust_name ?? 'Tanpa Nama' }}
                                <span class="text-lg font-medium text-[#c19a64]/80"> · {{ notif.table?.name ?? 'Tanpa Meja' }}</span>
                            </p>
                        </div>
                    </div>
                    <!-- Right: dismiss button -->
                    <button
                        @click="dismissNotification(notif.uid)"
                        class="notif-dismiss-btn flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-[#c19a64] text-lg font-bold transition-all"
                    >
                        ✕
                    </button>
                </div>
                <!-- Progress bar -->
                <div class="h-1 w-full bg-[#3B2314]/50">
                    <div class="notif-progress h-full" />
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
/* ── Page fade overlay ── */
.fade-leave-active { transition: opacity 0.5s ease; }
.fade-leave-to { opacity: 0; }

/* ── Notification slide-up ── */
.notif-enter-active {
    transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
}
.notif-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.notif-enter-from {
    transform: translateY(100%);
    opacity: 0;
}
.notif-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

/* ── Notification card ── */
.notif-card {
    width: 100%;
    background: linear-gradient(135deg, #2c1a0e 0%, #1c1008 100%);
    border-top: 1px solid rgba(193, 154, 100, 0.30);
    border-left: 1px solid rgba(193, 154, 100, 0.15);
    border-right: 1px solid rgba(193, 154, 100, 0.15);
    border-radius: 24px 24px 0 0;
    box-shadow:
        0 -8px 40px rgba(28, 16, 8, 0.75),
        0 -2px 12px rgba(193, 154, 100, 0.12),
        inset 0 1px 0 rgba(193, 154, 100, 0.08);
}

/* ── Pulse ring on icon ── */
.notif-pulse-ring {
    animation: pulse-ring 1.8s ease-out infinite;
    background: rgba(193, 154, 100, 0.35);
}
@keyframes pulse-ring {
    0%   { transform: scale(1);    opacity: 0.7; }
    70%  { transform: scale(1.5);  opacity: 0; }
    100% { transform: scale(1.5);  opacity: 0; }
}

/* ── Dismiss button hover ── */
.notif-dismiss-btn {
    border: 1px solid rgba(193, 154, 100, 0.25);
    background: rgba(193, 154, 100, 0.08);
}
.notif-dismiss-btn:hover {
    background: rgba(193, 154, 100, 0.20);
    border-color: rgba(193, 154, 100, 0.45);
}

/* ── Progress bar shrink ── */
.notif-progress {
    background: linear-gradient(90deg, #c19a64, #8B5E3C);
    animation: notif-shrink 8s linear forwards;
    transform-origin: left;
}
@keyframes notif-shrink {
    from { width: 100%; }
    to   { width: 0%; }
}
</style>