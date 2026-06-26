<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import PromoBannerForm from '@/components/master/PromoBannerForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Banner Promo', href: '/master/promo-banner' },
    { title: 'Tambah Banner', href: '/master/promo-banner/create' },
];

const form = useForm({
    title: '',
    image: null as File | null,
    start_date: '',
    end_date: '',
    sort_order: 0,
    is_active: true,
});

const submit = () => form.post('/master/promo-banner');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Banner Promo" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Banner Promo"
                        description="Upload banner promo baru untuk ditampilkan di halaman menu." />
                    <Link href="/master/promo-banner"
                        class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <PromoBannerForm
                        :form="form"
                        submit-label="Simpan Banner"
                        @submit="submit"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
