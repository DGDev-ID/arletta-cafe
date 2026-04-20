<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem
} from '@/components/ui/sidebar';

import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Component } from 'vue';

interface NavItem {
    title: string;
    href: string;
    icon: Component;
    permission?: string;
}

const props = defineProps<{
    items: NavItem[];
    title: string;
}>();

const page = usePage<SharedData>();

const userRole = computed(() => page.props.roles?.[0]);

const permissions = computed(() => {
    const roleId = userRole.value?.id;
    if (!roleId) return [];

    return page.props.rolePermissions?.[roleId] ?? [];
});

const hasPermission = (permission?: string) => {
    if (!permission) return true;
    return permissions.value.includes(permission);
};

const filteredItems = computed(() =>
    props.items.filter(item => hasPermission(item.permission))
);
</script>

<template>
    <SidebarGroup v-if="filteredItems.length > 0" class="px-2 py-0">
        <SidebarGroupLabel>{{ title }}</SidebarGroupLabel>

        <SidebarMenu>
            <SidebarMenuItem
                v-for="item in filteredItems"
                :key="item.title"
            >
                <SidebarMenuButton
                    as-child
                    :is-active="
                        page.url === item.href ||
                        page.url.startsWith(item.href + '/')
                    "
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>