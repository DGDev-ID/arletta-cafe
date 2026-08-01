<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, BookA, BookOpen, Camera, Coffee, Coins, DollarSign, Folder, History, Image, Inbox, LayoutGrid, Package, Receipt, Send, ShieldCheck, Store, User, UserCheck, UserCog, Utensils, MessageSquare } from 'lucide-vue-next';
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
        permission: 'master.cafe.view',
    },
    {
        title: 'Promo',
        href: '/master/cafe-promo',
        icon: Coins,
        permission: 'master.menu.view',
    },
    {
        title: 'Banner Promo',
        href: '/master/promo-banner',
        icon: Image,
        permission: 'master.menu.view',
    },
    {
        title: 'Unit',
        href: '/master/unit',
        icon: Package,
        permission: 'master.unit.view',
    },
    {
        title: 'Material (Recipe)',
        href: '/master/material',
        icon: BookOpen,
        permission: 'master.material.view',
    },
    {
        title: 'Production',
        href: '/master/semi-finished-material',
        icon: BookA,
        permission: 'master.material.view',
    },
    {
        title: 'Menu Category',
        href: '/master/menu-category',
        icon: Folder,
        permission: 'master.menu-category.view',

    },
    {
        title: 'Menu',
        href: '/master/menu',
        icon: Utensils,
        permission: 'master.menu.view',
    },
    {
        title: 'Gallery',
        href: '/master/gallery',
        icon: Camera,
        permission: 'master.gallery.view',
    },
    {
        title: 'Saluran Pihak Ketiga',
        href: '/master/third-party-channel',
        icon: Store,
        permission: 'master.menu.view',
    },
    {
        title: 'Ulasan Pelanggan',
        href: '/master/customer-feedback',
        icon: MessageSquare,
        // Optional: you can define a specific permission if needed, but for now we skip or use a general one.
        // If not using permission guard on the view, we can leave it out. Since we didn't add middleware, it's visible.
        permission: 'master.menu.view', // Use existing permission to ensure only authorized can see Master Data menu
    },
];

const userMamagementNavItems: NavItem[] = [
    {
        title: 'User',
        href: '/user-management/user',
        icon: User,
        permission: 'settings',
    },
    {
        title: 'Admin',
        href: '/user-management/admin',
        icon: ShieldCheck,
        permission: 'user-management.admin',
    },
    {
        title: 'Cashier',
        href: '/user-management/cashier',
        icon: UserCheck,
        permission: 'user-management.cashier',
    },
    {
        title: 'Backoffice',
        href: '/user-management/backoffice',
        icon: Receipt,
        permission: 'user-management.backoffice',
    },
    {
        title: 'Role & Permission',
        href: '/user-management/role-permission',
        icon: UserCog,
        permission: 'settings',
    },
];

const managementNavItems: NavItem[] = [
    {
        title: 'Unit Material Converter',
        href: '/management/unit-material-converter',
        icon: ArrowLeftRight,
        permission: 'management.unit-material-converter',
    },
    {
        title: 'Purchase',
        href: '/management/inbound-outbound-material',
        icon: Inbox,
        permission: 'management.inbound-outbound-material',
    },
    {
        title: 'Expense',
        href: '/management/expense',
        icon: DollarSign,
        permission: 'transaction.history',
    },
];

const transactionNavItems: NavItem[] = [
    {
        title: 'History Transaction',
        href: '/transaction/history',
        icon: History,
        permission: 'transaction.history',
    },
    {
        title: 'Cashier',
        href: '/transaction/cashier',
        icon: DollarSign,
        permission: 'transaction.cashier',
    },
];

const shortcutNavItems: NavItem[] = [
    {
        title: 'Public Link Generator',
        href: '/shortcut/public-link-generator',
        icon: Send,
        permission: 'shortcut.public-link-generator',
    }
]
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
            <NavMain :items="shortcutNavItems" :title="'Shortcut'" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
