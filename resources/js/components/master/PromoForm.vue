<script setup lang="ts">
import InputError from '@/components/InputError.vue';

defineProps<{
    form: any;
    submitLabel: string;
    cafes: any[];
}>();

defineEmits(['submit']);
</script>

<template>
    <form @submit.prevent="$emit('submit')" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Cafe ID -->
            <div class="space-y-2">
                <label for="cafe_id" class="text-sm font-medium leading-none">Cafe <span
                        class="text-red-500">*</span></label>
                <select id="cafe_id" v-model="form.cafe_id" required
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="" disabled>Pilih Cafe</option>
                    <option v-for="cafe in cafes" :key="cafe.id" :value="cafe.id">
                        {{ cafe.name }}
                    </option>
                </select>
                <InputError :message="form.errors.cafe_id" />
            </div>

            <!-- Promo Code -->
            <div class="space-y-2">
                <label for="promo_code" class="text-sm font-medium leading-none">Kode Promo <span
                        class="text-red-500">*</span></label>
                <input id="promo_code" v-model="form.promo_code" placeholder="Misal: DISKON50" required
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                <InputError :message="form.errors.promo_code" />
            </div>

            <!-- Type -->
            <div class="space-y-2">
                <label for="type" class="text-sm font-medium leading-none">Tipe Diskon <span
                        class="text-red-500">*</span></label>
                <select id="type" v-model="form.type" required
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="discount_percent">Persentase (%)</option>
                    <option value="discount_amount">Nominal / Harga Langsung</option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <!-- Value -->
            <div class="space-y-2">
                <label for="value" class="text-sm font-medium leading-none">Nilai Diskon <span
                        class="text-red-500">*</span></label>
                <input id="value" type="number" step="0.01" v-model="form.value" placeholder="Misal: 10 atau 50000"
                    required
                    class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                <InputError :message="form.errors.value" />
                <p v-if="form.type === 'discount_percent'" class="text-xs text-muted-foreground mt-1">Masukkan nilai
                    0-100</p>
                <p v-if="form.type === 'discount_amount'" class="text-xs text-muted-foreground mt-1">Masukkan nominal
                    rupiah</p>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" :disabled="form.processing"
                class="inline-flex items-center justify-center min-w-32 rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50">
                <span v-if="form.processing">Menyimpan...</span>
                <span v-else>{{ submitLabel }}</span>
            </button>
        </div>
    </form>
</template>
