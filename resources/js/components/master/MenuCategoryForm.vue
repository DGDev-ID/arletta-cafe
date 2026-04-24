<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { computed } from 'vue';

interface CafeOption {
    id: number;
    name: string;
}

interface CategoryOption {
    id: number;
    cafe_id: number;
    name: string;
    parent_id?: number | null;
}

interface MenuCategoryFormData {
    cafe_id: number | '';
    name: string;
    parent_id: number | '' | null;
    description: string;
    errors: Record<string, string>;
    processing: boolean;
}

const props = defineProps<{
    form: MenuCategoryFormData;
    cafes: CafeOption[];
    categories: CategoryOption[];
    submitLabel?: string;
    backHref?: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const cafeOptions = computed(() => props.cafes.map(c => ({ value: c.id, label: c.name })));
const parentOptions = computed(() => {
    const parentCats = [{ value: null, label: 'Tidak ada (root)' }];
    const available = props.categories.filter(c => c.cafe_id === props.form.cafe_id);
    return parentCats.concat(available.map(c => ({ value: c.id, label: c.name })));
});
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">

        <!-- Cafe -->
        <div class="grid gap-2">
            <label for="category-cafe" class="text-sm font-medium leading-none">
                Cafe <span class="text-red-500">*</span>
            </label>
            <SearchableSelect
                v-model="form.cafe_id"
                :options="cafeOptions"
                placeholder="Pilih Cafe"
                required
            />
            <InputError :message="form.errors.cafe_id" />
        </div>

        <!-- Nama Kategori -->
        <div class="grid gap-2">
            <label for="category-name" class="text-sm font-medium leading-none">
                Nama Kategori <span class="text-red-500">*</span>
            </label>
            <input
                id="category-name"
                v-model="form.name"
                required
                placeholder="Contoh: Minuman"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.name" />
        </div>

        <!-- Parent Category -->
        <div class="grid gap-2">
            <label for="category-parent" class="text-sm font-medium leading-none">
                Kategori Induk
            </label>
            <SearchableSelect
                v-model="form.parent_id"
                :options="parentOptions"
                placeholder="Tidak ada (root)"
            />
            <InputError :message="form.errors.parent_id" />
        </div>

        <!-- Deskripsi -->
        <div class="grid gap-2">
            <label for="category-description" class="text-sm font-medium leading-none">
                Deskripsi
            </label>
            <textarea
                id="category-description"
                v-model="form.description"
                rows="3"
                placeholder="Deskripsi kategori (opsional)"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.description" />
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a
                :href="backHref ?? '/master/menu-category'"
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
