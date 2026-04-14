<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Copy, Check, Link2, ExternalLink } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Cafe {
    id: number;
    unique_id: string;
    name: string;
    address: string | null;
}

const props = defineProps<{
    cafes: Cafe[];
    baseUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Public Link Generator', href: '/shortcut/public-link-generator' },
];

const selectedCafeId = ref<number | ''>('');
const selectedType = ref<'for-public' | 'for-kitchen' | ''>('');
const generatedLink = ref('');
const copied = ref(false);

const selectedCafe = computed(() =>
    props.cafes.find((c) => c.id === Number(selectedCafeId.value)) ?? null,
);

const canGenerate = computed(() => !!selectedCafeId.value && !!selectedType.value);

const generate = () => {
    if (!selectedCafe.value || !selectedType.value) return;
    generatedLink.value = `${props.baseUrl}/${selectedType.value}/${selectedCafe.value.unique_id}`;
    copied.value = false;
};

const copyLink = async () => {
    if (!generatedLink.value) return;
    await navigator.clipboard.writeText(generatedLink.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const openLink = () => {
    if (!generatedLink.value) return;

    window.open(generatedLink.value, '_blank');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Public Link Generator" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <Heading variant="small" title="Public Link Generator"
                    description="Generate link publik untuk cafe berdasarkan tipe akses." />

                <div class="rounded-2xl border bg-background shadow-sm p-8 space-y-6">

                    <!-- Cafe Select -->
                    <div class="grid gap-2">
                        <label class="text-sm font-medium leading-none">Pilih Cafe</label>
                        <select v-model="selectedCafeId"
                            class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                            <option value="" disabled>Pilih Cafe</option>
                            <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">
                                {{ cafe.name }}{{ cafe.address ? ` - ${cafe.address}` : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Type Select -->
                    <div class="grid gap-2">
                        <label class="text-sm font-medium leading-none">Tipe Link</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="selectedType = 'for-public'" :class="selectedType === 'for-public'
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border hover:bg-muted/60'"
                                class="cursor-pointer flex flex-col items-start gap-1 rounded-xl border px-4 py-3 text-left transition">
                                <span class="text-sm font-medium">For Public</span>
                                <span class="text-xs text-muted-foreground">Akses menu untuk pelanggan</span>
                            </button>
                            <button type="button" @click="selectedType = 'for-kitchen'" :class="selectedType === 'for-kitchen'
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border hover:bg-muted/60'"
                                class="cursor-pointer flex flex-col items-start gap-1 rounded-xl border px-4 py-3 text-left transition">
                                <span class="text-sm font-medium">For Kitchen</span>
                                <span class="text-xs text-muted-foreground">Akses tampilan dapur</span>
                            </button>
                        </div>
                    </div>

                    <!-- Generate Button -->
                    <button type="button" @click="generate" :disabled="!canGenerate"
                        class="cursor-pointer w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <Link2 :size="16" /> Generate Link
                    </button>

                    <!-- Result -->
                    <Transition enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0">
                        <div v-if="generatedLink" class="space-y-2">
                            <label class="text-sm font-medium leading-none">Public Link Generated</label>
                            <div class="flex items-center gap-2 rounded-xl border bg-muted/30 px-4 py-3">
                                <span class="flex-1 text-sm text-foreground break-all select-all">{{ generatedLink
                                }}</span>

                                <div class="flex flex-row gap-4">
                                    <button type="button" @click="openLink"
                                        :class="copied ? 'text-green-600' : 'text-muted-foreground hover:text-foreground'"
                                        class="cursor-pointer shrink-0 transition" title="Buka link di tab baru">
                                        <ExternalLink :size="18" />
                                    </button>
                                    <button type="button" @click="copyLink"
                                        :class="copied ? 'text-green-600' : 'text-muted-foreground hover:text-foreground'"
                                        class="cursor-pointer shrink-0 transition"
                                        :title="copied ? 'Tersalin!' : 'Salin link'">
                                        <Check v-if="copied" :size="18" />
                                        <Copy v-else :size="18" />
                                    </button>
                                </div>
                            </div>
                            <p v-if="copied" class="text-xs text-green-600">Link berhasil disalin!</p>
                        </div>
                    </Transition>

                </div>

            </div>
        </div>
    </AppLayout>
</template>
