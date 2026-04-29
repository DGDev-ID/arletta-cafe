<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import PromoForm from '@/components/master/PromoForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Promo', href: '/master/cafe-promo' },
    { title: 'Tambah Promo', href: '/master/cafe-promo/create' },
];

defineProps<{
    cafes: any[];
}>();

const form = useForm({
    cafe_id: '',
    promo_code: '',
    type: 'discount_percent',
    value: '',
});

const submit = () => form.post('/master/cafe-promo');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Promo" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Promo" description="Isi data promo baru yang akan ditambahkan." />
                    <Link href="/master/cafe-promo" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <PromoForm :form="form" :cafes="cafes" submit-label="Simpan Promo" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
