<template>
    <Head :title="t('events.index_title')" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="t('events.index_title')" :subtitle="t('events.index_subtitle')">
                            <template #actions>
                                <Link :href="route('admin.ticket-definitions.index')" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition">
                                    {{ t('events.manage_ticket_definitions_button') }}
                                </Link>
                                <Link :href="route('admin.events.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    {{ t('events.create_button') }}
                                </Link>
                            </template>
                        </PageHeader>

                        <AdminDataTable>
                            <!-- Filters Slot -->
                            <template #filters>
                                <div>
                                    <label for="search_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.search_by_name') }}</label>
                                    <input type="text" v-model="filterForm.search_name" @input="searchEvents" id="search_name" :placeholder="t('events.search_placeholder')" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label for="event_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.status') }}</label>
                                    <select v-model="filterForm.event_status" @change="searchEvents" id="event_status" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">{{ t('filters.all_statuses') }}</option>
                                        <option v-for="status in eventStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                    </select>
                                </div>
                            </template>

                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>{{ t('fields.name') }}</TableHead>
                                <TableHead>{{ t('fields.category') }}</TableHead>
                                <TableHead>{{ t('fields.organizer') }}</TableHead>
                                <TableHead>{{ t('fields.status') }}</TableHead>
                                <TableHead>{{ t('fields.visibility') }}</TableHead>
                                <TableHead>{{ t('fields.featured') }}</TableHead>
                                <TableHead>{{ t('fields.published_at') }}</TableHead>
                                <TableHead class="text-right">{{ t('fields.actions') }}</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="events.data && events.data.length === 0">
                                    <TableCell colspan="8" class="text-center">{{ t('events.no_events_found') }}</TableCell>
                                </TableRow>
                                <TableRow v-for="event in events.data" :key="event.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">{{ getTranslation(event.name, currentLocale) }}</TableCell>
                                    <TableCell>{{ event.category ? getTranslation(event.category.name, currentLocale) : 'N/A' }}</TableCell>
                                    <TableCell>{{ event.organizer ? getTranslation(event.organizer.name, currentLocale) : 'N/A' }}</TableCell>
                                    <TableCell>
                                        <span :class="statusClass(event.event_status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ formatStatus(event.event_status) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ formatStatus(event.visibility) }}</TableCell>
                                    <TableCell>
                                        <span :class="event.is_featured ? 'text-green-500' : 'text-red-500'">{{ event.is_featured ? t('common.yes') : t('common.no') }}</span>
                                    </TableCell>
                                    <TableCell>{{ formatDate(event.published_at) }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('admin.events.edit', event.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">{{ t('actions.edit') }}</Link>
                                        <Link :href="route('admin.events.seo.show', event.id)" class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-600 mr-3">SEO</Link>
                                        <Link :href="route('admin.events.occurrences.index', { event: event.id })" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-600 mr-3">{{ t('events.occurrences') }}</Link>
                                        <button @click="confirmDeleteEvent(event.id, getTranslation(event.name, 'en'))" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">{{ t('actions.delete') }}</button>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="events.links" :from="events.from" :to="events.to" :total="events.total" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showConfirmDeleteModal" @update:open="showConfirmDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ t('events.delete_title') }}</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('events.delete_confirmation', { name: eventToDelete?.name }) }}
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">{{ t('actions.cancel') }}</Button>
                    <Button variant="destructive" @click="deleteEvent" class="ml-3">{{ t('events.delete_button') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();
const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

interface Organizer {
    name: Record<string, string> | string;
}

interface Category {
    name: Record<string, string> | string;
}

interface Event {
    id: number;
    name: Record<string, string> | string;
    category?: Category;
    organizer?: Organizer;
    event_status: string;
    visibility: string;
    is_featured: boolean;
    published_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedEvents {
    data: Event[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
    per_page: number;
}

interface Filters {
    search_name?: string;
    category_id?: string;
    organizer_id?: string;
    event_status?: string;
}

const props = defineProps<{
    events: PaginatedEvents;
    filters: Filters;
}>();

const filterForm = useForm({
    search_name: props.filters.search_name || '',
    category_id: props.filters.category_id || '',
    organizer_id: props.filters.organizer_id || '',
    event_status: props.filters.event_status || '',
    per_page: props.events.per_page || 15,
});

const showConfirmDeleteModal = ref(false);
const eventToDelete = ref<{ id: number; name: string } | null>(null);

const eventStatuses = ref([
    { value: 'draft', label: t('events.status.draft') },
    { value: 'pending_approval', label: t('events.status.pending_approval') },
    { value: 'published', label: t('events.status.published') },
    { value: 'cancelled', label: t('events.status.cancelled') },
    { value: 'completed', label: t('events.status.completed') },
    { value: 'past', label: t('events.status.past') },
]);

const searchEvents = throttle(() => {
    filterForm.get(route('admin.events.index'), {
        preserveState: true,
        replace: true,
    });
}, 300);

watch(() => filterForm.per_page, () => {
    searchEvents();
});

const confirmDeleteEvent = (id: number, name: string) => {
    eventToDelete.value = { id, name };
    showConfirmDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showConfirmDeleteModal.value = false;
    eventToDelete.value = null;
};

const deleteEvent = () => {
    if (eventToDelete.value) {
        router.delete(route('admin.events.destroy', eventToDelete.value.id), {
            onSuccess: () => closeDeleteModal(),
            preserveState: false,
        });
    }
};

const formatDate = (dateString: string | null): string => {
    if (!dateString) return 'N/A';
    const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString(currentLocale.value, options);
};

const formatStatus = (status: string | null): string => {
    if (!status) return 'N/A';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const statusClass = (status: string | null): string => {
    switch (status) {
        case 'published': return 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200';
        case 'draft': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-600 dark:text-yellow-100';
        case 'pending_approval': return 'bg-blue-100 text-blue-800 dark:bg-blue-600 dark:text-blue-100';
        case 'cancelled': return 'bg-red-100 text-red-800 dark:bg-red-600 dark:text-red-100';
        case 'completed': return 'bg-purple-100 text-purple-800 dark:bg-purple-600 dark:text-purple-100';
        case 'past': return 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200';
        default: return 'bg-gray-200 text-gray-800 dark:bg-gray-500 dark:text-gray-100';
    }
};
</script>

<style scoped>
/* Scoped styles if needed */
</style>
