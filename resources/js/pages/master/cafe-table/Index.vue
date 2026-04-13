<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Pencil, Trash2 } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Cafe & Table',
        href: '/master/cafe',
    },
];

const deleteCafe = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus cafe ini?')) {
        router.delete(`/master/cafe/${id}`);
    }
};

defineProps<{
    data: any;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Cafe & Table" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <Heading variant="small" title="Master Cafe"
                        description="Kelola daftar cafe, lokasi, dan list meja cafe." />

                    <Link href="/master/cafe/create"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                        Tambah Cafe
                    </Link>
                </div>

                <!-- Table Card -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Nama Cafe</th>
                                <th class="px-6 py-4 text-left font-medium">Lokasi</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(cafe, index) in data.data" :key="cafe.id"
                                class="border-t hover:bg-muted/40 transition">
                                <!-- No -->
                                <td class="px-6 py-4">
                                    {{ index + 1 + (data.current_page - 1) * data.per_page }}
                                </td>

                                <!-- Name -->
                                <td class="px-6 py-4 font-medium">
                                    {{ cafe.name }}
                                </td>

                                <!-- address -->
                                <td class="px-6 py-4 font-medium">
                                    {{ cafe.address }}
                                </td>

                                <!-- Action -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-3">

                                        <!-- Edit -->
                                        <Link :href="`/master/cafe/${cafe.id}/edit`"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                            title="Edit Cafe">
                                            <Pencil :size="16" />
                                        </Link>

                                        <!-- Delete -->
                                        <button @click="deleteCafe(cafe.id)" type="button"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                            title="Hapus Cafe">
                                            <Trash2 :size="16" />
                                        </button>

                                    </div>
                                </td>
                            </tr>

                            <!-- Empty -->
                            <tr v-if="data.data.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada data cafe.
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