<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

interface Material {
    id: number;
    name: string;
    stock: string;
    avg_buy_price: string;
    cafe: { id: number; name: string };
    base_unit: { id: number; name: string };
}

interface InboundOutbound {
    id: number;
    type: 'inbound' | 'outbound';
    amount: string;
    inbound_buy_price: string | null;
    base_unit: { name: string };
    created_at: string;
    opening_stock: number;
    closing_stock: number;
    transaction_detail: {
        menu: { name: string };
        amount: string;
    } | null;
    description: string | null;
}

const props = defineProps<{
    material: Material;
    logs: {
        data: InboundOutbound[];
        links: any[];
        current_page: number;
        per_page: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Material', href: '/master/material' },
    { title: props.material.name, href: `/master/material/${props.material.id}` },
];

const formatCurrency = (val: string | null) => {
    if (!val) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(val));
};

function setOutOfStock(id: number) {
    if (window.confirm('Stok akan diubah menjadi 0 dan tercatat sebagai outbound. Lanjutkan?')) {
        router.patch(`/master/material/${id}/out-of-stock`, {}, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head :title="`Material Logs`" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex items-center justify-between">
                    <Heading variant="small" :title="material.name"
                        description="Detail material dan riwayat inbound/outbound." />
                    <div class="flex gap-2">
                        <Link href="/master/material"
                            class="text-sm text-muted-foreground hover:text-foreground transition self-center">
                            ← Kembali
                        </Link>
                    </div>
                </div>

                <!-- Detail Card -->
                <div class="rounded-2xl border bg-background shadow-sm p-6">
                    <h2 class="text-base font-semibold mb-4">Informasi Material</h2>
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Cafe</dt>
                            <dd class="font-medium mt-0.5">{{ material.cafe.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Nama Material</dt>
                            <dd class="font-medium mt-0.5">{{ material.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Satuan Dasar</dt>
                            <dd class="font-medium mt-0.5">{{ material.base_unit.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Stok</dt>
                            <dd class="font-medium mt-0.5">{{ material.stock }} {{ material.base_unit.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Rata-Rata Harga Beli</dt>
                            <dd class="font-medium mt-0.5">{{ formatCurrency(material.avg_buy_price) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Action</dt>
                            <dd class="font-medium mt-0.5">
                                <span v-if="Number(material.stock) <= 0">Out of Stock</span>
                                <button v-else @click="setOutOfStock(material.id)"
                                    class="cursor-pointer p-2 rounded-md bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition"
                                    :disabled="Number(material.stock) <= 0">
                                    <span>Set Out of Stock</span>
                                </button>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Inbound / Outbound Log -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-base font-semibold">Riwayat Inbound / Outbound</h2>
                        <p class="text-sm text-muted-foreground">Log pergerakan stok material ini.</p>
                    </div>

                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-3 text-left font-medium">No</th>
                                <th class="px-6 py-3 text-left font-medium">Tipe</th>
                                <th class="px-6 py-3 text-left font-medium">Opening Stock ({{ material.base_unit.name
                                    }})</th>
                                <th class="px-6 py-3 text-left font-medium">Jumlah</th>
                                <th class="px-6 py-3 text-left font-medium">Satuan</th>
                                <th class="px-6 py-3 text-left font-medium">Closing Stock ({{ material.base_unit.name
                                    }})</th>
                                <th class="px-6 py-3 text-left font-medium">Harga Beli</th>
                                <th class="px-6 py-3 text-left font-medium">Tanggal</th>
                                <th class="px-6 py-3 text-left font-medium">Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(log, index) in logs.data" :key="log.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-3 relative overflow-visible">
                                    {{ index + 1 + (logs.current_page - 1) * logs.per_page }}
                                </td>
                                <td class="px-6 py-3">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="log.type === 'inbound'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-red-100 text-red-700'">
                                        {{ log.type === 'inbound' ? 'Inbound' : 'Outbound' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">{{ log.opening_stock }}</td>
                                <td class="px-6 py-3 font-medium">{{ log.amount }}</td>
                                <td class="px-6 py-3">{{ log.base_unit.name }}</td>
                                <td class="px-6 py-3">{{ log.closing_stock }}</td>
                                <td class="px-6 py-3">{{ formatCurrency(log.inbound_buy_price) }}</td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    {{ new Date(log.created_at).toLocaleDateString('id-ID', {
                                        day: '2-digit', month:
                                            'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                    }) }}
                                </td>
                                <td class="px-6 py-3">
                                    <div v-if="log.type === 'outbound' && log.transaction_detail"
                                        class="relative group inline-block">
                                        <!-- icon bulat -->
                                        <span
                                            class="w-5 h-5 flex items-center justify-center rounded-full border border-blue-500 text-blue-500 text-xs cursor-pointer bg-white">
                                            ?
                                        </span>
                                        <!-- tooltip -->
                                        <div
                                            class="absolute z-50 hidden group-hover:block bg-white text-gray-700 text-xs rounded px-2 py-1 border border-gray-300 shadow -top-8 left-1/2 -translate-x-1/2 whitespace-normal max-w-xs">
                                            {{ log.transaction_detail.menu.name }} ({{ log.transaction_detail.amount }})
                                        </div>
                                    </div>

                                    <div v-if="log.type === 'inbound' && log.transaction_detail"
                                        class="relative group inline-block">
                                        <!-- icon bulat -->
                                        <span
                                            class="w-5 h-5 flex items-center justify-center rounded-full border border-blue-500 text-blue-500 text-xs cursor-pointer bg-white">
                                            ?
                                        </span>
                                        <!-- tooltip -->
                                        <div
                                            class="absolute z-50 hidden group-hover:block bg-white text-gray-700 text-xs rounded px-2 py-1 border border-gray-300 shadow -top-8 left-1/2 -translate-x-1/2 whitespace-normal max-w-xs">
                                            Pengembalian material dikarenakan transaksi gagal - {{
                                                log.transaction_detail.menu.name }} ({{ log.transaction_detail.amount }})
                                        </div>
                                    </div>

                                    <div v-if="log.type === 'outbound' && !log.transaction_detail && !log.description"
                                        class="relative group inline-block">
                                        <!-- icon bulat -->
                                        <span
                                            class="w-5 h-5 flex items-center justify-center rounded-full border border-blue-500 text-blue-500 text-xs cursor-pointer bg-white">
                                            ?
                                        </span>
                                        <!-- tooltip -->
                                        <div
                                            class="absolute z-50 hidden group-hover:block bg-white text-gray-700 text-xs rounded px-2 py-1 border border-gray-300 shadow -top-8 left-1/2 -translate-x-1/2 whitespace-normal max-w-xs">
                                            Set out of stock oleh admin.
                                        </div>
                                    </div>

                                    <div v-if="log.description" class="relative group inline-block">
                                        <!-- icon bulat -->
                                        <span
                                            class="w-5 h-5 flex items-center justify-center rounded-full border border-blue-500 text-blue-500 text-xs cursor-pointer bg-white">
                                            ?
                                        </span>
                                        <!-- tooltip -->
                                        <div
                                            class="absolute z-50 hidden group-hover:block bg-white text-gray-700 text-xs rounded px-2 py-1 border border-gray-300 shadow -top-8 left-1/2 -translate-x-1/2 whitespace-normal max-w-xs">
                                            {{ log.description }}
                                        </div>
                                    </div>

                                </td>
                            </tr>

                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada riwayat inbound/outbound.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 bg-background border-t">
                        <Pagination :links="logs.links" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
