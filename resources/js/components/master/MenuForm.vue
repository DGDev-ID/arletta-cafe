<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { AlertTriangle, Plus, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface CafeOption {
    id: number;
    name: string;
}

interface CategoryOption {
    id: number;
    cafe_id: number;
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

interface MenuMaterialRow {
    material_id: number | '';
    amount: number | '';
    unit_id: number | '';
}

interface SfmOption {
    id: number;
    cafe_id: number;
    name: string;
    unit: { id: number; name: string };
}

interface MenuSfmRow {
    semi_finished_material_id: number | '';
    multiplier: number | '';
}

interface MenuFormData {
    cafe_id: number | '';
    menu_category_id: number | null;
    name: string;
    description: string;
    image: File | null;
    price: number | '';
    has_promo: boolean;
    promo_type: string;
    promo_discount_amount: number | '';
    materials: MenuMaterialRow[];
    semi_finished_materials: MenuSfmRow[];
    errors: Record<string, string>;
    processing: boolean;
}

const props = defineProps<{
    form: MenuFormData;
    cafes: CafeOption[];
    categories: CategoryOption[];
    allMaterials: MaterialOption[];
    units: UnitOption[];
    converters: ConverterOption[];
    semiFinishedMaterials: SfmOption[];
    existingImgUrl?: string | null;
    submitLabel?: string;
    backHref?: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const imagePreview = ref<string | null>(props.existingImgUrl ?? null);

const filteredCategories = computed(() => {
    if (!props.form.cafe_id) return [];
    return props.categories.filter(c => c.cafe_id === props.form.cafe_id);
});

const filteredMaterials = computed(() => {
    if (!props.form.cafe_id) return [];
    return props.allMaterials.filter(m => m.cafe_id === props.form.cafe_id);
});

const filteredSfms = computed(() => {
    if (!props.form.cafe_id) return [];
    return props.semiFinishedMaterials.filter(s => s.cafe_id === props.form.cafe_id);
});

watch(() => props.form.cafe_id, () => {
    props.form.materials = [];
    props.form.semi_finished_materials = [];
    props.form.menu_category_id = null;
});

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    props.form.image = file;
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        imagePreview.value = props.existingImgUrl ?? null;
    }
}

function removeImage() {
    props.form.image = null;
    imagePreview.value = null;
    const input = document.getElementById('menu-img') as HTMLInputElement;
    if (input) input.value = '';
}

function addMaterial() {
    props.form.materials.push({
        material_id: '',
        amount: '',
        unit_id: '',
    });
}

function removeMaterial(index: number) {
    props.form.materials.splice(index, 1);
}

function addSfm() {
    props.form.semi_finished_materials.push({
        semi_finished_material_id: '',
        multiplier: 1,
    });
}

function removeSfm(index: number) {
    props.form.semi_finished_materials.splice(index, 1);
}

function onMaterialChange(index: number) {
    const row = props.form.materials[index];
    const material = props.allMaterials.find(m => m.id === row.material_id);
    if (material) {
        row.unit_id = material.base_unit_id;
    }
}

function getConversionError(row: MenuMaterialRow): string | null {
    if (!row.material_id || !row.unit_id) return null;

    const material = props.allMaterials.find(m => m.id === row.material_id);
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

    return `Tidak bisa konversi dari ${baseUnitName} ke ${targetUnit?.name} dikarenakan material belum memiliki data konversi. Silahkan tambahkan data konversi pada menu Unit Material Converter`;
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-8">

        <!-- Basic Info Section -->
        <div class="space-y-6">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Informasi Menu</h3>

            <!-- Cafe -->
            <div class="grid gap-2">
                <label for="menu-cafe" class="text-sm font-medium leading-none">
                    Cafe <span class="text-red-500">*</span>
                </label>
                <select id="menu-cafe" v-model="form.cafe_id" required
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="" disabled>Pilih Cafe</option>
                    <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">{{ cafe.name }}</option>
                </select>
                <InputError :message="form.errors.cafe_id" />
            </div>

            <!-- Nama Menu -->
            <div class="grid gap-2">
                <label for="menu-name" class="text-sm font-medium leading-none">
                    Nama Menu <span class="text-red-500">*</span>
                </label>
                <input id="menu-name" v-model="form.name" required placeholder="Contoh: Americano"
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                <InputError :message="form.errors.name" />
            </div>

            <!-- Kategori Menu -->
            <div class="grid gap-2">
                <label for="menu-category" class="text-sm font-medium leading-none">Kategori Menu</label>
                <select id="menu-category" v-model="form.menu_category_id"
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                    <option :value="null">Tanpa Kategori</option>
                    <option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <InputError :message="form.errors.menu_category_id" />
            </div>

            <!-- Deskripsi -->
            <div class="grid gap-2">
                <label for="menu-description" class="text-sm font-medium leading-none">Deskripsi</label>
                <textarea id="menu-description" v-model="form.description" rows="3"
                    placeholder="Deskripsi menu (opsional)"
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                <InputError :message="form.errors.description" />
            </div>

            <!-- Image Upload -->
            <div class="grid gap-2">
                <label for="menu-img" class="text-sm font-medium leading-none">Gambar Menu</label>
                <div class="space-y-3">
                    <label for="menu-img"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-dashed bg-background cursor-pointer hover:bg-muted/50 transition w-fit text-sm text-muted-foreground">
                        <Upload :size="16" />
                        <span>Pilih Gambar</span>
                        <input id="menu-img" type="file" accept="image/*" class="hidden" @change="onFileChange" />
                    </label>
                    <div v-if="imagePreview" class="relative inline-block">
                        <img :src="imagePreview" alt="Preview" class="w-40 h-40 object-cover rounded-xl border" />
                        <button type="button" @click="removeImage"
                            class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                            <X :size="14" />
                        </button>
                    </div>
                </div>
                <InputError :message="form.errors.image" />
            </div>

            <!-- Price -->
            <div class="grid gap-2">
                <label for="menu-price" class="text-sm font-medium leading-none">
                    Harga <span class="text-red-500">*</span>
                </label>
                <input id="menu-price" v-model="form.price" type="number" min="0" step="0.01" required placeholder="0"
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                <InputError :message="form.errors.price" />
            </div>
        </div>

        <!-- Promo Section -->
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Promo</h3>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="form.has_promo" class="sr-only peer" />
                    <div
                        class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-ring rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary">
                    </div>
                </label>
            </div>

            <div v-if="form.has_promo" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <label for="promo-type" class="text-sm font-medium leading-none">Tipe Promo</label>
                    <select id="promo-type" v-model="form.promo_type"
                        class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="" disabled>Pilih Tipe</option>
                        <option value="discount_percent">Diskon Persen (%)</option>
                        <option value="discount_amount">Diskon Nominal (Rp)</option>
                    </select>
                    <InputError :message="form.errors.promo_type" />
                </div>
                <div class="grid gap-2">
                    <label for="promo-amount" class="text-sm font-medium leading-none">Nilai Diskon</label>
                    <input id="promo-amount" v-model="form.promo_discount_amount" type="number" min="0" step="0.01"
                        placeholder="0"
                        class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                    <InputError :message="form.errors.promo_discount_amount" />
                </div>
            </div>
        </div>

        <!-- Menu Materials (Resep) Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Resep (Material Menu)
                </h3>
                <button type="button" @click="addMaterial" :disabled="!form.cafe_id"
                    class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium text-muted-foreground hover:bg-muted transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <Plus :size="14" /> Tambah Material
                </button>
            </div>

            <p v-if="!form.cafe_id" class="text-sm text-muted-foreground italic">
                Pilih cafe terlebih dahulu untuk menambahkan material.
            </p>

            <div v-if="form.materials.length > 0" class="space-y-4">
                <div v-for="(row, index) in form.materials" :key="index" class="rounded-xl border p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">Material #{{ index + 1 }}</span>
                        <button type="button" @click="removeMaterial(index)"
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
                                <option v-for="mat in filteredMaterials" :key="mat.id" :value="mat.id">{{ mat.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors[`materials.${index}.material_id`]" />
                        </div>

                        <!-- Amount -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Jumlah</label>
                            <input v-model="row.amount" type="number" min="0.01" step="0.01" required placeholder="0"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            <InputError :message="form.errors[`materials.${index}.amount`]" />
                        </div>

                        <!-- Unit -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Satuan</label>
                            <select v-model="row.unit_id" required
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="" disabled>Pilih Satuan</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                            <InputError :message="form.errors[`materials.${index}.unit_id`]" />
                        </div>
                    </div>

                    <!-- Conversion warning -->
                    <div v-if="getConversionError(row)"
                        class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3">
                        <AlertTriangle :size="16" class="text-amber-600 mt-0.5 shrink-0" />
                        <p class="text-sm text-amber-800">{{ getConversionError(row) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Semi-Finished Material Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Semi-Finished Material
                </h3>
                <button type="button" @click="addSfm" :disabled="!form.cafe_id"
                    class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium text-muted-foreground hover:bg-muted transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <Plus :size="14" /> Tambah SFM
                </button>
            </div>

            <p v-if="!form.cafe_id" class="text-sm text-muted-foreground italic">
                Pilih cafe terlebih dahulu untuk menambahkan semi-finished material.
            </p>

            <div v-if="form.semi_finished_materials.length > 0" class="space-y-4">
                <div v-for="(row, index) in form.semi_finished_materials" :key="index"
                    class="rounded-xl border p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">SFM #{{ index + 1 }}</span>
                        <button type="button" @click="removeSfm(index)"
                            class="cursor-pointer inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition">
                            <Trash2 :size="14" />
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- SFM select -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Semi-Finished Material</label>
                            <select v-model="row.semi_finished_material_id" required
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="" disabled>Pilih SFM</option>
                                <option v-for="sfm in filteredSfms" :key="sfm.id" :value="sfm.id">{{ sfm.name }} ({{
                                    sfm.unit.name }})</option>
                            </select>
                            <InputError
                                :message="form.errors[`semi_finished_materials.${index}.semi_finished_material_id`]" />
                        </div>

                        <!-- Multiplier -->
                        <div class="grid gap-2">
                            <label class="text-sm font-medium leading-none">Multiplier (Porsi)</label>
                            <input v-model="row.multiplier" type="number" min="0.01" step="0.01" required
                                placeholder="1"
                                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                            <p class="text-xs text-muted-foreground">1 = satu porsi resep, 2 = dua porsi, dst.</p>
                            <InputError :message="form.errors[`semi_finished_materials.${index}.multiplier`]" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a :href="backHref ?? '/master/menu'"
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
