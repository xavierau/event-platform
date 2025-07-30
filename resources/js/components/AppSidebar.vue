<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/types/index.d';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid,Calendar, MapPin, Tag, Settings, Ticket, Megaphone, FileText, MessageSquare, Users, Percent, UserCog, ScanLine, QrCode } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage<SharedData>();
const userPermissions = computed(() => page.props.auth.user.permissions || []);

const canManageUsers = computed(() => userPermissions.value.includes('manage-users'));

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Events',
        href: '/admin/events',
        icon: Calendar,
    },
    {
        title: 'Bookings',
        href: '/admin/bookings',
        icon: Ticket,
    },
    {
        title: 'Coupons',
        href: '/admin/coupons',
        icon: Percent,
    },
    {
        title: 'Organizers',
        href: '/admin/organizers',
        icon: Users,
    },
    {
        title: 'CMS Pages',
        href: '/admin/cms-pages',
        icon: FileText,
    },
    {
        title: 'Contact Submissions',
        href: '/admin/contact-submissions',
        icon: MessageSquare,
    },
    {
        title: 'Venues',
        href: '/admin/venues',
        icon: MapPin,
    },
    {
        title: 'Categories',
        href: '/admin/categories',
        icon: Tag,
    },
    {
        title: 'Tags',
        href: '/admin/tags',
        icon: Tag,
    },
    {
        title: 'Promotions',
        href: '/admin/promotions',
        icon: Megaphone,
    },
    {
        title: 'Settings',
        href: '/admin/settings',
        icon: Settings,
    }

];

const userManagementNavItem: NavItem = {
    title: 'User Management',
    href: '/admin/users',
    icon: UserCog,
};

const filteredMainNavItems = computed(() => {
    const items = [...mainNavItems];
    if (canManageUsers.value) {
        // find settings index
        const settingsIndex = items.findIndex(item => item.title === 'Settings');
        if (settingsIndex !== -1) {
            items.splice(settingsIndex, 0, userManagementNavItem);
        } else {
            items.push(userManagementNavItem);
        }
    }
    return items;
});


const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // }
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('admin.dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="filteredMainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
