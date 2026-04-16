<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import CafeForm from '@/components/master/CafeForm.vue';
import CafeFormAdminCashier from '@/components/master/CafeFormAdminCashier.vue';
import CafeFormTable from '@/components/master/CafeFormTable.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface UserOption {
    id: number;
    name: string;
    email: string;
}

interface CafeTableItem {
    id: number;
    name: string;
    status: 'available' | 'occupied';
    description: string | null;
}

const props = defineProps<{
    data: {
        id: number;
        unique_id: string;
        name: string;
        address: string;
        address_coordinate: string;
        ppn_fee: number;
        qris_fee: number;
        description: string;
        img_url: string | null;
        phone_number: string | null;
        cafe_admins: { user_id: number }[];
        cafe_cashiers: { user_id: number }[];
        tables: CafeTableItem[];
    };
    admins: UserOption[];
    cashiers: UserOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cafe & Table', href: '/master/cafe' },
    { title: 'Edit Cafe', href: `/master/cafe/${props.data.id}/edit` },
];

const form = useForm({
    _method: 'PUT',
    name: props.data.name,
    address: props.data.address,
    address_coordinate: props.data.address_coordinate ?? '',
    description: props.data.description ?? '',
    image: null as File | null,
    phone_number: props.data.phone_number ?? '',
    ppn_fee: props.data.ppn_fee ?? 0,
    qris_fee: props.data.qris_fee ?? 0,
    admin_ids: props.data.cafe_admins.map((a) => a.user_id),
    cashier_ids: props.data.cafe_cashiers.map((c) => c.user_id),
});

const submit = () => form.post(`/master/cafe/${props.data.id}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit Cafe" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Edit Cafe" description="Ubah data cafe yang sudah ada." />
                    <Link href="/master/cafe" class="text-sm text-muted-foreground hover:text-foreground transition">
                        ← Kembali
                    </Link>
                </div>

                <!-- Cafe Info -->
                <div class="rounded-2xl border bg-background shadow-sm p-8">
                    <h2 class="text-base font-semibold mb-6">Informasi Cafe</h2>
                    <CafeForm :form="form" submit-label="Simpan Perubahan" @submit="submit" />
                </div>

                <!-- Admin & Cashier -->
                <CafeFormAdminCashier
                    :admins="admins"
                    :cashiers="cashiers"
                    v-model:selected-admin-ids="form.admin_ids"
                    v-model:selected-cashier-ids="form.cashier_ids"
                    :errors="form.errors as any"
                />

                <!-- Meja -->
                <CafeFormTable :cafe-id="data.id" :tables="data.tables" :m_cafe="data" />

            </div>
        </div>
    </AppLayout>
</template>
