<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/types/index.d';
import { Link } from '@inertiajs/vue3';
import { LayoutGrid,Calendar, MapPin, Tag, Settings, Ticket, Megaphone, FileText, MessageSquare, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
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
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
