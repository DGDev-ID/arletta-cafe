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

defineProps<{
    cafes: CafeOption[];
    categories: CategoryOption[];
    materials: MaterialOption[];
    units: UnitOption[];
    converters: ConverterOption[];
    semiFinishedMaterials: SfmOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Menu', href: '/master/menu' },
    { title: 'Tambah Menu', href: '/master/menu/create' },
];

const form = useForm({
    cafe_id: '' as number | '',
    menu_category_id: null as number | null,
    name: '',
    description: '',
    image: null as File | null,
    price: '' as number | '',
    has_promo: false,
    promo_type: '',
    promo_discount_amount: '' as number | '',
    materials: [] as { material_id: number | ''; amount: number | ''; unit_id: number | '' }[],
    semi_finished_materials: [] as { semi_finished_material_id: number | ''; multiplier: number | '' }[],
});

const submit = () => {
    const urlParams = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const page = urlParams.get('page');
    form.post(page ? `/master/menu?page=${page}` : '/master/menu');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tambah Menu" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Tambah Menu" description="Isi data menu baru yang akan ditambahkan." />
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
                        submit-label="Simpan Menu"
                        @submit="submit"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>
