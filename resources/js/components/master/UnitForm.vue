<script setup lang="ts">
import InputError from '@/components/InputError.vue';

interface UnitFormData {
    name: string;
    critical_stock: number | null;
    errors: Record<string, string>;
    processing: boolean;
}

defineProps<{
    form: UnitFormData;
    submitLabel?: string;
    backHref?: string;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">

        <!-- Nama Unit -->
        <div class="grid gap-2">
            <label for="unit-name" class="text-sm font-medium leading-none">
                Nama Unit <span class="text-red-500">*</span>
            </label>
            <input
                id="unit-name"
                v-model="form.name"
                required
                placeholder="Contoh: Kilogram, Liter, Pcs"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.name" />
        </div>

        <!-- Critical Stock -->
        <div class="grid gap-2">
            <label for="unit-critical-stock" class="text-sm font-medium leading-none">
                Stok Kritis
            </label>
            <input
                id="unit-critical-stock"
                v-model.number="form.critical_stock"
                type="number"
                min="0"
                placeholder="Contoh: 10"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.critical_stock" />
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a
                :href="backHref ?? '/master/unit'"
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
