<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { ref, computed } from 'vue';
import { Pencil, Trash2, Plus, ToggleLeft, ToggleRight, X, Check, UtensilsCrossed } from 'lucide-vue-next';

interface ThirdPartyChannel {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    channels: ThirdPartyChannel[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Saluran Pihak Ketiga', href: '/master/third-party-channel' },
];

// ── Form State ─────────────────────────────────────────────────────────────
const showForm = ref(false);
const editingId = ref<number | null>(null);
const form = ref({ name: '' });
const errors = ref<Record<string, string>>({});

const formTitle = computed(() => editingId.value ? 'Edit Saluran' : 'Tambah Saluran');

const openAdd = () => {
    editingId.value = null;
    form.value = { name: '' };
    errors.value = {};
    showForm.value = true;
};

const openEdit = (channel: ThirdPartyChannel) => {
    editingId.value = channel.id;
    form.value = { name: channel.name };
    errors.value = {};
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    errors.value = {};
};

const validateForm = () => {
    errors.value = {};
    if (!form.value.name.trim()) errors.value.name = 'Nama saluran wajib diisi.';
    return Object.keys(errors.value).length === 0;
};

const submitForm = () => {
    if (!validateForm()) return;

    const payload = { name: form.value.name.trim() };

    if (editingId.value) {
        router.put(`/master/third-party-channel/${editingId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => closeForm(),
            onError: (e) => { errors.value = e as Record<string, string>; },
        });
    } else {
        router.post('/master/third-party-channel', payload, {
            preserveScroll: true,
            onSuccess: () => closeForm(),
            onError: (e) => { errors.value = e as Record<string, string>; },
        });
    }
};

const deleteChannel = (id: number, name: string) => {
    if (confirm(`Hapus saluran "${name}"? Tindakan ini tidak bisa dibatalkan.`)) {
        router.delete(`/master/third-party-channel/${id}`, { preserveScroll: true });
    }
};

const toggleStatus = (id: number) => {
    router.patch(`/master/third-party-channel/${id}/toggle-status`, {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Saluran Pihak Ketiga" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-4xl mx-auto px-6 space-y-6">

                <!-- Header -->
                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Saluran Pihak Ketiga"
                        description="Kelola daftar platform pihak ketiga (GoFood, GrabFood, dll.) dan kustomisasi harganya per menu." />
                    <button @click="openAdd" type="button"
                        class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition shadow-sm">
                        <Plus :size="16" /> Tambah Saluran
                    </button>
                </div>

                <!-- Form Modal Overlay -->
                <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
                    <div class="bg-background rounded-2xl shadow-2xl border w-full max-w-md mx-4 p-6 space-y-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold">{{ formTitle }}</h2>
                            <button @click="closeForm" type="button"
                                class="cursor-pointer text-muted-foreground hover:text-foreground transition">
                                <X :size="20" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <!-- Nama -->
                            <div class="grid gap-1.5">
                                <label class="text-sm font-medium">Nama Saluran</label>
                                <input v-model="form.name" type="text" placeholder="Contoh: GoFood"
                                    class="px-3 py-2 text-sm rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                                    :class="errors.name ? 'border-red-400 focus:ring-red-300' : ''" />
                                <span v-if="errors.name" class="text-xs text-red-500">{{ errors.name }}</span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button @click="closeForm" type="button"
                                class="cursor-pointer px-4 py-2 rounded-xl border text-sm font-medium hover:bg-muted transition">
                                Batal
                            </button>
                            <button @click="submitForm" type="button"
                                class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition">
                                <Check :size="16" /> Simpan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Nama Saluran</th>
                                <th class="px-6 py-4 text-center font-medium">Status</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(channel, index) in channels" :key="channel.id"
                                class="border-t hover:bg-muted/30 transition">
                                <td class="px-6 py-4 text-muted-foreground">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium">{{ channel.name }}</td>
                                <td class="px-6 py-4 text-center">
                                    <button @click="toggleStatus(channel.id)" type="button"
                                        class="cursor-pointer inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition"
                                        :class="channel.is_active
                                            ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                                        <ToggleRight v-if="channel.is_active" :size="14" />
                                        <ToggleLeft v-else :size="14" />
                                        {{ channel.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end items-center gap-2">
                                        <Link :href="`/master/third-party-channel/${channel.id}/menus`"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-100 text-orange-600 text-xs font-medium hover:bg-orange-500 hover:text-white transition">
                                            <UtensilsCrossed :size="13" /> Kelola Menu
                                        </Link>
                                        <button @click="openEdit(channel)" type="button"
                                            class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-blue-600 text-xs font-medium hover:bg-blue-500 hover:text-white transition">
                                            <Pencil :size="13" /> Edit
                                        </button>
                                        <button @click="deleteChannel(channel.id, channel.name)" type="button"
                                            class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-100 text-red-600 text-xs font-medium hover:bg-red-500 hover:text-white transition">
                                            <Trash2 :size="13" /> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="channels.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-muted-foreground">
                                    Belum ada saluran pihak ketiga. Klik "Tambah Saluran" untuk menambahkan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
