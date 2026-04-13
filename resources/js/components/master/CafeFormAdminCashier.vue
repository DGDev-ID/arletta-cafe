<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface UserOption {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    admins: UserOption[];
    cashiers: UserOption[];
    selectedAdminIds: number[];
    selectedCashierIds: number[];
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:selectedAdminIds': [ids: number[]];
    'update:selectedCashierIds': [ids: number[]];
}>();

// ── Admin ──────────────────────────────────────────────────────────────────
const adminSearch = ref('');
const filteredAdmins = computed(() => {
    const q = adminSearch.value.toLowerCase().trim();
    if (!q) return props.admins;
    return props.admins.filter(
        (u) => u.email.toLowerCase().includes(q) || u.name.toLowerCase().includes(q),
    );
});

const toggleAdmin = (id: number) => {
    const ids = [...props.selectedAdminIds];
    const idx = ids.indexOf(id);
    if (idx === -1) ids.push(id);
    else ids.splice(idx, 1);
    emit('update:selectedAdminIds', ids);
};

const removeAdmin = (id: number) => {
    emit('update:selectedAdminIds', props.selectedAdminIds.filter((i) => i !== id));
};

const selectedAdminUsers = computed(() =>
    props.admins.filter((u) => props.selectedAdminIds.includes(u.id)),
);

// ── Cashier ────────────────────────────────────────────────────────────────
const cashierSearch = ref('');
const filteredCashiers = computed(() => {
    const q = cashierSearch.value.toLowerCase().trim();
    if (!q) return props.cashiers;
    return props.cashiers.filter(
        (u) => u.email.toLowerCase().includes(q) || u.name.toLowerCase().includes(q),
    );
});

const toggleCashier = (id: number) => {
    const ids = [...props.selectedCashierIds];
    const idx = ids.indexOf(id);
    if (idx === -1) ids.push(id);
    else ids.splice(idx, 1);
    emit('update:selectedCashierIds', ids);
};

const removeCashier = (id: number) => {
    emit('update:selectedCashierIds', props.selectedCashierIds.filter((i) => i !== id));
};

const selectedCashierUsers = computed(() =>
    props.cashiers.filter((u) => props.selectedCashierIds.includes(u.id)),
);
</script>

<template>
    <div class="space-y-8">

        <!-- ── Pilih Admin ────────────────────────────────────── -->
        <div class="rounded-2xl border bg-background shadow-sm p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold">Pilih Admin</h2>
                <p class="text-sm text-muted-foreground">Tentukan user dengan role Admin yang mengelola cafe ini.</p>
            </div>

            <!-- Selected tags -->
            <div v-if="selectedAdminUsers.length" class="flex flex-wrap gap-2">
                <span
                    v-for="user in selectedAdminUsers"
                    :key="user.id"
                    class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 text-primary px-3 py-1 text-xs font-medium"
                >
                    {{ user.name }}
                    <button type="button" @click="removeAdmin(user.id)" class="hover:text-red-500 transition">
                        <X :size="12" />
                    </button>
                </span>
            </div>

            <!-- Search -->
            <div class="relative">
                <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="adminSearch"
                    type="text"
                    placeholder="Cari berdasarkan email atau nama..."
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                />
            </div>

            <!-- List -->
            <div v-if="admins.length === 0" class="text-sm text-muted-foreground italic">
                Belum ada user dengan role Admin.
            </div>
            <div v-else class="max-h-52 overflow-y-auto rounded-lg border divide-y">
                <label
                    v-for="user in filteredAdmins"
                    :key="user.id"
                    class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition hover:bg-muted/40"
                    :class="{ 'bg-primary/5': selectedAdminIds.includes(user.id) }"
                >
                    <input
                        type="checkbox"
                        :checked="selectedAdminIds.includes(user.id)"
                        @change="toggleAdmin(user.id)"
                        class="accent-primary shrink-0"
                    />
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate">{{ user.name }}</p>
                        <p class="text-xs text-muted-foreground truncate">{{ user.email }}</p>
                    </div>
                </label>
                <div v-if="filteredAdmins.length === 0" class="px-4 py-3 text-sm text-muted-foreground text-center">
                    Tidak ditemukan.
                </div>
            </div>

            <InputError :message="errors?.admin_ids" />
        </div>

        <!-- ── Pilih Cashier ──────────────────────────────────── -->
        <div class="rounded-2xl border bg-background shadow-sm p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold">Pilih Kasir</h2>
                <p class="text-sm text-muted-foreground">Tentukan user dengan role Cashier yang bertugas di cafe ini.</p>
            </div>

            <!-- Selected tags -->
            <div v-if="selectedCashierUsers.length" class="flex flex-wrap gap-2">
                <span
                    v-for="user in selectedCashierUsers"
                    :key="user.id"
                    class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 text-primary px-3 py-1 text-xs font-medium"
                >
                    {{ user.name }}
                    <button type="button" @click="removeCashier(user.id)" class="hover:text-red-500 transition">
                        <X :size="12" />
                    </button>
                </span>
            </div>

            <!-- Search -->
            <div class="relative">
                <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="cashierSearch"
                    type="text"
                    placeholder="Cari berdasarkan email atau nama..."
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                />
            </div>

            <!-- List -->
            <div v-if="cashiers.length === 0" class="text-sm text-muted-foreground italic">
                Belum ada user dengan role Cashier.
            </div>
            <div v-else class="max-h-52 overflow-y-auto rounded-lg border divide-y">
                <label
                    v-for="user in filteredCashiers"
                    :key="user.id"
                    class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition hover:bg-muted/40"
                    :class="{ 'bg-primary/5': selectedCashierIds.includes(user.id) }"
                >
                    <input
                        type="checkbox"
                        :checked="selectedCashierIds.includes(user.id)"
                        @change="toggleCashier(user.id)"
                        class="accent-primary shrink-0"
                    />
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate">{{ user.name }}</p>
                        <p class="text-xs text-muted-foreground truncate">{{ user.email }}</p>
                    </div>
                </label>
                <div v-if="filteredCashiers.length === 0" class="px-4 py-3 text-sm text-muted-foreground text-center">
                    Tidak ditemukan.
                </div>
            </div>

            <InputError :message="errors?.cashier_ids" />
        </div>

    </div>
</template>
