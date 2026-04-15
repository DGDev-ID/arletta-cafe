<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import UnitForm from '@/components/master/UnitForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Unit', href: '/master/unit' },
    { title: 'Tambah Unit', href: '/master/unit/create' },
];

const form = useForm({
    name: '',
});

const submit = () => form.post('/master/unit');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Unit" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Unit" description="Isi data unit baru yang akan ditambahkan." />
                    <Link href="/master/unit" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <UnitForm :form="form" submit-label="Simpan Unit" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
