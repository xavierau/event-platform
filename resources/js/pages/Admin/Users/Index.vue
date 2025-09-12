<template>
    <Head :title="t('users.index_title')" />
    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg dark:bg-gray-800">
                    <div
                        class="border-b border-gray-200 bg-white p-6 lg:p-8 dark:border-gray-700 dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent"
                    >
                        <div class="flex items-center justify-between">
                            <PageHeader :title="t('users.index_title')" :subtitle="t('users.index_subtitle')" />
                            <Link 
                                :href="route('admin.users.create')"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                            >
                                {{ t('actions.create_user') }}
                            </Link>
                        </div>

                        <AdminDataTable>
                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>{{ t('fields.name') }}</TableHead>
                                <TableHead>{{ t('fields.email') }}</TableHead>
                                <TableHead>{{ t('fields.commenting_blocked') }}</TableHead>
                                <TableHead>{{ t('fields.membership_level') }}</TableHead>
                                <TableHead>{{ t('fields.organization') }}</TableHead>
                                <TableHead class="text-right">{{ t('fields.actions') }}</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="users.data && users.data.length === 0">
                                    <TableCell colspan="6" class="text-center">{{ t('users.no_users_found') }} </TableCell>
                                </TableRow>
                                <TableRow v-for="user in users.data" :key="user.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">{{ user.name }} </TableCell>
                                    <TableCell>{{ user.email }}</TableCell>
                                    <TableCell>
                                        <span :class="user.is_commenting_blocked ? 'text-red-500' : 'text-green-500'">
                                            {{ user.is_commenting_blocked ? t('common.yes') : t('common.no') }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ user.membership_level }}</TableCell>
                                    <TableCell>{{ user.organizer_info }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link
                                            :href="route('admin.users.show', user.id)"
                                            class="mr-3 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600"
                                        >
                                            {{ t('actions.view') }}
                                        </Link>
                                        <Link
                                            :href="route('admin.users.edit', user.id)"
                                            class="mr-3 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600"
                                        >
                                            {{ t('actions.edit') }}
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="users.links" :from="users.from" :to="users.to" :total="users.total" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: User[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
    per_page: number;
}

defineProps<{
    users: PaginatedUsers;
}>();
</script>
