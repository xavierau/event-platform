<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/types/index.d';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Calendar, MapPin, Tag, Settings, Ticket, Megaphone, FileText, MessageSquare, Users, Percent, UserCog, ScanLine, QrCode, Crown, ClipboardList, Lock } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage<SharedData>();
const userPermissions = computed(() => page.props.auth.user.permissions || []);
const user = computed(() => page.props.auth.user);

const canManageUsers = computed(() => userPermissions.value.includes('manage-users'));
const isAdmin = computed(() => user.value?.is_admin || false);
const isOrganizerMember = computed(() => user.value?.is_organizer_member || false);

// Items available to both platform admins and organizer members
const sharedNavItems: NavItem[] = [
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
        title: 'Ticket Holds',
        href: '/admin/ticket-holds',
        icon: Lock,
    },
    {
        title: 'Coupons',
        href: '/admin/coupons',
        icon: Percent,
    },
    {
        title: 'Check-in Records',
        href: '/admin/check-in-records',
        icon: ClipboardList,
    },
    {
        title: 'Organizers',
        href: '/admin/organizers',
        icon: Users,
    },
    {
        title: 'Venues',
        href: '/admin/venues',
        icon: MapPin,
    },
];

// Items only available to platform admins
const adminOnlyNavItems: NavItem[] = [
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
        title: 'Promotional Modals',
        href: '/admin/promotional-modals',
        icon: Megaphone,
    },
    {
        title: 'Membership Levels',
        href: '/admin/membership-levels',
        icon: Crown,
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
    // Start with shared items that both admin and organizer members can access
    let items = [...sharedNavItems];

    // Add admin-only items if user is platform admin
    if (isAdmin.value) {
        items = [...items, ...adminOnlyNavItems];

        // Add user management item if user has the specific permission
        if (canManageUsers.value) {
            const settingsIndex = items.findIndex(item => item.title === 'Settings');
            if (settingsIndex !== -1) {
                items.splice(settingsIndex, 0, userManagementNavItem);
            } else {
                items.push(userManagementNavItem);
            }
        }
    }

    // Only show navigation if user is either admin or organizer member
    if (!isAdmin.value && !isOrganizerMember.value) {
        return [];
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
