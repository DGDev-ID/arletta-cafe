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
    cafe: { id: number; name: string; address: string | null; phone_number?: string | null };
    table: { id: number; name: string } | null;
    details: TransactionDetail[];
}

const props = defineProps<{
    transaction: Transaction;
}>();

const cleanNumber = (val: number) =>
    new Intl.NumberFormat('id-ID')
        .format(val)
        .replace(/[^\d]/g, '');

const fmtDate = (val: string) => {
    const d = new Date(val);
    return d.toLocaleString('id-ID');
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
                <h1 class="receipt-title">{{ transaction.cafe.name || 'CAFE' }}</h1>
                <p v-if="transaction.cafe.address" class="receipt-sub">{{ transaction.cafe.address }}</p>
                <p v-if="transaction.cafe.phone_number" class="receipt-sub">{{ transaction.cafe.phone_number }}</p>
            </div>

            <div class="divider"></div>

            <!-- Info -->
            <div class="receipt-info">
                <div class="receipt-row">
                    <span>No</span>
                    <span>#{{ transaction.id }}</span>
                </div>
                <div class="receipt-row">
                    <span>Tgl</span>
                    <span>{{ fmtDate(transaction.updated_at) }}</span>
                </div>
                <div class="receipt-row">
                    <span>Cust</span>
                    <span>{{ transaction.cust_name ?? '-' }}</span>
                </div>
                <div v-if="transaction.table" class="receipt-row">
                    <span>Table</span>
                    <span>{{ transaction.table.name }}</span>
                </div>
                <div class="receipt-row">
                    <span>Pay</span>
                    <span style="text-transform:capitalize">{{ transaction.payment_type }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Items -->
            <div class="receipt-items">
                <div v-for="detail in transaction.details" :key="detail.id" class="receipt-item">
                    <div class="receipt-row-name">
                        {{ detail.menu?.name ?? '-' }}
                    </div>
                    <div class="receipt-row">
                        <span>{{ detail.amount }}x{{ cleanNumber(Number(detail.price)) }}</span>
                        <span>{{ cleanNumber(Number(detail.price) * detail.amount) }}</span>
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
                    <span>{{ cleanNumber(Number(transaction.price)) }}</span>
                </div>
                <div class="receipt-row">
                    <span>Fee</span>
                    <span>{{ cleanNumber(Number(transaction.fee)) }}</span>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="receipt-info">
                <div class="receipt-row receipt-total">
                    <span>TOTAL</span>
                    <span>{{ cleanNumber(Number(transaction.total_price)) }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Footer -->
            <div class="receipt-footer mt-2">
                <p>Terima kasih</p>
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
    width: 58mm; /* Standard thermal width */
    max-width: 100%;
    background: white;
    padding: 2mm;
    margin: 0 auto;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.2;
    color: #000;
    -webkit-font-smoothing: none;
}

.text-center {
    text-align: center;
}

.mt-2 {
    margin-top: 2mm;
}

.mb-1 {
    margin-bottom: 1mm;
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

.receipt-row-name {
    word-break: break-word;
    margin-bottom: 0.5mm;
}

.receipt-items {
    margin-bottom: 1.5mm;
}

.receipt-item {
    margin-bottom: 1.5mm;
}

.receipt-note {
    padding-left: 2mm;
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
        size: 58mm auto;
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
        width: 100%;
        padding: 2mm;
        box-shadow: none;
    }
}
</style>

