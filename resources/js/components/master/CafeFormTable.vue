<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface CafeTable {
    id: number;
    name: string;
    status: 'available' | 'occupied';
    description: string | null;
}

const props = defineProps<{
    cafeId: number;
    tables: CafeTable[];
}>();

const form = useForm({
    name: '',
    description: '',
});

const addTable = () => {
    form.post(`/master/cafe/${props.cafeId}/table`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const deleteTable = (tableId: number) => {
    if (confirm('Hapus meja ini?')) {
        router.delete(`/master/cafe/${props.cafeId}/table/${tableId}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">

        <!-- Header -->
        <div class="px-6 py-4 border-b">
            <h2 class="text-base font-semibold">Daftar Meja</h2>
            <p class="text-sm text-muted-foreground">Kelola meja yang tersedia di cafe ini.</p>
        </div>

        <!-- Table -->
        <table class="min-w-full text-sm">
            <thead class="bg-muted/50">
                <tr class="text-muted-foreground">
                    <th class="px-6 py-3 text-left font-medium">No</th>
                    <th class="px-6 py-3 text-left font-medium">Nama Meja</th>
                    <th class="px-6 py-3 text-left font-medium">Deskripsi</th>
                    <th class="px-6 py-3 text-left font-medium">Status</th>
                    <th class="px-6 py-3 text-right font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(table, index) in tables"
                    :key="table.id"
                    class="border-t hover:bg-muted/40 transition"
                >
                    <td class="px-6 py-3">{{ index + 1 }}</td>
                    <td class="px-6 py-3 font-medium">{{ table.name }}</td>
                    <td class="px-6 py-3 text-muted-foreground">{{ table.description ?? '-' }}</td>
                    <td class="px-6 py-3">
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="table.status === 'available'
                                ? 'bg-green-100 text-green-700'
                                : 'bg-orange-100 text-orange-700'"
                        >
                            {{ table.status === 'available' ? 'Tersedia' : 'Terpakai' }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                @click="deleteTable(table.id)"
                                class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                title="Hapus Meja"
                            >
                                <Trash2 :size="14" />
                            </button>
                        </div>
                    </td>
                </tr>

                <tr v-if="tables.length === 0">
                    <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">
                        Belum ada meja. Tambahkan di bawah.
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Add Table Form -->
        <div class="px-6 py-5 border-t bg-muted/20">
            <p class="text-sm font-medium mb-3 flex items-center gap-2">
                <Plus :size="15" />
                Tambah Meja Baru
            </p>
            <form @submit.prevent="addTable" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 grid gap-1">
                    <input
                        v-model="form.name"
                        required
                        placeholder="Nama meja (contoh: Meja 01)"
                        class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="flex-1 grid gap-1">
                    <input
                        v-model="form.description"
                        placeholder="Deskripsi (opsional)"
                        class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-primary text-primary-foreground text-sm font-medium shadow-sm transition hover:opacity-90 disabled:opacity-50 whitespace-nowrap self-start"
                >
                    <Plus :size="15" />
                    {{ form.processing ? 'Menambahkan...' : 'Tambah Meja' }}
                </button>
            </form>
        </div>

    </div>
</template>
