<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface GalleryFormData {
    image: File | null;
    errors: Record<string, string>;
    processing: boolean;
}

const props = defineProps<{
    form: GalleryFormData;
    existingImgUrl?: string | null;
    submitLabel?: string;
    backHref?: string;
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
    const input = document.getElementById('gallery-img') as HTMLInputElement;
    if (input) input.value = '';
}
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-8">

        <div class="space-y-6">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Upload Gambar</h3>

            <!-- Image Upload -->
            <div class="grid gap-2">
                <label for="gallery-img" class="text-sm font-medium leading-none">
                    Gambar <span class="text-red-500">*</span>
                </label>
                <div class="space-y-3">
                    <!-- Image Preview -->
                    <div v-if="imagePreview" class="relative inline-block">
                        <img :src="imagePreview" alt="Preview" class="h-48 w-auto rounded-xl border object-cover shadow-sm" />
                        <button type="button" @click="removeImage"
                            class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow-sm hover:bg-red-600 transition">
                            <X :size="14" />
                        </button>
                    </div>

                    <!-- Upload Area -->
                    <label for="gallery-img"
                        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-muted-foreground/30 bg-muted/30 px-6 py-8 text-center transition hover:border-primary/50 hover:bg-muted/50">
                        <Upload :size="24" class="text-muted-foreground/60" />
                        <span class="text-sm text-muted-foreground">
                            Klik untuk upload atau drag & drop
                        </span>
                        <span class="text-xs text-muted-foreground/60">PNG, JPG, WEBP (max 5MB)</span>
                        <input id="gallery-img" type="file" accept="image/*" class="hidden" @change="onFileChange" />
                    </label>
                </div>
                <InputError :message="form.errors.image" />
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a :href="backHref ?? '/master/gallery'"
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