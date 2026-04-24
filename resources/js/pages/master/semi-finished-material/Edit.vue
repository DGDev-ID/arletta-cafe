<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import SemiFinishedMaterialForm from '@/components/master/SemiFinishedMaterialForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface CafeOption { id: number; name: string; }
interface MaterialOption { id: number; cafe_id: number; name: string; base_unit_id: number; base_unit: { id: number; name: string }; }
interface UnitOption { id: number; name: string; }
interface ConverterOption { material_id: number; from_unit_id: number; to_unit_id: number; }

const props = defineProps<{
    data: {
        id: number;
        cafe_id: number;
        name: string;
        base_unit_id: number;
        details: {
            material_id: number;
            amount: number;
            unit_id: number;
        }[];
    };
    cafes: CafeOption[];
    materials: MaterialOption[];
    units: UnitOption[];
    converters: ConverterOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Production', href: '/master/semi-finished-material' },
    { title: 'Edit', href: `/master/semi-finished-material/${props.data.id}/edit` },
];

const form = useForm({
    cafe_id: props.data.cafe_id as number | '',
    name: props.data.name,
    base_unit_id: props.data.base_unit_id as number | '',
    details: props.data.details.map(d => ({
        material_id: d.material_id as number | '',
        amount: d.amount as number | '',
        unit_id: d.unit_id as number | '',
    })),
});

const submit = () => form.put(`/master/semi-finished-material/${props.data.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Production" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Production" description="Ubah data bahan setengah jadi." />
                    <Link href="/master/semi-finished-material" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <SemiFinishedMaterialForm
                        :form="form"
                        :cafes="cafes"
                        :materials="materials"
                        :units="units"
                        :converters="converters"
                        submit-label="Simpan Perubahan"
                        @submit="submit"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
