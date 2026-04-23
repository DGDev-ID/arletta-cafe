<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { AlertTriangle, Plus, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface CafeOption {
    id: number;
    name: string;
}

interface MaterialOption {
    id: number;
    cafe_id: number;
    name: string;
    base_unit_id: number;
    base_unit: { id: number; name: string };
}

interface UnitOption {
    id: number;
    name: string;
}

interface ConverterOption {
    material_id: number;
    from_unit_id: number;
    to_unit_id: number;
}

interface DetailRow {
    material_id: number | '';
    amount: number | '';
    unit_id: number | '';
}

interface SfmFormData {
    cafe_id: number | '';
    name: string;
    details: DetailRow[];
    errors: Record<string, string>;
    processing: boolean;
}

const props = defineProps<{
    form: SfmFormData;
    cafes: CafeOption[];
    materials: MaterialOption[];
    units: UnitOption[];
    converters: ConverterOption[];
    submitLabel?: string;
    backHref?: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const filteredMaterials = computed(() => {
    if (!props.form.cafe_id) return [];
    return props.materials.filter(m => m.cafe_id === props.form.cafe_id);
});

watch(() => props.form.cafe_id, () => {
    props.form.details = [];
});

function addDetail() {
    props.form.details.push({
        material_id: '',
        amount: '',
        unit_id: '',
    });
}

function removeDetail(index: number) {
    props.form.details.splice(index, 1);
}

function onMaterialChange(index: number) {
    const row = props.form.details[index];
    const material = props.materials.find(m => m.id === row.material_id);
    if (material) {
        row.unit_id = material.base_unit_id;
    }
}

function getConversionError(row: DetailRow): string | null {
    if (!row.material_id || !row.unit_id) return null;

    const material = props.materials.find(m => m.id === row.material_id);
    if (!material) return null;

    if (row.unit_id === material.base_unit_id) return null;

    const forward = props.converters.find(c =>
        c.material_id === row.material_id &&
        c.from_unit_id === material.base_unit_id &&
        c.to_unit_id === row.unit_id
    );
    if (forward) return null;

    const reverse = props.converters.find(c =>
        c.material_id === row.material_id &&
        c.from_unit_id === row.unit_id &&
        c.to_unit_id === material.base_unit_id
    );
    if (reverse) return null;

    const baseUnitName = material.base_unit.name;
    const targetUnit = props.units.find(u => u.id === row.unit_id);

    return `Tidak bisa konversi dari ${baseUnitName} ke ${targetUnit?.name}. Silahkan tambahkan data konversi pada menu Unit Material Converter`;
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-8">

        <!-- Basic Info Section -->
        <div class="space-y-6">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Informasi Semi-Finished Material</h3>

            <!-- Cafe -->
            <div class="grid gap-2">
                <label for="sfm-cafe" class="text-sm font-medium leading-none">
                    Cafe <span class="text-red-500">*</span>
                </label>
                <select id="sfm-cafe" v-model="form.cafe_id" required
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="" disabled>Pilih Cafe</option>
                    <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">{{ cafe.name }}</option>
                </select>
                <InputError :message="form.errors.cafe_id" />
            </div>

            <!-- Nama -->
            <div class="grid gap-2">
                <label for="sfm-name" class="text-sm font-medium leading-none">
                    Nama <span class="text-red-500">*</span>
                </label>
                <input id="sfm-name" v-model="form.name" required placeholder="Contoh: Teh, Saus Tomat"
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                <InputError :message="form.errors.name" />
            </div>
        </div>

        <!-- Detail / Resep Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Komposisi (Material)</h3>
                <button type="button" @click="addDetail" :disabled="!form.cafe_id"
                    class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium text-muted-foreground hover:bg-muted transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <Plus :size="14" /> Tambah Material
                </button>
            </div>

            <p v-if="!form.cafe_id" class="text-sm text-muted-foreground italic">
                Pilih cafe terlebih dahulu untuk menambahkan material.
            </p>

            <p v-else-if="form.details.length === 0" class="text-sm text-muted-foreground italic">
                Belum ada material. Klik "Tambah Material" untuk menambahkan komposisi.
            </p>

            <div v-if="form.details.length > 0" class="space-y-4">
                <div v-for="(row, index) in form.details" :key="index" class="rounded-xl border p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">Material #{{ index + 1 }}</span>
                        <button type="button" @click="removeDetail(index)"
                            class="cursor-pointer inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition">
                            <Trash2 :size="14" />
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Material select -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Material</label>
                            <select v-model="row.material_id" @change="onMaterialChange(index)" required
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="" disabled>Pilih Material</option>
                                <option v-for="mat in filteredMaterials" :key="mat.id" :value="mat.id">{{ mat.name }}</option>
                            </select>
                            <InputError :message="form.errors[`details.${index}.material_id`]" />
                        </div>

                        <!-- Amount -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Jumlah</label>
                            <input v-model="row.amount" type="number" min="0.01" step="0.01" required placeholder="0"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            <InputError :message="form.errors[`details.${index}.amount`]" />
                        </div>

                        <!-- Unit -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Satuan</label>
                            <select v-model="row.unit_id" required
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="" disabled>Pilih Satuan</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                            <InputError :message="form.errors[`details.${index}.unit_id`]" />
                        </div>
                    </div>

                    <!-- Conversion warning -->
                    <div v-if="getConversionError(row)" class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3">
                        <AlertTriangle :size="16" class="text-amber-600 mt-0.5 shrink-0" />
                        <p class="text-sm text-amber-800">{{ getConversionError(row) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a :href="backHref ?? '/master/semi-finished-material'"
                class="inline-flex items-center px-5 py-2.5 rounded-xl border text-sm font-medium text-muted-foreground hover:bg-muted transition">
                Batal
            </a>
            <button type="submit" :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50">
                {{ form.processing ? 'Menyimpan...' : (submitLabel ?? 'Simpan') }}
            </button>
        </div>

    </form>
</template>
