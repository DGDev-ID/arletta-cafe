<script setup lang="ts">
import InputError from '@/components/InputError.vue';

interface CafeOption {
    id: number;
    name: string;
}

interface UnitOption {
    id: number;
    name: string;
}

interface MaterialFormData {
    cafe_id: number | '';
    name: string;
    base_unit_id: number | '';
    errors: Record<string, string>;
    processing: boolean;
}

defineProps<{
    form: MaterialFormData;
    cafes: CafeOption[];
    units: UnitOption[];
    submitLabel?: string;
    backHref?: string;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">

        <!-- Cafe -->
        <div class="grid gap-2">
            <label for="material-cafe" class="text-sm font-medium leading-none">
                Cafe <span class="text-red-500">*</span>
            </label>
            <select
                id="material-cafe"
                v-model="form.cafe_id"
                required
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option value="" disabled>Pilih Cafe</option>
                <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">
                    {{ cafe.name }}
                </option>
            </select>
            <InputError :message="form.errors.cafe_id" />
        </div>

        <!-- Nama Material -->
        <div class="grid gap-2">
            <label for="material-name" class="text-sm font-medium leading-none">
                Nama Material <span class="text-red-500">*</span>
            </label>
            <input
                id="material-name"
                v-model="form.name"
                required
                placeholder="Contoh: Biji Kopi Arabika"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.name" />
        </div>

        <!-- Base Unit -->
        <div class="grid gap-2">
            <label for="material-unit" class="text-sm font-medium leading-none">
                Satuan Dasar <span class="text-red-500">*</span>
            </label>
            <select
                id="material-unit"
                v-model="form.base_unit_id"
                required
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option value="" disabled>Pilih Satuan</option>
                <option v-for="unit in units" :key="unit.id" :value="unit.id">
                    {{ unit.name }}
                </option>
            </select>
            <InputError :message="form.errors.base_unit_id" />
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
