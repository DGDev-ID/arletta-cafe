<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import MenuForm from '@/components/master/MenuForm.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface CafeOption { id: number; name: string; }
interface CategoryOption { id: number; cafe_id: number; name: string; }
interface MaterialOption { id: number; cafe_id: number; name: string; base_unit_id: number; base_unit: { id: number; name: string }; }
interface UnitOption { id: number; name: string; }
interface ConverterOption { material_id: number; from_unit_id: number; to_unit_id: number; }
interface SfmOption { id: number; cafe_id: number; name: string; }

const props = defineProps<{
    data: {
        id: number;
        cafe_id: number;
        menu_category_id: number | null;
        name: string;
        description: string | null;
        img_url: string | null;
        price: number;
        status: string;
        promo: {
            type: string;
            discount_amount: number;
        } | null;
        menu_materials: {
            material_id: number;
            amount: number;
            unit_id: number;
        }[];
        menu_semi_finished_materials: {
            semi_finished_material_id: number;
            multiplier: number;
        }[];
    };
    cafes: CafeOption[];
    categories: CategoryOption[];
    materials: MaterialOption[];
    units: UnitOption[];
    converters: ConverterOption[];
    semiFinishedMaterials: SfmOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Menu', href: '/master/menu' },
    { title: 'Edit Menu', href: `/master/menu/${props.data.id}/edit` },
];

const form = useForm({
    cafe_id: props.data.cafe_id as number | '',
    menu_category_id: props.data.menu_category_id,
    name: props.data.name,
    description: props.data.description ?? '',
    image: null as File | null,
    price: props.data.price as number | '',
    has_promo: !!props.data.promo,
    promo_type: props.data.promo?.type ?? '',
    promo_discount_amount: (props.data.promo?.discount_amount ?? '') as number | '',
    materials: props.data.menu_materials.map(mm => ({
        material_id: mm.material_id as number | '',
        amount: mm.amount as number | '',
        unit_id: mm.unit_id as number | '',
    })),
    semi_finished_materials: (props.data.menu_semi_finished_materials ?? []).map(sfm => ({
        semi_finished_material_id: sfm.semi_finished_material_id as number | '',
        multiplier: sfm.multiplier as number | '',
    })),
});

const submit = () => {
    const urlParams = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const page = urlParams.get('page');
    form.put(page ? `/master/menu/${props.data.id}?page=${page}` : `/master/menu/${props.data.id}`);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Menu" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Menu" description="Ubah data menu yang sudah ada." />
                    <Link href="/master/menu" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <MenuForm
                        :form="form"
                        :cafes="cafes"
                        :categories="categories"
                        :all-materials="materials"
                        :units="units"
                        :converters="converters"
                        :semi-finished-materials="semiFinishedMaterials"
                        :existing-img-url="data.img_url"
                        submit-label="Simpan Perubahan"
                        @submit="submit"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
