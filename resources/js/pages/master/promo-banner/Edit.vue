<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import PromoBannerForm from '@/components/master/PromoBannerForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Cafe {
    id: number;
    name: string;
}

const props = defineProps<{
    banner: {
        id: number;
        title: string;
        image_url: string;
        start_date: string | null;
        end_date: string | null;
        sort_order: number;
        is_active: boolean;
        cafe_ids: number[];
    };
    cafes: Cafe[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Banner Promo', href: '/master/promo-banner' },
    { title: 'Edit Banner', href: `/master/promo-banner/${props.banner.id}/edit` },
];

const form = useForm({
    _method: 'PUT',
    title: props.banner.title,
    image: null as File | null,
    start_date: props.banner.start_date ?? '',
    end_date: props.banner.end_date ?? '',
    sort_order: props.banner.sort_order,
    is_active: props.banner.is_active,
    cafe_ids: [...props.banner.cafe_ids],
});

const submit = () => form.post(`/master/promo-banner/${props.banner.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Banner Promo" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Banner Promo"
                        description="Perbarui informasi banner promo." />
                    <Link href="/master/promo-banner"
                        class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <PromoBannerForm
                        :form="form"
                        :cafes="props.cafes"
                        :existing-img-url="banner.image_url"
                        submit-label="Update Banner"
                        @submit="submit"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
