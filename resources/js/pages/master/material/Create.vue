<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import MaterialForm from '@/components/master/MaterialForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface CafeOption { id: number; name: string; }
interface UnitOption { id: number; name: string; }

defineProps<{
    cafes: CafeOption[];
    units: UnitOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Material', href: '/master/material' },
    { title: 'Tambah Material', href: '/master/material/create' },
];

const form = useForm({
    cafe_id: '' as number | '',
    name: '',
    base_unit_id: '' as number | '',
    critical_stock: '' as number | '',
});

const submit = () => form.post('/master/material');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Material" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Material" description="Isi data material baru yang akan ditambahkan." />
                    <Link href="/master/material" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <MaterialForm :form="form" :cafes="cafes" :units="units" submit-label="Simpan Material" @submit="submit" />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
