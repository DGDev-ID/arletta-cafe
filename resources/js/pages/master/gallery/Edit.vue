<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import GalleryForm from '@/components/master/GalleryForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    data: {
        id: number;
        img_url: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gallery', href: '/master/gallery' },
    { title: 'Edit Gambar', href: `/master/gallery/${props.data.id}/edit` },
];

const form = useForm({
    _method: 'PUT',
    image: null as File | null,
});

const submit = () => form.post(`/master/gallery/${props.data.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Gambar" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Gambar" description="Ganti gambar gallery yang sudah ada." />
                    <Link href="/master/gallery" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <GalleryForm
                        :form="form"
                        :existing-img-url="data.img_url"
                        submit-label="Update Gambar"
                        @submit="submit"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>