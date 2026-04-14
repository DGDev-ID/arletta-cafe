<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, BookOpen, Coffee, DollarSign, Folder, History, Inbox, LayoutGrid, Package, Receipt, Send, ShieldCheck, UserCheck, Utensils } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const masterDataNavItems: NavItem[] = [
    {
        title: 'Cafe & Table',
        href: '/master/cafe',
        icon: Coffee,
    },
    {
        title: 'Unit',
        href: '/master/unit',
        icon: Package,
    },
    {
        title: 'Material (Recipe)',
        href: '/master/material',
        icon: BookOpen,
    },
    {
        title: 'Menu Category',
        href: '/master/menu-category',
        icon: Folder,
    },
    {
        title: 'Menu',
        href: '/master/menu',
        icon: Utensils,
    },
];

const userMamagementNavItems: NavItem[] = [
    {
        title: 'Admin',
        href: '/user-management/admin',
        icon: ShieldCheck,
    },
    {
        title: 'Cashier',
        href: '/user-management/cashier',
        icon: UserCheck,
    },
    {
        title: 'Backoffice',
        href: '/user-management/backoffice',
        icon: Receipt,
    },
];

const managementNavItems: NavItem[] = [
    {
        title: 'Unit Material Converter',
        href: '/management/unit-material-converter',
        icon: ArrowLeftRight,
    },
    {
        title: 'In-Out Material',
        href: '/management/inbound-outbound-material',
        icon: Inbox,
    },
];

const transactionNavItems: NavItem[] = [
    {
        title: 'History Transaction',
        href: '/transaction/history',
        icon: History,
    },
    {
        title: 'Cashier',
        href: '/transaction/cashier',
        icon: DollarSign,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" :title="'Dashboard'" />
            <NavMain :items="masterDataNavItems" :title="'Master Data'" />
            <NavMain :items="userMamagementNavItems" :title="'User Management'" />
            <NavMain :items="managementNavItems" :title="'Management'" />
            <NavMain :items="transactionNavItems" :title="'Transaction'" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
