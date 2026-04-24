<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { AlertTriangle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
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
    inboundOutbound: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inbound / Outbound Material', href: '/management/inbound-outbound-material' },
    { title: 'Edit', href: '#' },
];

const materials = ref<MaterialOption[]>([]);
const unitValid = ref<boolean | null>(true);
const checkingUnit = ref(false);

const form = useForm({
    amount: props.inboundOutbound.amount,
    base_unit_id: props.inboundOutbound.base_unit_id,
    inbound_buy_price: props.inboundOutbound.inbound_buy_price,
    description: props.inboundOutbound.description ? props.inboundOutbound.description.replace('spoil - ', '') : '',
});

const unitOptions = computed(() => props.units.map(u => ({ value: u.id, label: u.name })));

// We need to fetch materials to know the base_unit_id of the current material for validation
onMounted(async () => {
    if (props.inboundOutbound.material?.cafe_id) {
        const { data } = await axios.get('/management/inbound-outbound-material/materials-by-cafe', {
            params: { cafe_id: props.inboundOutbound.material.cafe_id },
        });
        materials.value = data;
    }
});

// Validate unit against converter when unit changes
watch(() => form.base_unit_id, async (unitId) => {
    unitValid.value = null;
    if (!unitId) return;

    // If unit is same as material base unit, always valid
    const selectedMaterial = materials.value.find((m) => m.id === props.inboundOutbound.material_id);
    if (selectedMaterial && selectedMaterial.base_unit_id === unitId) {
        unitValid.value = true;
        return;
    }

    checkingUnit.value = true;
    try {
        const { data } = await axios.get('/management/inbound-outbound-material/check-unit-converter', {
            params: { material_id: props.inboundOutbound.material_id, unit_id: unitId },
        });
        unitValid.value = data.exists;
    } finally {
        checkingUnit.value = false;
    }
});

const submit = () => {
    form.put(`/management/inbound-outbound-material/${props.inboundOutbound.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Data" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Data"
                        :description="`Edit data ${inboundOutbound.type} material.`" />
                    <Link href="/management/inbound-outbound-material"
                        class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <form @submit.prevent="submit" class="grid gap-6">

                        <!-- Material (Disabled) -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Material</label>
                            <input type="text" disabled :value="`${inboundOutbound.material.name} (${inboundOutbound.material.cafe.name})`"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-muted focus:outline-none cursor-not-allowed" />
                            <p class="text-xs text-muted-foreground">Material tidak dapat diubah setelah data dibuat.</p>
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
                                placeholder="Pilih Satuan"
                                required
                            />
                            <InputError :message="form.errors.base_unit_id" />

                            <!-- Unit converter alert -->
                            <div v-if="unitValid === false"
                                class="flex items-start gap-2 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
                                <AlertTriangle :size="18" class="shrink-0 mt-0.5" />
                                <p>Unit ini belum ditambahkan ke <strong>Unit Material Converter</strong> untuk material yang dipilih. Silakan tambahkan konversi unit terlebih dahulu sebelum melanjutkan.</p>
                            </div>

                            <div v-if="checkingUnit" class="text-xs text-muted-foreground">Memeriksa konversi unit...</div>
                        </div>

                        <!-- Inbound Buy Price -->
                        <div v-if="inboundOutbound.type === 'inbound'" class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Harga Beli (Inbound Buy Price)</label>
                            <input v-model="form.inbound_buy_price" type="number" min="0" step="1" required
                                placeholder="Contoh: 50000"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            <InputError :message="form.errors.inbound_buy_price" />
                        </div>

                        <!-- Description (Outbound Spoil) -->
                        <div v-if="inboundOutbound.type === 'outbound'" class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Keterangan Spoil</label>
                            <input v-model="form.description" type="text" required
                                placeholder="Contoh: Kadaluarsa"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            <InputError :message="form.errors.description" />
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end">
                            <button type="submit"
                                :disabled="form.processing || unitValid === false || unitValid === null && !!form.base_unit_id"
                                class="cursor-pointer inline-flex items-center px-6 py-2.5 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
