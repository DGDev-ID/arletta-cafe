<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import MenuCategoryForm from '@/components/master/MenuCategoryForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CafeOption { id: number; name: string; }
interface CategoryOption { id: number; cafe_id: number; name: string; parent_id?: number | null; }

const props = defineProps<{
    cafes: CafeOption[];
    categories: CategoryOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kategori Menu', href: '/master/menu-category' },
    { title: 'Tambah Kategori', href: '/master/menu-category/create' },
];

const form = useForm({
    cafe_id: '' as number | '',
    name: '',
    parent_id: null as number | null,
    description: '',
});

const filteredCategories = computed(() => {
    return props.categories.filter(c => 
        !c.parent_id || c.parent_id === 0 || c.parent_id === '0' || c.parent_id === ''
    );
});

const submit = () => form.post('/master/menu-category');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Kategori Menu" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Kategori Menu" description="Isi data kategori menu baru yang akan ditambahkan." />
                    <Link href="/master/menu-category" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <MenuCategoryForm :form="form" :cafes="cafes" :categories="filteredCategories" submit-label="Simpan Kategori" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
