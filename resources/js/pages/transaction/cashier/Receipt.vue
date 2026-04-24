<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';

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

const receiptText = computed(() => {
    const trx = props.transaction;

    const cleanNumber = (val: number) =>
        new Intl.NumberFormat('id-ID')
            .format(val)
            .replace(/[^\d]/g, '');

    const fmtDate = (val: string) => {
        const d = new Date(val);
        return d.toLocaleString('id-ID');
    };

    const WIDTH = 32;
    const line = '-'.repeat(WIDTH) + '\n';

    const padRight = (left: string, right: string) => {
        const space = WIDTH - (left.length + right.length);
        return left + ' '.repeat(space > 0 ? space : 1) + right + '\n';
    };

    const alignCenter = (text: string) => {
        if (!text) return '\n';
        const lines = text.split('\n');
        return lines.map(l => {
            const space = WIDTH - l.length;
            if (space <= 0) return l;
            const leftSpace = Math.floor(space / 2);
            return ' '.repeat(leftSpace) + l;
        }).join('\n') + '\n';
    };

    let str = '';

    // HEADER
    str += alignCenter(trx.cafe.name || 'CAFE');
    if (trx.cafe.address) str += alignCenter(trx.cafe.address);
    if (trx.cafe.phone_number) str += alignCenter(trx.cafe.phone_number);
    str += line;

    // INFO
    str += padRight('No', `#${trx.id}`);
    str += padRight('Tgl', fmtDate(trx.updated_at));
    str += padRight('Cust', trx.cust_name || '-');
    if (trx.table) str += padRight('Table', trx.table.name);
    str += padRight('Pay', trx.payment_type);
    str += line;

    // ITEMS
    trx.details.forEach((d) => {
        const name = (d.menu?.name || '-').substring(0, WIDTH);
        str += name + '\n';

        const qtyPrice = `${d.amount}x${cleanNumber(Number(d.price))}`;
        const subtotal = cleanNumber(Number(d.price) * d.amount);

        str += padRight(qtyPrice, subtotal);

        if (d.description) {
            str += ' ' + d.description + '\n';
        }
    });

    str += line;

    // TOTAL
    str += padRight('Subtotal', cleanNumber(Number(trx.price)));
    str += padRight('Fee', cleanNumber(Number(trx.fee)));

    str += line;
    str += padRight('TOTAL', cleanNumber(Number(trx.total_price)));
    str += line;

    // FOOTER
    str += alignCenter('Terima kasih');
    str += '\n\n\n';

    return str;
});

onMounted(() => {
    setTimeout(() => window.print(), 500);
});
</script>

<template>
    <Head :title="`Struk #${transaction.id}`" />

    <div class="receipt-container">
        <div class="receipt">
            <pre class="receipt-text">{{ receiptText }}</pre>
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
    background: white;
    padding: 20px;
    margin: 0 auto;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.receipt-text {
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.2;
    color: #000;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
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
        padding: 0;
        box-shadow: none;
        width: 100%;
        max-width: 100%;
    }

    .receipt-text {
        font-size: 12px;
        white-space: pre-wrap;
    }
}
</style>

