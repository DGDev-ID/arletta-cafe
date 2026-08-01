<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Star, MessageSquare, TrendingUp, Filter, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    feedbacks: {
        data: Array<{
            id: number;
            transaction_id: number;
            rating: number;
            comment: string | null;
            created_at: string;
            transaction: {
                id: number;
                unique_code: string;
                cust_name: string | null;
                created_at: string;
                cafe: { id: number; name: string } | null;
                table: { id: number; name: string } | null;
            } | null;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    averageRating: number | null;
    totalFeedbacks: number;
    ratingDistribution: Record<string, number>;
    cafes: Array<{ id: number; name: string }>;
    activeCafeId: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ulasan Pelanggan', href: '/master/customer-feedback' },
];

const selectedCafeId = ref<number | null>(props.activeCafeId ?? null);

function applyCafeFilter(cafeId: number | null) {
    selectedCafeId.value = cafeId;
    const params: Record<string, string> = {};
    if (cafeId !== null) params.cafe_id = String(cafeId);
    router.get('/master/customer-feedback', params, { preserveScroll: true });
}

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const starColors: Record<number, string> = {
    5: 'text-amber-400',
    4: 'text-yellow-400',
    3: 'text-orange-400',
    2: 'text-red-400',
    1: 'text-red-600',
};

const starBg: Record<number, string> = {
    5: 'bg-amber-50 border-amber-200',
    4: 'bg-yellow-50 border-yellow-200',
    3: 'bg-orange-50 border-orange-200',
    2: 'bg-red-50 border-red-200',
    1: 'bg-red-50 border-red-300',
};

const ratingLabels: Record<number, string> = {
    5: 'Luar Biasa',
    4: 'Bagus',
    3: 'Cukup',
    2: 'Kurang',
    1: 'Sangat Buruk',
};

const maxDistribution = computed(() => {
    const values = Object.values(props.ratingDistribution);
    return values.length > 0 ? Math.max(...values) : 1;
});

function goToPage(url: string | null) {
    if (!url) return;
    const urlObj = new URL(url, window.location.href);
    const params: Record<string, string> = {};
    urlObj.searchParams.forEach((v, k) => { params[k] = v; });
    router.get('/master/customer-feedback', params, { preserveScroll: true });
}
</script>

<template>
    <Head title="Ulasan Pelanggan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <Heading title="Ulasan Pelanggan" description="Kelola dan pantau feedback yang diberikan pelanggan." />

            <!-- Filter Cabang -->
            <div v-if="cafes.length > 1" class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Filter class="h-4 w-4 shrink-0" />
                    <span class="font-medium">Filter Cabang:</span>
                </div>
                <button
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="selectedCafeId === null ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                    @click="applyCafeFilter(null)"
                >Semua Cabang</button>
                <button
                    v-for="cafe in cafes"
                    :key="cafe.id"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                    :class="selectedCafeId === cafe.id ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                    @click="applyCafeFilter(cafe.id)"
                >{{ cafe.name }}</button>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Average Rating Card -->
                <div class="md:col-span-1 rounded-2xl border bg-gradient-to-br from-amber-50 to-orange-50 border-amber-200 p-5 flex flex-col items-center justify-center text-center">
                    <div class="flex items-center justify-center gap-1 mb-2">
                        <Star class="w-8 h-8 fill-amber-400 text-amber-400" />
                    </div>
                    <p class="text-5xl font-extrabold text-amber-600 tabular-nums leading-none mb-1">
                        {{ averageRating ?? '-' }}
                    </p>
                    <p class="text-sm text-amber-700 font-medium">Rata-rata Rating</p>
                    <p class="text-xs text-amber-600/70 mt-1">dari {{ totalFeedbacks.toLocaleString('id-ID') }} ulasan</p>
                    <!-- Stars display -->
                    <div class="flex items-center justify-center gap-0.5 mt-3">
                        <Star
                            v-for="s in 5"
                            :key="s"
                            class="w-5 h-5"
                            :class="averageRating && s <= Math.round(averageRating) ? 'fill-amber-400 text-amber-400' : 'fill-gray-200 text-gray-200'"
                        />
                    </div>
                </div>

                <!-- Rating Distribution -->
                <div class="md:col-span-2 rounded-2xl border bg-card p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <TrendingUp class="w-4 h-4 text-muted-foreground" />
                        <h3 class="text-sm font-semibold text-foreground">Distribusi Rating</h3>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="star in [5, 4, 3, 2, 1]"
                            :key="star"
                            class="flex items-center gap-3"
                        >
                            <div class="flex items-center gap-1 w-16 shrink-0">
                                <span class="text-xs font-medium text-foreground">{{ star }}</span>
                                <Star class="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                            </div>
                            <div class="flex-1 h-2.5 bg-muted rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="star >= 4 ? 'bg-amber-400' : star === 3 ? 'bg-orange-400' : 'bg-red-400'"
                                    :style="`width: ${maxDistribution > 0 ? ((ratingDistribution[star] ?? 0) / maxDistribution) * 100 : 0}%`"
                                />
                            </div>
                            <span class="text-xs text-muted-foreground w-8 text-right tabular-nums shrink-0">
                                {{ ratingDistribution[star] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feedback List -->
            <div class="rounded-2xl border bg-card overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-4 border-b">
                    <MessageSquare class="w-4 h-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold text-foreground">Daftar Ulasan</h3>
                    <span class="ml-auto text-xs text-muted-foreground">{{ feedbacks.total }} ulasan</span>
                </div>

                <!-- Empty State -->
                <div v-if="feedbacks.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                    <MessageSquare class="w-12 h-12 text-muted-foreground/30 mb-3" />
                    <p class="text-sm font-medium text-muted-foreground">Belum ada ulasan dari pelanggan</p>
                    <p class="text-xs text-muted-foreground/70 mt-1">Ulasan akan muncul setelah pelanggan menyelesaikan pesanan</p>
                </div>

                <!-- Feedback Rows -->
                <div v-else class="divide-y">
                    <div
                        v-for="feedback in feedbacks.data"
                        :key="feedback.id"
                        class="px-5 py-4 hover:bg-muted/30 transition-colors"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                            <!-- Rating Badge -->
                            <div
                                class="flex items-center gap-1.5 shrink-0 rounded-xl border px-3 py-1.5 w-fit"
                                :class="starBg[feedback.rating]"
                            >
                                <Star class="w-4 h-4 fill-current" :class="starColors[feedback.rating]" />
                                <span class="text-sm font-bold" :class="starColors[feedback.rating]">{{ feedback.rating }}</span>
                                <span class="text-xs hidden sm:inline" :class="starColors[feedback.rating]">— {{ ratingLabels[feedback.rating] }}</span>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-foreground">
                                        {{ feedback.transaction?.cust_name || 'Pelanggan Anonim' }}
                                    </span>
                                    <span v-if="feedback.transaction?.cafe" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-medium">
                                        {{ feedback.transaction.cafe.name }}
                                    </span>
                                    <span v-if="feedback.transaction?.table" class="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-full">
                                        Meja {{ feedback.transaction.table.name }}
                                    </span>
                                </div>

                                <p v-if="feedback.comment" class="text-sm text-foreground/80 leading-relaxed mb-1">
                                    "{{ feedback.comment }}"
                                </p>
                                <p v-else class="text-xs text-muted-foreground italic mb-1">Tidak ada komentar</p>

                                <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                    <span>{{ formatDate(feedback.created_at) }}</span>
                                    <span v-if="feedback.transaction" class="font-mono">
                                        #{{ feedback.transaction.unique_code }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="feedbacks.last_page > 1" class="flex items-center justify-between px-5 py-4 border-t bg-muted/20">
                    <p class="text-xs text-muted-foreground">
                        Halaman {{ feedbacks.current_page }} dari {{ feedbacks.last_page }}
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            @click="goToPage(feedbacks.prev_page_url)"
                            :disabled="!feedbacks.prev_page_url"
                            class="p-2 rounded-lg hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            v-for="link in feedbacks.links.slice(1, -1)"
                            :key="link.label"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="w-8 h-8 rounded-lg text-xs font-medium transition-colors"
                            :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-muted-foreground disabled:opacity-40'"
                            v-html="link.label"
                        />
                        <button
                            @click="goToPage(feedbacks.next_page_url)"
                            :disabled="!feedbacks.next_page_url"
                            class="p-2 rounded-lg hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
