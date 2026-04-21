<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import MenuCategoryForm from '@/components/master/MenuCategoryForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface CafeOption { id: number; name: string; }
interface CategoryOption { id: number; cafe_id: number; name: string; parent_id?: number | null; }

const props = defineProps<{
    data: {
        id: number;
        cafe_id: number;
        name: string;
        parent_id: number | null;
        description: string | null;
    };
    cafes: CafeOption[];
    categories: CategoryOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kategori Menu', href: '/master/menu-category' },
    { title: 'Edit Kategori', href: `/master/menu-category/${props.data.id}/edit` },
];

const form = useForm({
    cafe_id: props.data.cafe_id as number | '',
    name: props.data.name,
    parent_id: props.data.parent_id,
    description: props.data.description ?? '',
});

const submit = () => form.put(`/master/menu-category/${props.data.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Kategori Menu" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Kategori Menu" description="Ubah data kategori menu yang sudah ada." />
                    <Link href="/master/menu-category" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <MenuCategoryForm :form="form" :cafes="cafes" :categories="categories" submit-label="Simpan Perubahan" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
