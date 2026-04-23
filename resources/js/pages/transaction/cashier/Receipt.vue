<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

interface TransactionDetail {
    id: number;
    menu: { id: number; name: string } | null;
    amount: number;
    price: string;
    description: string | null;
}

interface Transaction {
    id: number;
    cust_name: string | null;
    price: string;
    fee: string;
    total_price: string;
    payment_type: string;
    status: string;
    updated_at: string;
    cafe: { id: number; name: string; address: string | null };
    table: { id: number; name: string } | null;
    details: TransactionDetail[];
}

const props = defineProps<{
    transaction: Transaction;
}>();

const formatCurrency = (val: string | number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));

const formatDate = (val: string) => {
    const d = new Date(val);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    setTimeout(() => window.print(), 500);
});
</script>

<template>
    <Head :title="`Struk #${transaction.id}`" />

    <div class="receipt-container">
        <div class="receipt">
            <!-- Header -->
            <div class="text-center mb-4">
                <h1 class="text-lg font-bold">{{ transaction.cafe.name }}</h1>
                <p v-if="transaction.cafe.address" class="text-xs text-gray-500">{{ transaction.cafe.address }}</p>
                <p class="text-xs text-gray-500">main@arlettaluxury.com</p>
                <p class="text-xs text-gray-500">085742089646</p>
            </div>

            <div class="border-t border-dashed border-gray-400 my-3"></div>

            <!-- Info -->
            <div class="text-xs space-y-1 mb-3">
                <div class="flex justify-between">
                    <span>No. Transaksi</span>
                    <span class="font-medium">#{{ transaction.id }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Tanggal</span>
                    <span class="font-medium">{{ formatDate(transaction.updated_at) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Customer</span>
                    <span class="font-medium">{{ transaction.cust_name ?? '-' }}</span>
                </div>
                <div v-if="transaction.table" class="flex justify-between">
                    <span>Table</span>
                    <span class="font-medium">{{ transaction.table.name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Pembayaran</span>
                    <span class="font-medium capitalize">{{ transaction.payment_type }}</span>
                </div>
            </div>

            <div class="border-t border-dashed border-gray-400 my-3"></div>

            <!-- Items -->
            <div class="text-xs space-y-2 mb-3">
                <div v-for="detail in transaction.details" :key="detail.id">
                    <div class="flex justify-between">
                        <span>{{ detail.menu?.name ?? '-' }}</span>
                        <span>{{ formatCurrency(Number(detail.price) * detail.amount) }}</span>
                    </div>
                    <div class="text-gray-500 pl-2">
                        {{ detail.amount }} x {{ formatCurrency(detail.price) }}
                    </div>
                    <div v-if="detail.description" class="text-gray-400 pl-2 italic">
                        {{ detail.description }}
                    </div>
                </div>
            </div>

            <div class="border-t border-dashed border-gray-400 my-3"></div>

            <!-- Totals -->
            <div class="text-xs space-y-1">
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span>{{ formatCurrency(transaction.price) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Fee</span>
                    <span>{{ formatCurrency(transaction.fee) }}</span>
                </div>
                <div class="flex justify-between font-bold text-sm mt-1 pt-1 border-t border-dashed border-gray-400">
                    <span>Total</span>
                    <span>{{ formatCurrency(transaction.total_price) }}</span>
                </div>
            </div>

            <div class="border-t border-dashed border-gray-400 my-3"></div>

            <!-- Footer -->
            <div class="text-center text-xs text-gray-500">
                <p>Terima kasih atas kunjungan Anda!</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.receipt-container {
    display: flex;
    justify-content: center;
    padding: 20px;
    background: #f5f5f5;
    min-height: 100vh;
}

.receipt {
    width: 48mm;
    background: white;
    padding: 5mm;
    font-family: 'Courier New', monospace;
}

@media print {
    @page {
        size: 58mm 210mm;
        margin: 0;
    }

    body {
        margin: 0;
        padding: 0;
    }

    .receipt-container {
        padding: 0;
        background: white;
        min-height: auto;
    }

    .receipt {
        width: 48mm;
        padding: 5mm;
        box-shadow: none;
    }
}
</style>
