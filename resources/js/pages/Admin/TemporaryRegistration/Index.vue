<template>
    <Head title="Temporary Registration Pages" />
    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Page Header -->
                <div class="flex items-center justify-between">
                    <PageHeader title="Temporary Registration Pages" />
                    <Link
                        :href="route('admin.temporary-registration.create')"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    >
                        Create Registration Page
                    </Link>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <DocumentTextIcon class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Pages</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total_pages }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <CheckCircleIcon class="h-8 w-8 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Pages</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.active_pages }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <UsersIcon class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Registrations</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total_registrations }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pages Table -->
                <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 bg-white p-6 lg:p-8 dark:border-gray-700 dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent">
                        <AdminDataTable>
                            <template #header>
                                <TableHead>Title</TableHead>
                                <TableHead>Membership Level</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Registrations</TableHead>
                                <TableHead>Expires At</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </template>

                            <template #body>
                                <TableRow v-if="pages.data && pages.data.length === 0">
                                    <TableCell colspan="6" class="text-center text-gray-500 dark:text-gray-400">
                                        No temporary registration pages found.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="page in pages.data" :key="page.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">
                                        {{ getTranslation(page.title) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ getTranslation(page.membership_level?.name) || 'N/A' }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge :variant="page.is_active ? 'success' : 'secondary'">
                                                {{ page.is_active ? 'Active' : 'Inactive' }}
                                            </Badge>
                                            <Badge v-if="isExpired(page.expires_at)" variant="destructive">
                                                Expired
                                            </Badge>
                                            <Badge v-if="isFull(page)" variant="warning">
                                                Full
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {{ page.registrations_count }}
                                        <span v-if="page.max_registrations">/ {{ page.max_registrations }}</span>
                                    </TableCell>
                                    <TableCell>
                                        {{ page.expires_at ? formatDate(page.expires_at) : 'Never' }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link
                                                :href="route('admin.temporary-registration.show', page.id)"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            >
                                                View
                                            </Link>
                                            <Link
                                                :href="route('admin.temporary-registration.edit', page.id)"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                @click="toggleActive(page)"
                                                class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                            >
                                                {{ page.is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                            <button
                                                @click="confirmDelete(page)"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination
                            v-if="pages.links"
                            :links="pages.links"
                            :from="pages.meta?.from"
                            :to="pages.meta?.to"
                            :total="pages.meta?.total"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Registration Page</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this registration page? This action cannot be undone.
                        All registration data will be permanently removed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="deletePage" :disabled="isDeleting">
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DocumentTextIcon,
    CheckCircleIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { getTranslation } from '@/Utils/i18n';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
}

interface TemporaryRegistrationPage {
    id: number;
    title: Record<string, string>;
    slug: string | null;
    token: string;
    membership_level: MembershipLevel | null;
    is_active: boolean;
    expires_at: string | null;
    max_registrations: number | null;
    registrations_count: number;
    registered_users_count: number;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    pages: {
        data: TemporaryRegistrationPage[];
        links: PaginationLink[];
        meta: {
            from: number;
            to: number;
            total: number;
        };
    };
    stats: {
        total_pages: number;
        active_pages: number;
        total_registrations: number;
    };
}

const props = defineProps<Props>();

const showDeleteDialog = ref(false);
const pageToDelete = ref<TemporaryRegistrationPage | null>(null);
const isDeleting = ref(false);

const formatDate = (dateString: string): string => {
    const options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
};

const isExpired = (expiresAt: string | null): boolean => {
    if (!expiresAt) return false;
    return new Date(expiresAt) < new Date();
};

const isFull = (page: TemporaryRegistrationPage): boolean => {
    if (!page.max_registrations) return false;
    return page.registrations_count >= page.max_registrations;
};

const toggleActive = (page: TemporaryRegistrationPage) => {
    router.patch(
        route('admin.temporary-registration.toggle-active', page.id),
        {},
        {
            preserveScroll: true,
        }
    );
};

const confirmDelete = (page: TemporaryRegistrationPage) => {
    pageToDelete.value = page;
    showDeleteDialog.value = true;
};

const deletePage = () => {
    if (!pageToDelete.value) return;

    isDeleting.value = true;
    router.delete(route('admin.temporary-registration.destroy', pageToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            pageToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>
