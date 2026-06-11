<!-- resources/js/pages/master/material/MaterialForm.vue -->
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { computed, watch } from 'vue';

interface CafeOption { id: number; name: string; }
interface UnitOption { id: number; name: string; }

interface VariantItem {
    id?: number | null;
    name: string;
    minimum_stock: number | '';
}

interface MaterialFormData {
    cafe_id: number | '';
    name: string;
    type: 'normal' | 'selectable';
    base_unit_id: number | '';
    critical_stock: number | '';
    variants: VariantItem[];
    errors: Record<string, string>;
    processing: boolean;
}

const props = defineProps<{
    form: MaterialFormData;
    cafes: CafeOption[];
    units: UnitOption[];
    submitLabel?: string;
    backHref?: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const cafeOptions = computed(() => props.cafes.map(c => ({ value: c.id, label: c.name })));
const unitOptions = computed(() => props.units.map(u => ({ value: u.id, label: u.name })));

// Saat type berubah ke normal, kosongkan variants
watch(() => props.form.type, (val) => {
    if (val === 'normal') {
        props.form.variants = [];
    }
});

function addVariant() {
    props.form.variants.push({ id: null, name: '', minimum_stock: 0 });
}

function removeVariant(index: number) {
    props.form.variants.splice(index, 1);
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">

        <!-- Cafe -->
        <div class="grid gap-2">
            <label class="text-sm font-medium leading-none">
                Cafe <span class="text-red-500">*</span>
            </label>
            <SearchableSelect v-model="form.cafe_id" :options="cafeOptions" placeholder="Pilih Cafe" required />
            <InputError :message="form.errors.cafe_id" />
        </div>

        <!-- Nama Material -->
        <div class="grid gap-2">
            <label class="text-sm font-medium leading-none">
                Nama Material <span class="text-red-500">*</span>
            </label>
            <input
                v-model="form.name"
                required
                placeholder="Contoh: Biji Kopi Arabika"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.name" />
        </div>

        <!-- Type -->
        <div class="grid gap-2">
            <label class="text-sm font-medium leading-none">
                Tipe Material <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.type" value="normal" class="accent-primary" />
                    <span class="text-sm">Normal</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.type" value="selectable" class="accent-primary" />
                    <span class="text-sm">Selectable (Variant)</span>
                </label>
            </div>
            <p class="text-xs text-muted-foreground">
                <span v-if="form.type === 'normal'">Stok dikelola langsung di material ini.</span>
                <span v-else>User dapat memilih variant saat memesan. Stok dikelola per variant.</span>
            </p>
            <InputError :message="form.errors.type" />
        </div>

        <!-- Base Unit -->
        <div class="grid gap-2">
            <label class="text-sm font-medium leading-none">
                Satuan Dasar <span class="text-red-500">*</span>
            </label>
            <SearchableSelect v-model="form.base_unit_id" :options="unitOptions" placeholder="Pilih Satuan" required />
            <InputError :message="form.errors.base_unit_id" />
        </div>

        <!-- Critical Stock (hanya untuk normal) -->
        <div v-if="form.type === 'normal'" class="grid gap-2">
            <label class="text-sm font-medium leading-none">
                Stok Kritis
                <span class="text-muted-foreground text-xs font-normal ml-1">(opsional)</span>
            </label>
            <input
                v-model="form.critical_stock"
                type="number"
                min="0"
                step="0.01"
                placeholder="0"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <p class="text-xs text-muted-foreground">Jika stok di bawah nilai ini, material akan ditandai sebagai kritis.</p>
            <InputError :message="form.errors.critical_stock" />
        </div>

        <!-- Variants (hanya untuk selectable) -->
        <div v-if="form.type === 'selectable'" class="grid gap-3">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium leading-none">
                    Daftar Variant <span class="text-red-500">*</span>
                </label>
                <button
                    type="button"
                    @click="addVariant"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary/80 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Variant
                </button>
            </div>

            <div v-if="form.variants.length === 0" class="rounded-lg border border-dashed border-muted-foreground/30 p-6 text-center">
                <p class="text-sm text-muted-foreground">Belum ada variant. Klik "Tambah Variant" untuk menambahkan.</p>
            </div>

            <div class="space-y-3">
                <div
                    v-for="(variant, index) in form.variants"
                    :key="index"
                    class="rounded-xl border bg-muted/20 p-4 space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            Variant #{{ index + 1 }}
                        </span>
                        <button
                            type="button"
                            @click="removeVariant(index)"
                            class="text-xs text-red-400 hover:text-red-600 transition"
                        >
                            Hapus
                        </button>
                    </div>

                    <!-- Nama Variant -->
                    <div class="grid gap-1.5">
                        <label class="text-xs font-medium">Nama Variant <span class="text-red-500">*</span></label>
                        <input
                            v-model="variant.name"
                            required
                            placeholder="Contoh: Temanggung"
                            class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        <InputError :message="form.errors[`variants.${index}.name`]" />
                    </div>

                    <!-- Minimum Stok -->
                    <div class="grid gap-1.5">
                        <label class="text-xs font-medium">Stok Minimum</label>
                        <input
                            v-model="variant.minimum_stock"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0"
                            class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        <p class="text-xs text-muted-foreground">Stok diisi melalui menu Purchase (Inbound).</p>
                        <InputError :message="form.errors[`variants.${index}.minimum_stock`]" />
                    </div>
                </div>
            </div>

            <InputError :message="form.errors.variants" />
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a
                :href="backHref ?? '/master/material'"
                class="inline-flex items-center px-5 py-2.5 rounded-xl border text-sm font-medium text-muted-foreground hover:bg-muted transition"
            >
                Batal
            </a>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50"
            >
                {{ form.processing ? 'Menyimpan...' : (submitLabel ?? 'Simpan') }}
            </button>
        </div>

    </form>
</template>