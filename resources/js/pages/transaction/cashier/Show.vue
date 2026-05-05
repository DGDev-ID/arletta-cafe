<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { computed, ref } from 'vue';
import { CheckCircle, XCircle } from 'lucide-vue-next';

interface TransactionDetail {
    id: number;
    menu: {
        id: number;
        name: string;
        price: string;
        category: { id: number; name: string } | null;
    } | null;
    amount: number;
    price: string;
    description: string | null;
    status?: string | null;
}

interface Transaction {
    id: number;
    cafe_id: number;
    table_id: number | null;
    cust_name: string | null;
    price: string;
    fee: string;
    total_price: string;
    payment_type: string;
    status: string;
    created_at: string;
    updated_at: string;
    cafe: { id: number; name: string; address: string | null };
    table: { id: number; name: string } | null;
    details: TransactionDetail[];
    is_open_bill?: number | boolean;
    promo_id?: number | null;
}

const props = defineProps<{
    transaction: Transaction;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cashier', href: '/transaction/cashier' },
    { title: `Transaction #${props.transaction.id}`, href: `/transaction/cashier/${props.transaction.id}` },
];

const formatCurrency = (val: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));

const formatDate = (val: string) => {
    const d = new Date(val);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const makeSuccess = () => {
    if (confirm('Setujui transaksi ini? Status akan diubah ke in_order.')) {
        router.patch(`/transaction/cashier/${props.transaction.id}/success`);
    }
};

const makeFailed = () => {
    if (confirm('Tolak transaksi ini? Status akan diubah ke failed.')) {
        router.patch(`/transaction/cashier/${props.transaction.id}/failed`);
    }
};

const hideFailedForOpenBill = computed(() => {
    const trx: Transaction = props.transaction;
    if (!trx) return false;
    if (!trx.is_open_bill) return false;
    if (!trx.details || trx.details.length === 0) return false;
    return trx.details.some((d) => d.status === 'success');
});

const promoCode = ref('');
const applyPromo = () => {
    if (!promoCode.value) return;
    router.post('/transaction/cashier/apply-promo', {
        transaction_id: props.transaction.id,
        promo_code: promoCode.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            promoCode.value = '';
        }
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head :title="`Transaction #${transaction.id}`" />

        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <!-- Header -->
                <div class="flex items-center justify-between"
                    v-if="transaction.status === 'pending' && transaction.payment_type === 'manual'">
                    <Heading variant="small" :title="`Transaction #${transaction.id}`"
                        description="Detail transaksi pending manual." />
                    <div class="flex items-center gap-2">
                        <button v-if="!hideFailedForOpenBill" @click="makeFailed" type="button"
                            class="cursor-pointer inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium shadow-sm transition bg-red-100 text-red-600 hover:bg-red-500 hover:text-white">
                            <XCircle :size="16" /> Failed
                        </button>
                        <button @click="makeSuccess" type="button"
                            class="cursor-pointer inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-green-100 text-green-600 text-sm font-medium shadow-sm hover:bg-green-500 hover:text-white transition">
                            <CheckCircle :size="16" /> Approve
                        </button>
                    </div>
                </div>

                <!-- Transaction Info Card -->
                <div class="rounded-2xl border bg-background shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-4">Informasi
                        Transaksi</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 text-sm">
                        <div>
                            <span class="text-muted-foreground">Customer Name</span>
                            <p class="font-medium">{{ transaction.cust_name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Cafe</span>
                            <p class="font-medium">{{ transaction.cafe?.name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Table</span>
                            <p class="font-medium">{{ transaction.table?.name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Price (Subtotal)</span>
                            <p class="font-medium">{{ formatCurrency(transaction.price) }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Fee</span>
                            <p class="font-medium">{{ formatCurrency(transaction.fee) }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Total Price</span>
                            <p class="font-medium">{{ formatCurrency(transaction.total_price) }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Payment Type</span>
                            <p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                    :class="transaction.payment_type === 'cash' ? 'bg-emerald-100 text-emerald-700' : 'bg-violet-100 text-violet-700'">
                                    {{ transaction.payment_type }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Status</span>
                            <p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 capitalize">
                                    {{ transaction.status }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Tipe Order</span>
                            <p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="transaction.is_open_bill ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'">
                                    {{ transaction.is_open_bill ? '📋 Open Bill' : '🧾 Sekali Bayar' }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Tanggal</span>
                            <p class="font-medium">{{ formatDate(transaction.updated_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Transaction Details Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Transaction
                            Details</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">No</th>
                                <th class="px-6 py-4 text-left font-medium">Menu</th>
                                <th class="px-6 py-4 text-left font-medium">Kategori</th>
                                <th class="px-6 py-4 text-left font-medium">Qty</th>
                                <th class="px-6 py-4 text-left font-medium">Harga</th>
                                <th class="px-6 py-4 text-left font-medium">Subtotal</th>
                                <th class="px-6 py-4 text-left font-medium">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(detail, index) in transaction.details" :key="detail.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium">{{ detail.menu?.name ?? '-' }}</td>
                                <td class="px-6 py-4 text-muted-foreground">{{ detail.menu?.category?.name ?? '-' }}
                                </td>
                                <td class="px-6 py-4">{{ detail.amount }}</td>
                                <td class="px-6 py-4">{{ formatCurrency(detail.price) }}</td>
                                <td class="px-6 py-4 font-medium">{{ formatCurrency(Number(detail.price) *
                                    detail.amount) }}</td>
                                <td class="px-6 py-4 text-muted-foreground">{{ detail.description ?? '-' }}</td>
                            </tr>
                            <tr v-if="transaction.details.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-muted-foreground">
                                    Tidak ada detail transaksi.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="transaction.details.length > 0" class="bg-muted/30">
                            <tr class="border-t">
                                <td colspan="5" class="px-6 py-3 text-right font-medium text-muted-foreground">Subtotal
                                </td>
                                <td colspan="2" class="px-6 py-3 font-semibold">{{ formatCurrency(transaction.price) }}
                                </td>
                            </tr>
                            <tr v-if="transaction.is_open_bill == 1 && transaction.promo_id == null" class="border-t">
                                <td colspan="5" class="px-6 py-3 text-right font-medium text-muted-foreground">Promo Code</td>
                                <td colspan="2" class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <input type="text" v-model="promoCode" placeholder="Masukkan Promo Code" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm w-full max-w-[150px]" />
                                        <button @click="applyPromo" type="button" class="cursor-pointer px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">Apply</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right font-medium text-muted-foreground">Fee</td>
                                <td colspan="2" class="px-6 py-3 font-semibold">{{ formatCurrency(transaction.fee) }}
                                </td>
                            </tr>
                            <tr class="border-t">
                                <td colspan="5" class="px-6 py-3 text-right font-medium text-muted-foreground">Total
                                </td>
                                <td colspan="2" class="px-6 py-3 font-bold">{{ formatCurrency(transaction.total_price)
                                    }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
