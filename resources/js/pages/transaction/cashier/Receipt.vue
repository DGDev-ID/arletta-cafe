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
            <div class="text-center mb-1">
                <h1 class="receipt-title">{{ transaction.cafe.name }}</h1>
                <p v-if="transaction.cafe.address" class="receipt-sub">{{ transaction.cafe.address }}</p>
                <p class="receipt-sub">main@arlettaluxury.com</p>
                <p class="receipt-sub">085742089646</p>
            </div>

            <div class="divider"></div>

            <!-- Info -->
            <div class="receipt-info">
                <div class="receipt-row">
                    <span>No. Transaksi</span>
                    <span class="font-medium">#{{ transaction.id }}</span>
                </div>
                <div class="receipt-row">
                    <span>Tanggal</span>
                    <span class="font-medium">{{ formatDate(transaction.updated_at) }}</span>
                </div>
                <div class="receipt-row">
                    <span>Customer</span>
                    <span class="font-medium">{{ transaction.cust_name ?? '-' }}</span>
                </div>
                <div v-if="transaction.table" class="receipt-row">
                    <span>Table</span>
                    <span class="font-medium">{{ transaction.table.name }}</span>
                </div>
                <div class="receipt-row">
                    <span>Pembayaran</span>
                    <span class="font-medium" style="text-transform:capitalize">{{ transaction.payment_type }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Items -->
            <div class="receipt-items">
                <div v-for="detail in transaction.details" :key="detail.id" class="receipt-item">
                    <div class="receipt-row">
                        <span>{{ detail.menu?.name ?? '-' }}</span>
                        <span>{{ formatCurrency(Number(detail.price) * detail.amount) }}</span>
                    </div>
                    <div class="receipt-detail">
                        {{ detail.amount }} x {{ formatCurrency(detail.price) }}
                    </div>
                    <div v-if="detail.description" class="receipt-note">
                        {{ detail.description }}
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Totals -->
            <div class="receipt-info">
                <div class="receipt-row">
                    <span>Subtotal</span>
                    <span>{{ formatCurrency(transaction.price) }}</span>
                </div>
                <div class="receipt-row">
                    <span>Fee</span>
                    <span>{{ formatCurrency(transaction.fee) }}</span>
                </div>
                <div class="divider"></div>
                <div class="receipt-row receipt-total">
                    <span>Total</span>
                    <span>{{ formatCurrency(transaction.total_price) }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Footer -->
            <div class="receipt-footer">
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
    width: 44mm;
    background: white;
    padding: 2mm;
    margin: 0 auto;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.2;
    color: #000;
    -webkit-font-smoothing: none;
}

.receipt-title {
    font-size: 14px;
    font-weight: bold;
    margin: 0;
}

.receipt-sub {
    font-size: 11px;
    margin: 0;
}

.divider {
    border-top: 1px dashed #000;
    margin: 2mm 0;
}

.receipt-info {
    margin-bottom: 1.5mm;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    gap: 1mm;
    word-break: break-word;
}

.receipt-items {
    margin-bottom: 1.5mm;
}

.receipt-item {
    margin-bottom: 1.5mm;
}

.receipt-detail {
    padding-left: 1mm;
    font-size: 11px;
}

.receipt-note {
    padding-left: 1mm;
    font-style: italic;
    font-size: 11px;
}

.receipt-total {
    font-weight: bold;
    font-size: 13px;
}

.receipt-footer {
    text-align: center;
    font-size: 11px;
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
        width: 44mm;
        padding: 2mm;
        box-shadow: none;
    }
}
</style>

