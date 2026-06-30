<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface PromoBannerFormData {
    title: string;
    image: File | null;
    start_date: string;
    end_date: string;
    sort_order: number;
    is_active: boolean;
    errors: Record<string, string>;
    processing: boolean;
}

const props = defineProps<{
    form: PromoBannerFormData;
    existingImgUrl?: string | null;
    submitLabel?: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const imagePreview = ref<string | null>(props.existingImgUrl ?? null);

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
    const input = document.getElementById('banner-img') as HTMLInputElement;
    if (input) input.value = '';
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-8">

        <!-- Image Upload -->
        <div class="space-y-6">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Upload Gambar</h3>

            <div class="grid gap-2">
                <label for="banner-img" class="text-sm font-medium leading-none">
                    Gambar Banner <span class="text-red-500">*</span>
                </label>
                <div class="space-y-3">
                    <!-- Image Preview -->
                    <div v-if="imagePreview" class="relative inline-block">
                        <img :src="imagePreview" alt="Preview"
                            class="h-48 w-auto rounded-xl border object-cover shadow-sm" />
                        <button type="button" @click="removeImage"
                            class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow-sm hover:bg-red-600 transition">
                            <X :size="14" />
                        </button>
                    </div>

                    <!-- Upload Area -->
                    <label for="banner-img"
                        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-muted-foreground/30 bg-muted/30 px-6 py-8 text-center transition hover:border-primary/50 hover:bg-muted/50">
                        <Upload :size="24" class="text-muted-foreground/60" />
                        <span class="text-sm text-muted-foreground">
                            Klik untuk upload atau drag & drop
                        </span>
                        <span class="text-xs text-muted-foreground/60">JPG, JPEG, PNG, WEBP (max 10MB)</span>
                        <input id="banner-img" type="file" accept=".jpg,.jpeg,.png,.webp" class="hidden"
                            @change="onFileChange" />
                    </label>
                </div>
                <InputError :message="form.errors.image" />
            </div>
        </div>

        <!-- Details -->
        <div class="space-y-6">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Detail Banner</h3>

            <!-- Title -->
            <div class="grid gap-2">
                <label for="banner-title" class="text-sm font-medium leading-none">
                    Judul <span class="text-red-500">*</span>
                </label>
                <input id="banner-title" v-model="form.title" type="text" placeholder="Judul banner promo"
                    class="w-full rounded-xl border bg-background px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                <InputError :message="form.errors.title" />
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <label for="banner-start-date" class="text-sm font-medium leading-none">
                        Tanggal Mulai
                    </label>
                    <input id="banner-start-date" v-model="form.start_date" type="date"
                        class="w-full rounded-xl border bg-background px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                    <p class="text-xs text-muted-foreground">Kosongkan jika selalu aktif.</p>
                    <InputError :message="form.errors.start_date" />
                </div>
                <div class="grid gap-2">
                    <label for="banner-end-date" class="text-sm font-medium leading-none">
                        Tanggal Selesai
                    </label>
                    <input id="banner-end-date" v-model="form.end_date" type="date"
                        class="w-full rounded-xl border bg-background px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                    <p class="text-xs text-muted-foreground">Kosongkan jika selalu aktif.</p>
                    <InputError :message="form.errors.end_date" />
                </div>
            </div>

            <!-- Sort Order -->
            <div class="grid gap-2">
                <label for="banner-sort-order" class="text-sm font-medium leading-none">
                    Urutan (Sort Order)
                </label>
                <input id="banner-sort-order" v-model.number="form.sort_order" type="number" min="0" placeholder="0"
                    class="w-full rounded-xl border bg-background px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring md:w-40" />
                <p class="text-xs text-muted-foreground">Semakin kecil semakin di depan.</p>
                <InputError :message="form.errors.sort_order" />
            </div>

            <!-- Status Active -->
            <div class="flex items-center gap-3">
                <input id="banner-is-active" v-model="form.is_active" type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
                <label for="banner-is-active" class="text-sm font-medium leading-none">
                    Aktif
                </label>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a href="/master/promo-banner"
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
