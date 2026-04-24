<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown, Check, Search } from 'lucide-vue-next';

interface Option {
    value: string | number;
    label: string;
}

const props = defineProps<{
    modelValue: string | number | null | '';
    options: Option[];
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null | ''];
    'change': [value: string | number | null | ''];
}>();

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

onClickOutside(containerRef, () => {
    isOpen.value = false;
});

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    return props.options.filter(opt => 
        String(opt.label).toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const selectedOption = computed(() => {
    return props.options.find(opt => opt.value === props.modelValue);
});

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        nextTick(() => {
            searchInputRef.value?.focus();
        });
    }
};

const selectOption = (option: Option) => {
    emit('update:modelValue', option.value);
    emit('change', option.value);
    isOpen.value = false;
};
</script>

<template>
    <div class="relative w-full" ref="containerRef">
        <!-- Hidden input for required validation if needed -->
        <select v-if="required" :value="modelValue" class="sr-only" required tabindex="-1">
            <option v-if="!modelValue" value=""></option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <button type="button" @click="toggleDropdown" :disabled="disabled"
            class="flex items-center justify-between w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-left">
            <span class="block truncate" :class="{'text-muted-foreground': !selectedOption}">
                {{ selectedOption ? selectedOption.label : (placeholder || 'Pilih...') }}
            </span>
            <ChevronDown class="w-4 h-4 ml-2 opacity-50 shrink-0" />
        </button>

        <div v-if="isOpen"
            class="absolute z-50 w-full mt-1 bg-background border rounded-lg shadow-lg overflow-hidden">
            <div class="p-2 border-b flex items-center gap-2">
                <Search class="w-4 h-4 opacity-50 shrink-0" />
                <input ref="searchInputRef" v-model="searchQuery" type="text"
                    class="w-full text-sm bg-transparent border-none focus:ring-0 p-0 outline-none placeholder:text-muted-foreground"
                    placeholder="Cari..." />
            </div>
            <ul class="max-h-60 overflow-auto py-1">
                <li v-for="option in filteredOptions" :key="option.value"
                    @click="selectOption(option)"
                    class="flex items-center justify-between px-3 py-2 text-sm cursor-pointer hover:bg-muted transition">
                    <span class="block truncate">{{ option.label }}</span>
                    <Check v-if="modelValue === option.value" class="w-4 h-4 text-primary shrink-0" />
                </li>
                <li v-if="filteredOptions.length === 0"
                    class="px-3 py-2 text-sm text-muted-foreground text-center">
                    Tidak ditemukan
                </li>
            </ul>
        </div>
    </div>
</template>
