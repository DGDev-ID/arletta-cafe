<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import PromoForm from '@/components/master/PromoForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    promo: any;
    cafes: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Promo', href: '/master/cafe-promo' },
    { title: 'Edit Promo', href: `/master/cafe-promo/${props.promo.id}/edit` },
];

const form = useForm({
    cafe_id: props.promo.cafe_id,
    promo_code: props.promo.promo_code,
    type: props.promo.type,
    value: props.promo.value,
});

const submit = () => form.put(`/master/cafe-promo/${props.promo.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Promo" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Promo" description="Ubah data promo yang sudah ada." />
                    <Link href="/master/cafe-promo" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <PromoForm :form="form" :cafes="cafes" submit-label="Simpan Perubahan" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
