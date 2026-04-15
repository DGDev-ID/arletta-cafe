<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import GalleryForm from '@/components/master/GalleryForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gallery', href: '/master/gallery' },
    { title: 'Tambah Gambar', href: '/master/gallery/create' },
];

const form = useForm({
    image: null as File | null,
});

const submit = () => form.post('/master/gallery');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Gambar" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Gambar" description="Upload gambar baru ke gallery." />
                    <Link href="/master/gallery" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <GalleryForm
                        :form="form"
                        submit-label="Upload Gambar"
                        @submit="submit"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>