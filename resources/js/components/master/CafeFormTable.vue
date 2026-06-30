<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, QrCode, Trash2, Receipt, Eye, EyeOff } from 'lucide-vue-next';
import QRCode from 'qrcode';

interface CafeTable {
    id: number;
    name: string;
    status: 'available' | 'occupied';
    description: string | null;
    is_open_bill?: number | boolean;
    only_preview?: number | boolean;
}

const props = defineProps<{
    m_cafe: any;
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

const downloadQR = async (table: CafeTable) => {
    try {
        const url = `https://cafe.arlettaluxury.com/menu?cafe_id=${props.m_cafe.unique_id}&table_id=${table.id}`;
        const dataUrl = await QRCode.toDataURL(url);

        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = `${props.m_cafe.name} - ${table.name}.png`;
        link.click();
    } catch (error) {
        console.error(error);
    }
};

const toggleOpenBill = (table: CafeTable) => {
    const newVal = table.is_open_bill ? 0 : 1;
    const action = newVal === 1 ? 'Aktifkan' : 'Nonaktifkan';
    if (!confirm(`${action} Open Bill untuk meja "${table.name}"?`)) {
        return;
    }

    router.patch(`/master/cafe/${props.cafeId}/table/${table.id}/toggle-open-bill`, { is_open_bill: newVal }, {
        preserveScroll: true,
        onSuccess: () => {
            // reload to reflect updated props from server
            window.location.reload();
        },
        onError: () => {
            alert('Gagal memperbarui pengaturan open bill.');
        }
    });
};

const toggleOnlyPreview = (table: CafeTable) => {
    const newVal = table.only_preview ? 0 : 1;
    const action = newVal === 1 ? 'Aktifkan' : 'Nonaktifkan';
    if (!confirm(`${action} Preview Only untuk meja "${table.name}"?`)) {
        return;
    }

    router.patch(`/master/cafe/${props.cafeId}/table/${table.id}/toggle-only-preview`, { only_preview: newVal }, {
        preserveScroll: true,
        onSuccess: () => {
            window.location.reload();
        },
        onError: () => {
            alert('Gagal memperbarui pengaturan preview only.');
        }
    });
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
                    <th class="px-6 py-3 text-left font-medium">Open Bill</th>
                    <th class="px-6 py-3 text-left font-medium">Preview Only</th>
                    <th class="px-6 py-3 text-right font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(table, index) in tables" :key="table.id" class="border-t hover:bg-muted/40 transition">
                    <td class="px-6 py-3">{{ index + 1 }}</td>
                    <td class="px-6 py-3 font-medium">{{ table.name }}</td>
                    <td class="px-6 py-3 text-muted-foreground">{{ table.description ?? '-' }}</td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="table.status === 'available'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-orange-100 text-orange-700'">
                            {{ table.status === 'available' ? 'Tersedia' : 'Terpakai' }}
                        </span>
                    </td>
                    <td class="px-6 py-3">
                        <span v-if="table.is_open_bill" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                            Open Bill
                        </span>
                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted/10 text-muted-foreground">
                            -
                        </span>
                    </td>
                    <td class="px-6 py-3">
                        <span v-if="table.only_preview" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                            Preview Only
                        </span>
                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted/10 text-muted-foreground">
                            -
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="downloadQR(table)"
                                class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white transition"
                                title="Download QR">
                                <QrCode :size="14" />
                            </button>

                            <button type="button" @click="toggleOpenBill(table)"
                                :title="table.is_open_bill ? 'Nonaktifkan Open Bill' : 'Aktifkan Open Bill'"
                                :class="['cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md transition', table.is_open_bill ? 'bg-emerald-100 text-emerald-600 hover:bg-emerald-600 hover:text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-600 hover:text-white']">
                                <Receipt class="w-4 h-4" />
                            </button>

                            <button type="button" @click="toggleOnlyPreview(table)"
                                :title="table.only_preview ? 'Nonaktifkan Preview Only' : 'Aktifkan Preview Only'"
                                :class="['cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md transition', table.only_preview ? 'bg-violet-100 text-violet-600 hover:bg-violet-600 hover:text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-600 hover:text-white']">
                                <Eye v-if="table.only_preview" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>

                            <button type="button" @click="deleteTable(table.id)"
                                class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                title="Hapus Meja">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                    </td>
                </tr>

                <tr v-if="tables.length === 0">
                    <td colspan="7" class="px-6 py-10 text-center text-muted-foreground">
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
                    <input v-model="form.name" required placeholder="Nama meja (contoh: Meja 01)"
                        class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="flex-1 grid gap-1">
                    <input v-model="form.description" placeholder="Deskripsi (opsional)"
                        class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring" />
                    <InputError :message="form.errors.description" />
                </div>
                <button type="submit" :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-primary text-primary-foreground text-sm font-medium shadow-sm transition hover:opacity-90 disabled:opacity-50 whitespace-nowrap self-start">
                    <Plus :size="15" />
                    {{ form.processing ? 'Menambahkan...' : 'Tambah Meja' }}
                </button>
            </form>
        </div>

    </div>
</template>
