<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import CafeForm from '@/components/master/CafeForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cafe & Table', href: '/master/cafe' },
    { title: 'Tambah Cafe', href: '/master/cafe/create' },
];

const form = useForm({
    name: '',
    address: '',
    address_coordinate: '',
    description: '',
    image: null as File | null,
    phone_number: '',
    ppn_fee: 0,
    qris_fee: 0,
});

const submit = () => form.post('/master/cafe');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Cafe" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Cafe" description="Isi data cafe baru yang akan ditambahkan." />
                    <Link href="/master/cafe" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <CafeForm :form="form" submit-label="Simpan Cafe" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
