<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    data: any;
}>();

const showAddUser = ref(false);
const addUserForm = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
const addUserErrors = ref<any>({});
const addUserLoading = ref(false);

const openAddUser = () => {
    showAddUser.value = true;
    addUserForm.value = { name: '', email: '', password: '', password_confirmation: '' };
    addUserErrors.value = {};
};
const closeAddUser = () => {
    showAddUser.value = false;
};

const submitAddUser = () => {
    addUserLoading.value = true;
    addUserErrors.value = {};
    router.post('/user-management/user', addUserForm.value, {
        onSuccess: () => {
            showAddUser.value = false;
        },
        onError: (errors) => {
            addUserErrors.value = errors;
        },
        onFinish: () => {
            addUserLoading.value = false;
        },
    });
};

const deleteUser = (id: number) => {
    if (confirm('Yakin ingin menghapus user ini?')) {
        router.delete(`/user-management/user/${id}`);
    }
};
</script>

<template>
    <AppLayout>

        <Head title="Manage User" />
        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">
                <div class="flex flex-row items-center justify-between gap-4">
                    <Heading variant="small" title="Manage User" description="Kelola daftar user." />
                    <button @click="openAddUser"
                        class="inline-flex items-center px-4 py-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition">
                        Tambah User
                    </button>
                </div>

                <!-- Add User Section -->
                <div v-if="showAddUser"
                    class="rounded-2xl border bg-background shadow-sm p-6 space-y-4 mx-auto">
                    <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Tambah User</h3>
                    <form @submit.prevent="submitAddUser" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1">Nama</label>
                            <input v-model="addUserForm.name" type="text"
                                class="w-full px-3 py-2 rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring text-sm" />
                            <div v-if="addUserErrors.name" class="text-xs text-red-500 mt-1">{{ addUserErrors.name }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Email</label>
                            <input v-model="addUserForm.email" type="email"
                                class="w-full px-3 py-2 rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring text-sm" />
                            <div v-if="addUserErrors.email" class="text-xs text-red-500 mt-1">{{ addUserErrors.email }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Password</label>
                            <input v-model="addUserForm.password" type="password"
                                class="w-full px-3 py-2 rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring text-sm" />
                            <div v-if="addUserErrors.password" class="text-xs text-red-500 mt-1">{{
                                addUserErrors.password }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Konfirmasi Password</label>
                            <input v-model="addUserForm.password_confirmation" type="password"
                                class="w-full px-3 py-2 rounded-xl border bg-background focus:outline-none focus:ring-2 focus:ring-ring text-sm" />
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="closeAddUser"
                                class="px-4 py-2 rounded-xl bg-muted text-sm font-medium text-muted-foreground hover:opacity-80 transition">Batal</button>
                            <button type="submit" :disabled="addUserLoading"
                                class="px-4 py-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-sm hover:opacity-90 transition disabled:opacity-60">Simpan</button>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <div class="rounded-2xl border bg-background shadow-sm overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-muted-foreground">
                                <th class="px-6 py-4 text-left font-medium">ID</th>
                                <th class="px-6 py-4 text-left font-medium">Nama</th>
                                <th class="px-6 py-4 text-left font-medium">Email</th>
                                <th class="px-6 py-4 text-left font-medium">Roles</th>
                                <th class="px-6 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in props.data.data" :key="user.id"
                                class="border-t hover:bg-muted/40 transition">
                                <td class="px-6 py-4">{{ user.id }}</td>
                                <td class="px-6 py-4 font-medium">{{ user.name }}</td>
                                <td class="px-6 py-4">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <div v-if="user.roles && user.roles.length > 0" class="flex flex-wrap gap-1.5">
                                        <span v-for="role in user.roles" :key="role"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                            {{ role.name }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="deleteUser(user.id)" type="button"
                                        class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-100 text-red-600 text-xs font-medium hover:bg-red-600 hover:text-white transition"
                                        title="Hapus User">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="props.data.data.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-muted-foreground">
                                    Belum ada user.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="p-4 bg-background">
                        <Pagination :links="props.data.links" />
                    </div>
                </div>


            </div>
        </div>
    </AppLayout>
</template>
