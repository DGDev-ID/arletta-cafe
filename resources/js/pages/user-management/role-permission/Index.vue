<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { type BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';

const props = defineProps<{
    data: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Role Permission', href: '/user-management/role-permission' },
];

const selectedRoleId = ref<number>(props.data.roles[0]?.id || null);
const checkedPermissions = ref<string[]>(props.data.rolePermissions[selectedRoleId.value] || []);

function onRoleChange() {
    checkedPermissions.value = props.data.rolePermissions[selectedRoleId.value] || [];
}

const allPermissionNames = computed(() => props.data.permissions.map(p => p.name));
const isAllChecked = computed(() =>
    allPermissionNames.value.length > 0 && allPermissionNames.value.every(p => checkedPermissions.value.includes(p))
);

function toggleCheckAll() {
    if (isAllChecked.value) {
        checkedPermissions.value = [];
    } else {
        checkedPermissions.value = [...allPermissionNames.value];
    }
}

function togglePermissionRow(permissionName: string) {
    if (checkedPermissions.value.includes(permissionName)) {
        checkedPermissions.value = checkedPermissions.value.filter(p => p !== permissionName);
    } else {
        checkedPermissions.value.push(permissionName);
    }
}

function savePermissions() {
    router.post(
        '/user-management/role-permission/update',
        {
            role_id: selectedRoleId.value,
            permissions: checkedPermissions.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Optionally show notification
            },
        }
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Role Permission Management" />
        
        <div class="min-h-screen bg-muted/40 py-10">
            <div class="max-w-7xl mx-auto px-6 space-y-8">

                <div class="flex items-center justify-between">
                    <Heading variant="small" title="Role Permission Management"
                        description="Kelola akses permission untuk setiap role." />
                    <div class="mb-4">
                        <select v-model="selectedRoleId" @change="onRoleChange" class="ml-2 border rounded px-2 py-1">
                            <option v-for="role in props.data.roles" :key="role.id" :value="role.id">{{ role.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="rounded-2xl border bg-background shadow-sm p-6">
                    <form @submit.prevent="savePermissions" class="space-y-6">

                        <div class="overflow-x-auto">
                            <table class="min-w-full border rounded bg-white">
                                <thead>
                                    <tr>
                                        <th class="text-left p-2 border-b">Permission</th>
                                        <th class="text-center p-2 border-b">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <span>Akses</span>
                                                <input type="checkbox" class="checkbox-black" :checked="isAllChecked" @change="toggleCheckAll"
                                                    title="Check Semua" />
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="permission in props.data.permissions" :key="permission.id"
                                        @click="togglePermissionRow(permission.name)"
                                        class="cursor-pointer hover:bg-blue-50 transition select-none">
                                        <td class="p-2 border-b">{{ permission.name }}</td>
                                        <td class="p-2 border-b text-center">
                                            <input type="checkbox" class="accent-primary" :value="permission.name" v-model="checkedPermissions"
                                                @click.stop />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-right">
                            <button type="submit"
                                class="px-4 py-2 bg-primary text-white rounded hover:opacity-90">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
