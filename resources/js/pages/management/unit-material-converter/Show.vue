<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Pencil, Trash2, Plus, Check, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';

interface Unit {
    id: number;
    name: string;
}

interface Converter {
    id: number;
    material_id: number;
    from_unit_id: number;
    to_unit_id: number;
    multiplier: string;
    from_unit: Unit;
    to_unit: Unit;
}

interface Material {
    id: number;
    name: string;
    cafe_id: number;
    base_unit_id: number;
    stock: string;
    avg_buy_price: string;
    cafe: { id: number; name: string };
    base_unit: Unit;
}

const props = defineProps<{
    material: Material;
    converters: Converter[];
    units: Unit[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Unit Material Converter', href: '/management/unit-material-converter' },
    { title: props.material.name, href: `/management/unit-material-converter/${props.material.id}` },
];

const unitOptions = computed(() => props.units.map(u => ({ value: u.id, label: u.name })));

// --- Add converter form ---
const addForm = useForm({
    from_unit_id: '' as number | '',
    to_unit_id: '' as number | '',
    multiplier: '' as number | '',
});

const submitAdd = () => {
    addForm.post(`/management/unit-material-converter/${props.material.id}/converter`, {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
};

// --- Inline edit ---
const editingId = ref<number | null>(null);
const editMultiplier = ref<number | ''>('');

function startEdit(converter: Converter) {
    editingId.value = converter.id;
    editMultiplier.value = parseFloat(converter.multiplier);
}

function cancelEdit() {
    editingId.value = null;
    editMultiplier.value = '';
}

function saveEdit(converterId: number) {
    router.put(
        `/management/unit-material-converter/${props.material.id}/converter/${converterId}`,
        { multiplier: editMultiplier.value },
        {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        },
    );
}

function deleteConverter(converterId: number) {
    if (confirm('Apakah Anda yakin ingin menghapus data konversi ini?')) {
        router.delete(
            `/management/unit-material-converter/${props.material.id}/converter/${converterId}`,
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Converter - ${material.name}`" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex items-center justify-between">
                    <Heading variant="small" :title="`Converter: ${material.name}`"
                        description="Lihat dan kelola data konversi satuan material ini." />
                    <Link href="/management/unit-material-converter"
                        class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <!-- Material Detail Card -->
                <div class="rounded-2xl border bg-background shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-4">Detail Material</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-muted-foreground">Nama Material</span>
                            <p class="font-medium">{{ material.name }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Cafe</span>
                            <p class="font-medium">{{ material.cafe.name }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Base Unit</span>
                            <p class="font-medium">{{ material.base_unit.name }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Stok</span>
                            <p class="font-medium">{{ material.stock }} {{ material.base_unit.name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Converter Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Data Konversi Satuan</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Dari Satuan</th>
                                <th class="px-6 py-4 text-left font-medium">Ke Satuan</th>
                                <th class="px-6 py-4 text-left font-medium">Multiplier</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(conv, index) in converters" :key="conv.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium">{{ conv.from_unit.name }}</td>
                                <td class="px-6 py-4 font-medium">{{ conv.to_unit.name }}</td>
                                <td class="px-6 py-4">
                                    <template v-if="editingId === conv.id">
                                        <input v-model="editMultiplier" type="number" min="0.0001" step="0.0001"
                                            class="w-32 px-2 py-1 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                                            @keydown.enter="saveEdit(conv.id)"
                                            @keydown.escape="cancelEdit" />
                                    </template>
                                    <template v-else>
                                        {{ conv.multiplier }}
                                    </template>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        <template v-if="editingId === conv.id">
                                            <button @click="saveEdit(conv.id)" type="button"
                                                class="cursor-pointer inline-flex items-center justify-center w-7 h-7 rounded-md bg-green-100 text-green-600 hover:bg-green-500 hover:text-white transition"
                                                title="Simpan">
                                                <Check :size="14" />
                                            </button>
                                            <button @click="cancelEdit" type="button"
                                                class="cursor-pointer inline-flex items-center justify-center w-7 h-7 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-500 hover:text-white transition"
                                                title="Batal">
                                                <X :size="14" />
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button @click="startEdit(conv)" type="button"
                                                class="cursor-pointer inline-flex items-center justify-center w-7 h-7 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                                title="Edit Multiplier">
                                                <Pencil :size="14" />
                                            </button>
                                            <button @click="deleteConverter(conv.id)" type="button"
                                                class="cursor-pointer inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                                title="Hapus">
                                                <Trash2 :size="14" />
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="converters.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data konversi untuk material ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add Converter Form -->
                <div class="rounded-2xl border bg-background shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Tambah Konversi</h3>

                    <form @submit.prevent="submitAdd" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Dari Satuan</label>
                            <SearchableSelect
                                v-model="addForm.from_unit_id"
                                :options="unitOptions"
                                placeholder="Pilih Satuan"
                                required
                            />
                            <InputError :message="addForm.errors.from_unit_id" />
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Ke Satuan</label>
                            <SearchableSelect
                                v-model="addForm.to_unit_id"
                                :options="unitOptions"
                                placeholder="Pilih Satuan"
                                required
                            />
                            <InputError :message="addForm.errors.to_unit_id" />
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Multiplier</label>
                            <div class="flex gap-2">
                                <input v-model="addForm.multiplier" type="number" min="0.0001" step="0.0001" required
                                    placeholder="Contoh: 1000"
                                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                                <button type="submit" :disabled="addForm.processing"
                                    class="cursor-pointer inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition disabled:opacity-50 whitespace-nowrap">
                                    <Plus :size="14" /> Tambah
                                </button>
                            </div>
                            <InputError :message="addForm.errors.multiplier" />
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
