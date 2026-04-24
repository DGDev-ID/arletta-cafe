<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { AlertTriangle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import SearchableSelect from '@/components/SearchableSelect.vue';

interface Cafe {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
}

interface MaterialOption {
    id: number;
    name: string;
    base_unit_id: number;
    base_unit: Unit;
}

const props = defineProps<{
    cafes: Cafe[];
    units: Unit[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inbound / Outbound Material', href: '/management/inbound-outbound-material' },
    { title: 'Tambah Outbound', href: '/management/inbound-outbound-material/create-outbound' },
];

const selectedCafe = ref<number | ''>('');
const materials = ref<MaterialOption[]>([]);
const unitValid = ref<boolean | null>(null);
const checkingUnit = ref(false);

const form = useForm({
    material_id: '' as number | '',
    amount: '' as number | '',
    base_unit_id: '' as number | '',
    description: '' as string | '',
});

const cafeOptions = computed(() => props.cafes.map(c => ({ value: c.id, label: c.name })));
const materialOptions = computed(() => materials.value.map(m => ({ value: m.id, label: `${m.name} (Base: ${m.base_unit.name})` })));
const unitOptions = computed(() => props.units.map(u => ({ value: u.id, label: u.name })));

// Load materials when cafe changes
watch(selectedCafe, async (cafeId) => {
    form.material_id = '';
    form.base_unit_id = '';
    materials.value = [];
    unitValid.value = null;

    if (cafeId) {
        const { data } = await axios.get('/management/inbound-outbound-material/materials-by-cafe', {
            params: { cafe_id: cafeId },
        });
        materials.value = data;
    }
});

// Reset unit when material changes
watch(() => form.material_id, () => {
    form.base_unit_id = '';
    unitValid.value = null;
});

// Validate unit against converter when unit changes
watch(() => form.base_unit_id, async (unitId) => {
    unitValid.value = null;
    if (!form.material_id || !unitId) return;

    // If unit is same as material base unit, always valid
    const selectedMaterial = materials.value.find((m) => m.id === form.material_id);
    if (selectedMaterial && selectedMaterial.base_unit_id === unitId) {
        unitValid.value = true;
        return;
    }

    checkingUnit.value = true;
    try {
        const { data } = await axios.get('/management/inbound-outbound-material/check-unit-converter', {
            params: { material_id: form.material_id, unit_id: unitId },
        });
        unitValid.value = data.exists;
    } finally {
        checkingUnit.value = false;
    }
});

const submit = () => {
    form.post('/management/inbound-outbound-material/store-outbound', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Tambah Outbound" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Outbound"
                        description="Tambahkan data outbound material baru." />
                    <Link href="/management/inbound-outbound-material"
                        class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <form @submit.prevent="submit" class="grid gap-6">

                        <!-- Cafe -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Cafe</label>
                            <SearchableSelect
                                v-model="selectedCafe"
                                :options="cafeOptions"
                                placeholder="Pilih Cafe"
                                required
                            />
                        </div>

                        <!-- Material -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Material</label>
                            <SearchableSelect
                                v-model="form.material_id"
                                :options="materialOptions"
                                :disabled="!selectedCafe"
                                :placeholder="selectedCafe ? 'Pilih Material' : 'Pilih cafe terlebih dahulu'"
                                required
                            />
                            <InputError :message="form.errors.material_id" />
                        </div>

                        <!-- Amount -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Jumlah (Amount)</label>
                            <input v-model="form.amount" type="number" min="0.01" step="0.01" required
                                placeholder="Contoh: 10"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <!-- Unit -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Satuan (Unit)</label>
                            <SearchableSelect
                                v-model="form.base_unit_id"
                                :options="unitOptions"
                                :disabled="!form.material_id"
                                :placeholder="form.material_id ? 'Pilih Satuan' : 'Pilih material terlebih dahulu'"
                                required
                            />
                            <InputError :message="form.errors.base_unit_id" />

                            <!-- Unit converter alert -->
                            <div v-if="unitValid === false"
                                class="flex items-start gap-2 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
                                <AlertTriangle :size="18" class="shrink-0 mt-0.5" />
                                <p>Unit ini belum ditambahkan ke <strong>Unit Material Converter</strong> untuk material
                                    yang dipilih. Silakan tambahkan konversi unit terlebih dahulu sebelum melanjutkan.
                                </p>
                            </div>

                            <div v-if="checkingUnit" class="text-xs text-muted-foreground">Memeriksa konversi unit...
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Deskripsi (Description)</label>
                            <input v-model="form.description" type="text" placeholder="Contoh: Kadaluwarsa"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            <InputError :message="form.errors.description" />
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end">
                            <button type="submit"
                                :disabled="form.processing || unitValid === false || unitValid === null && !!form.base_unit_id"
                                class="cursor-pointer inline-flex items-center px-6 py-2.5 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Outbound' }}
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
