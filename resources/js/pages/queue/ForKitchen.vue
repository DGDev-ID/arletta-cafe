<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { ChefHat, Coffee, Clock, NotepadText, User, UtensilsCrossed } from 'lucide-vue-next';

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
const previousData = ref<Map<number, Transaction>>(new Map());
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
    osc.frequency.value = 880;
    osc.type = 'sine';
    gain.gain.value = 0.3;
    osc.start();
    osc.stop(audioCtx.currentTime + 0.3);
};

const fetchQueue = async () => {
    try {
        const { data } = await axios.get<Transaction[]>(`/api/queue/kitchen/${props.cafe.unique_id}`);

        // Build current snapshot
        const currentMap = new Map<number, Transaction>(data.map((t: Transaction) => [t.id, t]));

        // Compare: IDs in current that were NOT in previous → new order arrived
        if (previousData.value.size > 0) {
            for (const [id] of currentMap) {
                if (!previousData.value.has(id)) {
                    playNotification();
                    speak('Pesanan baru diterima');
                    break; // one notification is enough per polling cycle
                }
            }
        }

        // Commit new snapshot and render
        previousData.value = currentMap;
        transactions.value = data;
    } catch (_e) {
        // silently retry on next interval — do NOT update previousData so diff stays intact
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
    <Head :title="`Kitchen Queue - ${cafe.name}`" />

    <!-- Tap-to-enable overlay (shown until first user gesture) -->
    <Transition name="fade">
        <div
            v-if="!userHasInteracted"
            class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 bg-[#1c1008]/80 backdrop-blur-sm"
            @click="unlockAudio"
        >
            <div class="flex h-24 w-24 animate-pulse items-center justify-center rounded-3xl bg-gradient-to-br from-[#8B5E3C] to-[#5C3A1E] shadow-2xl shadow-[#8B5E3C]/40">
                <ChefHat class="h-12 w-12 text-[#f5e6d0]" :stroke-width="1.5" />
            </div>
            <div class="text-center">
                <p class="text-3xl font-extrabold text-[#f5e6d0]">Ketuk untuk Mengaktifkan</p>
                <p class="mt-2 text-lg text-[#c19a64]/70">Notifikasi suara &amp; pengumuman akan aktif</p>
            </div>
        </div>
    </Transition>

    <div class="relative min-h-screen overflow-auto bg-[#faf6f0]" style="scrollbar-gutter: stable;">
        <!-- Dot pattern -->
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
                        <ChefHat class="h-8 w-8 text-[#f5e6d0]" :stroke-width="1.75" />
                    </div>
                    <div>
                        <h1 class="text-4xl font-extrabold leading-none tracking-tight text-[#3B2314]">Antrian Dapur</h1>
                    </div>
                </div>
                <!-- Right: cafe name + live clock -->
                <div class="text-right">
                    <p class="text-xl font-semibold text-[#5C3A1E]">{{ cafe.name }}</p>
                    <p class="mt-1 text-base text-[#8B5E3C]/50">Diperbarui setiap 3 detik</p>
                </div>
            </div>

            <!-- Divider -->
            <div class="mb-10 h-px bg-gradient-to-r from-transparent via-[#c19a64]/40 to-transparent" />

            <!-- ─── Empty State ─── -->
            <div v-if="transactions.length === 0" class="flex flex-col items-center justify-center py-32 gap-6">
                <div class="flex h-32 w-32 items-center justify-center rounded-3xl border-2 border-[#d4c4a8]/50 bg-white/60 shadow-xl shadow-[#8B5E3C]/5">
                    <Coffee class="h-16 w-16 text-[#8B5E3C]/25" :stroke-width="1.25" />
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-[#3B2314]/30">Tidak Ada Pesanan</p>
                    <p class="mt-2 text-lg text-[#8B5E3C]/30">Pesanan baru akan muncul otomatis</p>
                </div>
            </div>

            <!-- ─── Transaction Grid ─── -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
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
                    <!-- Inner wrapper: always present to clip content inside rounded corners -->
                    <div :class="['relative z-10', trx.status === 'in_order' ? 'queue-card-inner' : 'bg-white/80']">
                    <!-- Top bar: gold (in_order) or brown (success) -->
                    <div
                        :class="[
                            'h-1',
                            trx.status === 'in_order'
                                ? 'bg-gradient-to-r via-[#c19a64] to-[#c19a64]/30'
                                : 'bg-gradient-to-r from-[#c19a64]/40 via-[#c19a64] to-[#c19a64]/40'
                        ]"
                    />

                    <div class="p-7">
                        <!-- Card header: number + table + time -->
                        <div class="mb-5 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <!-- Order number -->
                                <div
                                    :class="[
                                        'flex h-16 w-16 items-center justify-center rounded-2xl shadow-lg',
                                        trx.status === 'in_order'
                                            ? 'bg-gradient-to-br from-[#8B5E3C] to-[#5C3A1E] shadow-[#8B5E3C]/25'
                                            : 'bg-gradient-to-br from-[#c19a64] to-[#8B5E3C] shadow-[#8B5E3C]/20'
                                    ]"
                                >
                                    <span class="text-3xl font-black text-white">{{ trx.id }}</span>
                                </div>
                                <!-- Table badge -->
                                <span
                                    v-if="trx.table"
                                    class="rounded-xl border border-[#c19a64]/40 bg-[#c19a64]/10 px-4 py-1.5 text-lg font-bold text-[#5C3A1E]"
                                >
                                    {{ trx.table.name }}
                                </span>
                            </div>
                            <!-- Time + status indicator -->
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-2 rounded-xl border border-[#d4c4a8]/50 bg-[#faf6f0] px-3 py-1.5">
                                    <Clock class="h-5 w-5 text-[#8B5E3C]/50" :stroke-width="2" />
                                    <span class="text-base font-semibold text-[#8B5E3C]/60">{{ formatTime(trx.created_at) }}</span>
                                </div>
                                <!-- Status badge -->
                                <span
                                    v-if="trx.status === 'success'"
                                    class="rounded-xl border border-[#c19a64]/50 bg-[#c19a64]/10 px-3 py-1.5 text-base font-bold text-[#5C3A1E]"
                                >
                                    ✓ Selesai
                                </span>
                            </div>
                        </div>

                        <!-- Customer name -->
                        <div v-if="trx.cust_name" class="mb-5 flex items-center gap-3">
                            <User class="h-5 w-5 shrink-0 text-[#8B5E3C]/50" :stroke-width="2" />
                            <p class="text-xl font-semibold text-[#3B2314]/80">{{ trx.cust_name }}</p>
                        </div>

                        <!-- Divider -->
                        <div class="mb-5 h-px bg-gradient-to-r from-transparent via-[#d4c4a8]/80 to-transparent" />

                        <!-- Menu items -->
                        <div class="space-y-3">
                            <div
                                v-for="detail in trx.details"
                                :key="detail.id"
                                class="flex items-center justify-between rounded-2xl border border-[#d4c4a8]/40 bg-[#faf6f0] px-5 py-3.5"
                            >
                                <div class="flex items-center gap-3">
                                    <UtensilsCrossed class="h-5 w-5 shrink-0 text-[#8B5E3C]/40" :stroke-width="2" />
                                    <span class="text-xl font-semibold text-[#3B2314]">{{ detail.menu?.name ?? '-' }}</span>
                                </div>
                                <span class="rounded-xl bg-[#8B5E3C] px-3.5 py-1 text-lg font-black text-white shadow-sm">
                                    ×{{ detail.amount }}
                                </span>
                            </div>

                            <!-- Notes -->
                            <div
                                v-for="detail in trx.details.filter(d => d.description)"
                                :key="'note-' + detail.id"
                                class="flex items-start gap-3 rounded-xl bg-amber-50/60 border border-amber-200/40 px-5 py-3"
                            >
                                <NotepadText class="mt-0.5 h-5 w-5 shrink-0 text-amber-600/60" :stroke-width="2" />
                                <p class="text-base leading-relaxed text-[#5C3A1E]/70">
                                    <span class="font-bold not-italic text-[#5C3A1E]/90">{{ detail.menu?.name }}:</span>
                                    {{ detail.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                    </div><!-- end inner clip wrapper -->
                </div>
            </div>

            <!-- ─── Footer count ─── -->
            <div v-if="transactions.length > 0" class="mt-10 text-center">
                <p class="text-lg text-[#8B5E3C]/40">
                    <span class="font-bold text-[#8B5E3C]/60">{{ transactions.length }}</span> pesanan dalam antrian
                </p>
            </div>

        </div>
    </div>
</template>

<style scoped>
.fade-leave-active { transition: opacity 0.5s ease; }
.fade-leave-to { opacity: 0; }
</style>