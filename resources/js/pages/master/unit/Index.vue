<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Pencil, Trash2 } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Unit',
        href: '/master/unit',
    },
];

const deleteUnit = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus unit ini?')) {
        router.delete(`/master/unit/${id}`);
    }
};

defineProps<{
    data: any;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Unit" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <Heading variant="small" title="Master Unit" description="Kelola daftar unit." />

                    <Link href="/master/unit/create"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                        Tambah Unit
                    </Link>
                </div>

                <!-- Table Card -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Nama Unit</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(unit, index) in data.data" :key="unit.id"
                                class="border-t hover:bg-muted/40 transition">
                                <!-- No -->
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>

                                <!-- Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ unit.name }}
                                </td>

                                <!-- Action -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-3">

                                        <!-- Edit -->
                                        <Link :href="`/master/unit/${unit.id}/edit`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                            title="Edit Unit">
                                            <Pencil :size="16" />
                                        </Link>

                                        <!-- Delete -->
                                        <button @click="deleteUnit(unit.id)" type="button"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                            title="Hapus Unit">
                                            <Trash2 :size="16" />
                                        </button>

                                    </div>
                                </td>
                            </tr>

                            <!-- Empty -->
                            <tr v-if="data.data.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data unit.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 bg-background">
                        <Pagination :links="data.links" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>