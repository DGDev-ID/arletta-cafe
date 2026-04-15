<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import UnitForm from '@/components/master/UnitForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    data: {
        id: number;
        name: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Unit', href: '/master/unit' },
    { title: 'Edit Unit', href: `/master/unit/${props.data.id}/edit` },
];

const form = useForm({
    name: props.data.name,
});

const submit = () => form.put(`/master/unit/${props.data.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Unit" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Unit" description="Ubah data unit yang sudah ada." />
                    <Link href="/master/unit" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <UnitForm :form="form" submit-label="Simpan Perubahan" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
