<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    Copy,
    Database,
    Download,
    ExternalLink,
    HardDriveDownload,
    Loader2,
    RefreshCw,
    Search,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import { onMounted, onUnmounted, ref, watch } from 'vue';

type BackupStatus = 'pending' | 'processing' | 'completed' | 'failed';

interface ActiveBackup {
    id: number;
    name: string;
    status: BackupStatus;
    progress: number;
    progress_message: string | null;
    error_message?: string | null;
    created_at?: string | null;
}

interface BackupItem {
    id: number;
    name: string;
    url: string | null;
    status: BackupStatus;
    progress: number;
    progress_message: string | null;
    error_message: string | null;
    created_at: string | null;
    created_at_formatted: string | null;
    created_at_diff: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    backups: Paginator<BackupItem>;
    filters: { search: string };
    activeBackup?: ActiveBackup | null;
}>();

const notyf = new Notyf({
    duration: 3500,
    position: { x: 'right', y: 'bottom' },
    ripple: true,
    dismissible: true,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Backup Database', href: '/management/backup-databases' },
];

const search = ref(props.filters.search ?? '');
const isRefreshing = ref(false);
const isDispatching = ref(false);
const isDeleting = ref(false);

const showBackupConfirmDialog = ref(false);
const showDeleteDialog = ref(false);
const backupToDelete = ref<BackupItem | null>(null);
const copiedId = ref<number | null>(null);

const currentActive = ref<ActiveBackup | null>(props.activeBackup ?? null);
let pollTimer: ReturnType<typeof setInterval> | null = null;

const stopPolling = () => {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const fetchStatus = async () => {
    try {
        const res = await fetch(route('management.backup-databases.status'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!res.ok) return;

        const data = await res.json();
        const prevActiveId = currentActive.value?.id;
        currentActive.value = data.active ?? null;

        if (!data.active) {
            stopPolling();
            if (prevActiveId && data.latest && data.latest.id === prevActiveId) {
                if (data.latest.status === 'completed') {
                    notyf.success('Backup database selesai!');
                } else if (data.latest.status === 'failed') {
                    notyf.error('Backup database gagal: ' + (data.latest.error_message || 'Terjadi kesalahan.'));
                }
            }
            router.reload({ only: ['backups', 'activeBackup'] });
        }
    } catch {
        // Ignore transient network errors during polling
    }
};

const startPolling = () => {
    if (pollTimer !== null) return;
    pollTimer = setInterval(fetchStatus, 2000);
};

watch(
    () => props.activeBackup,
    (val) => {
        currentActive.value = val ?? null;
        if (val) {
            startPolling();
        } else {
            stopPolling();
        }
    },
);

onMounted(() => {
    if (currentActive.value) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

const applyFilter = useDebounceFn(() => {
    router.get(
        route('management.backup-databases.index'),
        { search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch(search, applyFilter);

const refreshData = () => {
    isRefreshing.value = true;
    router.reload({
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const handleConfirmBackup = () => {
    isDispatching.value = true;
    router.post(
        route('management.backup-databases.store'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showBackupConfirmDialog.value = false;
                startPolling();
            },
            onError: () => {
                notyf.error('Gagal mendispatch Job Backup Database.');
            },
            onFinish: () => {
                isDispatching.value = false;
            },
        },
    );
};

const confirmDelete = (backup: BackupItem) => {
    backupToDelete.value = backup;
    showDeleteDialog.value = true;
};

const handleDelete = () => {
    if (!backupToDelete.value) return;

    isDeleting.value = true;
    router.delete(route('management.backup-databases.destroy', backupToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            backupToDelete.value = null;
        },
        onError: () => {
            notyf.error('Gagal menghapus data backup database.');
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const copyUrl = async (backup: BackupItem) => {
    if (!backup.url) return;
    try {
        await navigator.clipboard.writeText(backup.url);
        copiedId.value = backup.id;
        notyf.success('URL backup berhasil disalin ke clipboard!');
        setTimeout(() => {
            if (copiedId.value === backup.id) {
                copiedId.value = null;
            }
        }, 2000);
    } catch {
        notyf.error('Gagal menyalin URL.');
    }
};
</script>

<template>
    <Head title="Backup Database" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-muted/40 py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Backup Database"
                        :description="`Kelola riwayat backup database PostgreSQL dan pencadangan S3/Cloud Storage. Total: ${backups.total}`"
                    />

                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="cursor-pointer gap-2"
                            :disabled="isRefreshing"
                            @click="refreshData"
                        >
                            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                            <span>Segarkan</span>
                        </Button>

                        <Button
                            class="cursor-pointer gap-2 shadow-sm"
                            :disabled="Boolean(currentActive)"
                            @click="showBackupConfirmDialog = true"
                        >
                            <Loader2 v-if="currentActive" class="h-4 w-4 animate-spin" />
                            <HardDriveDownload v-else class="h-4 w-4" />
                            <span>{{ currentActive ? 'Sedang Memproses...' : 'Backup Database' }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Live Progress Banner -->
                <div
                    v-if="currentActive"
                    class="rounded-xl border border-primary/30 bg-primary/5 p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Loader2 class="h-5 w-5 animate-spin" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">
                                    Backup Database Sedang Berjalan
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ currentActive.progress_message || 'Memproses backup database...' }}
                                </p>
                            </div>
                        </div>
                        <span class="rounded-full bg-primary/10 px-3 py-1 font-mono text-xs font-bold text-primary">
                            {{ currentActive.progress }}%
                        </span>
                    </div>

                    <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-500 ease-out"
                            :style="{ width: `${currentActive.progress}%` }"
                        />
                    </div>
                </div>

                <!-- Info Box -->
                <div class="rounded-xl border bg-background p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Database class="h-5 w-5" />
                        </div>
                        <div class="space-y-1 text-sm">
                            <p class="font-medium text-foreground">Pencadangan Database Otomatis & Terjadwal</p>
                            <p class="text-xs text-muted-foreground sm:text-sm">
                                Proses backup mengeksekusi perintah <code>pg_dump</code> versi 18 untuk mendump database PostgreSQL, kemudian mengunggah hasilnya ke cloud storage melalui <code>S3Helper</code>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Bar -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full max-w-sm">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            placeholder="Cari file backup..."
                            class="pl-9"
                        />
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Menampilkan {{ backups.from ?? 0 }} - {{ backups.to ?? 0 }} dari {{ backups.total }} backup
                    </div>
                </div>

                <!-- Table Card -->
                <div class="overflow-hidden rounded-xl border bg-background shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                                <tr>
                                    <th class="w-14 px-4 py-3.5 text-center">#</th>
                                    <th class="px-4 py-3.5">Nama File Backup</th>
                                    <th class="px-4 py-3.5">Status</th>
                                    <th class="px-4 py-3.5">URL Storage</th>
                                    <th class="px-4 py-3.5">Waktu Backup</th>
                                    <th class="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="backups.data.length === 0">
                                    <td colspan="6" class="py-12 text-center">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <div class="rounded-full bg-muted p-3 text-muted-foreground">
                                                <Database class="h-6 w-6" />
                                            </div>
                                            <div class="space-y-1 text-center">
                                                <p class="font-medium text-foreground">Belum ada riwayat backup database</p>
                                                <p class="text-xs text-muted-foreground">
                                                    Klik tombol "Backup Database" di atas untuk membuat cadangan pertama Anda.
                                                </p>
                                            </div>
                                            <Button
                                                size="sm"
                                                class="mt-2 cursor-pointer gap-2"
                                                :disabled="Boolean(currentActive)"
                                                @click="showBackupConfirmDialog = true"
                                            >
                                                <HardDriveDownload class="h-4 w-4" />
                                                <span>Backup Database Sekarang</span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-for="(backup, idx) in backups.data"
                                    :key="backup.id"
                                    class="transition-colors hover:bg-muted/40"
                                >
                                    <td class="px-4 py-3.5 text-center font-mono text-xs text-muted-foreground">
                                        {{ (backups.current_page - 1) * 10 + idx + 1 }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                <Database class="h-4 w-4" />
                                            </div>
                                            <div>
                                                <p class="font-mono text-xs font-semibold text-foreground sm:text-sm">
                                                    {{ backup.name }}
                                                </p>
                                                <span class="inline-flex items-center rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                                    PostgreSQL Dump (.sql)
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span
                                            v-if="backup.status === 'pending' || backup.status === 'processing'"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                                        >
                                            <Loader2 class="h-3 w-3 animate-spin" />
                                            <span>Proses {{ backup.progress }}%</span>
                                        </span>
                                        <span
                                            v-else-if="backup.status === 'failed'"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-300"
                                            :title="backup.error_message ?? 'Backup gagal'"
                                        >
                                            <XCircle class="h-3 w-3" />
                                            <span>Gagal</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                                        >
                                            <CheckCircle2 class="h-3 w-3" />
                                            <span>Selesai</span>
                                        </span>
                                    </td>
                                    <td class="max-w-xs px-4 py-3.5">
                                        <div v-if="backup.status === 'completed' && backup.url" class="flex items-center gap-1.5">
                                            <a
                                                :href="backup.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex max-w-[220px] items-center gap-1 truncate text-xs text-primary hover:underline"
                                                :title="backup.url"
                                            >
                                                <span class="truncate">{{ backup.url }}</span>
                                                <ExternalLink class="h-3 w-3 shrink-0" />
                                            </a>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-7 w-7 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                                                title="Salin URL"
                                                @click="copyUrl(backup)"
                                            >
                                                <Check v-if="copiedId === backup.id" class="h-3.5 w-3.5 text-green-600" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                        <span v-else-if="backup.status === 'failed'" class="text-xs text-destructive line-clamp-1" :title="backup.error_message ?? ''">
                                            {{ backup.error_message || 'Gagal diproses' }}
                                        </span>
                                        <span v-else class="text-xs italic text-muted-foreground">
                                            {{ backup.progress_message || 'Sedang diproses...' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="space-y-0.5">
                                            <p class="text-xs font-medium text-foreground sm:text-sm">
                                                {{ backup.created_at_formatted ?? '-' }}
                                            </p>
                                            <p class="text-[11px] text-muted-foreground">
                                                {{ backup.created_at_diff }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                v-if="backup.status === 'completed' && backup.url"
                                                variant="outline"
                                                size="sm"
                                                class="h-8 cursor-pointer gap-1.5 text-xs"
                                                as-child
                                            >
                                                <a
                                                    :href="route('management.backup-databases.download', backup.id)"
                                                    target="_blank"
                                                    download
                                                >
                                                    <Download class="h-3.5 w-3.5" />
                                                    <span class="hidden sm:inline">Download</span>
                                                </a>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 cursor-pointer text-destructive hover:bg-destructive/10"
                                                title="Hapus riwayat"
                                                @click="confirmDelete(backup)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="backups.total > 0" class="border-t p-4">
                        <Pagination :links="backups.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Backup Database -->
        <Dialog v-model:open="showBackupConfirmDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <HardDriveDownload class="h-5 w-5" />
                    </div>
                    <DialogTitle>Konfirmasi Backup Database</DialogTitle>
                    <DialogDescription class="pt-2 text-sm text-muted-foreground">
                        Apakah Anda yakin ingin melakukan backup database sekarang?
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 rounded-lg border border-border/80 bg-muted/30 p-3.5 text-xs text-muted-foreground">
                    <div class="flex items-start gap-2">
                        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                        <div>
                            Job <strong>BackupDatabase</strong> akan di-dispatch ke antrian latar belakang untuk mengeksekusi <code>pg_dump</code> dan mengunggah hasilnya ke cloud storage melalui <code>S3Helper</code>.
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-4 gap-2 sm:gap-0">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="isDispatching" class="cursor-pointer">
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        class="cursor-pointer gap-2"
                        :disabled="isDispatching"
                        @click="handleConfirmBackup"
                    >
                        <Loader2 v-if="isDispatching" class="h-4 w-4 animate-spin" />
                        <span>{{ isDispatching ? 'Memproses...' : 'Oke' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Konfirmasi Hapus Riwayat Backup -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <Trash2 class="h-5 w-5" />
                    </div>
                    <DialogTitle>Hapus Data Backup</DialogTitle>
                    <DialogDescription class="pt-2 text-sm text-muted-foreground">
                        Apakah Anda yakin ingin menghapus catatan backup:
                        <strong class="mt-1 block font-mono text-foreground">{{ backupToDelete?.name }}</strong>
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4 gap-2 sm:gap-0">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="isDeleting" class="cursor-pointer">
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        class="cursor-pointer gap-2"
                        :disabled="isDeleting"
                        @click="handleDelete"
                    >
                        <Loader2 v-if="isDeleting" class="h-4 w-4 animate-spin" />
                        <span>{{ isDeleting ? 'Menghapus...' : 'Hapus' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
