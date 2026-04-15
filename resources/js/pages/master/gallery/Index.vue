<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Pencil, Trash2 } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gallery', href: '/master/gallery' },
];

defineProps<{
    data: any;
}>();

const deleteGallery = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus gambar ini?')) {
        router.delete(`/master/gallery/${id}`);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Gallery" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <Heading variant="small" title="Master Gallery" description="Kelola gambar gallery untuk cafe." />
                    <Link href="/master/gallery/create"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90">
                        Tambah Gambar
                    </Link>
                </div>

                <!-- Gallery Grid -->
                <div class="rounded-2xl border bg-background shadow-sm p-6">
                    <div v-if="data.data.length === 0" class="py-10 text-center text-muted-foreground">
                        Belum ada gambar di gallery.
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div v-for="item in data.data" :key="item.id" class="group relative rounded-xl border overflow-hidden shadow-sm">
                            <img :src="item.img_url" alt="Gallery image" class="w-full h-48 object-cover" />

                            <!-- Overlay actions -->
                            <div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 group-hover:opacity-100 transition">
                                <Link :href="`/master/gallery/${item.id}/edit`"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-yellow-100 text-yellow-600 hover:bg-yellow-500 hover:text-white transition"
                                    title="Edit">
                                    <Pencil :size="16" />
                                </Link>
                                <button @click="deleteGallery(item.id)" type="button"
                                    class="cursor-pointer inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                    title="Hapus">
                                    <Trash2 :size="16" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="data.data.length > 0" class="mt-6">
                        <Pagination :links="data.links" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>