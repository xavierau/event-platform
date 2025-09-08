<template>
    <Head title="Promotional Modals" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader title="Promotional Modals" subtitle="Manage promotional modals and banners displayed across the platform">
                            <template #actions>
                                <Link :href="route('admin.promotional-modals.analytics')" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition">
                                    Analytics
                                </Link>
                                <Link :href="route('admin.promotional-modals.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    Create Modal
                                </Link>
                            </template>
                        </PageHeader>

                        <AdminDataTable>
                            <!-- Filters Slot -->
                            <template #filters>
                                <div>
                                    <label for="search_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search by Title</label>
                                    <input type="text" v-model="filterForm.search_title" @input="searchModals" id="search_title" placeholder="Enter title..." class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                    <select v-model="filterForm.type" @change="searchModals" id="type" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">All Types</option>
                                        <option value="modal">Modal</option>
                                        <option value="banner">Banner</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                    <select v-model="filterForm.status" @change="searchModals" id="status" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </template>

                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>Title</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Pages</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Impressions</TableHead>
                                <TableHead>Clicks</TableHead>
                                <TableHead>Conversion Rate</TableHead>
                                <TableHead>Schedule</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="promotionalModals.data && promotionalModals.data.length === 0">
                                    <TableCell colspan="9" class="text-center">No promotional modals found</TableCell>
                                </TableRow>
                                <TableRow v-for="modal in promotionalModals.data" :key="modal.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">
                                        {{ getTranslation(modal.title, currentLocale) }}
                                    </TableCell>
                                    <TableCell>
                                        <span :class="typeClass(modal.type)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ modal.type.charAt(0).toUpperCase() + modal.type.slice(1) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="!modal.pages" class="text-gray-500">All Pages</span>
                                        <span v-else class="text-sm">{{ modal.pages.join(', ') }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <span :class="statusClass(modal.is_active)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ modal.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ modal.impressions_count.toLocaleString() }}</TableCell>
                                    <TableCell>{{ modal.clicks_count.toLocaleString() }}</TableCell>
                                    <TableCell>{{ modal.conversion_rate }}%</TableCell>
                                    <TableCell class="text-sm">
                                        <div v-if="modal.start_at || modal.end_at">
                                            <div v-if="modal.start_at">From: {{ formatDate(modal.start_at) }}</div>
                                            <div v-if="modal.end_at">To: {{ formatDate(modal.end_at) }}</div>
                                        </div>
                                        <span v-else class="text-gray-500">No schedule</span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <button @click="toggleStatus(modal)" :class="modal.is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'" class="mr-3">
                                            {{ modal.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <Link :href="route('admin.promotional-modals.edit', modal.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">Edit</Link>
                                        <button @click="duplicateModal(modal)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-600 mr-3">Duplicate</button>
                                        <button @click="confirmDeleteModal(modal.id, getTranslation(modal.title, 'en'))" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">Delete</button>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="promotionalModals.links" :from="promotionalModals.from" :to="promotionalModals.to" :total="promotionalModals.total" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showConfirmDeleteModal" @update:open="showConfirmDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Delete Promotional Modal</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete "{{ modalToDelete?.name }}"? This action cannot be undone.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteModal" class="ml-3">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter
} from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { getTranslation } from '@/Utils/i18n';
import { throttle } from 'lodash';
import PageHeader from '@/components/Shared/PageHeader.vue';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import { TableHead, TableRow, TableCell } from '@/components/ui/table';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

interface PromotionalModal {
    id: number;
    title: Record<string, string> | string;
    type: 'modal' | 'banner';
    pages: string[] | null;
    is_active: boolean;
    impressions_count: number;
    clicks_count: number;
    conversion_rate: number;
    start_at: string | null;
    end_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPromotionalModals {
    data: PromotionalModal[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
    per_page: number;
}

interface Filters {
    search_title?: string;
    type?: string;
    status?: string;
}

const props = defineProps<{
    promotionalModals: PaginatedPromotionalModals;
    filters: Filters;
}>();

const filterForm = useForm({
    search_title: props.filters.search_title || '',
    type: props.filters.type || '',
    status: props.filters.status || '',
    per_page: props.promotionalModals.per_page || 15,
});

const showConfirmDeleteModal = ref(false);
const modalToDelete = ref<{ id: number; name: string } | null>(null);

const searchModals = throttle(() => {
    filterForm.get(route('admin.promotional-modals.index'), {
        preserveState: true,
        replace: true,
    });
}, 300);

const typeClass = (type: string) => {
    return type === 'modal'
        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
};

const statusClass = (isActive: boolean) => {
    return isActive
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
};

const formatDate = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString();
};

const toggleStatus = (modal: PromotionalModal) => {
    router.post(route('admin.promotional-modals.toggle', modal.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // The page will automatically update due to Inertia
        }
    });
};

const duplicateModal = (modal: PromotionalModal) => {
    router.post(route('admin.promotional-modals.duplicate', modal.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // The page will automatically update
        }
    });
};

const confirmDeleteModal = (id: number, name: string) => {
    modalToDelete.value = { id, name };
    showConfirmDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showConfirmDeleteModal.value = false;
    modalToDelete.value = null;
};

const deleteModal = () => {
    if (modalToDelete.value) {
        router.delete(route('admin.promotional-modals.destroy', modalToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
            }
        });
    }
};
</script>