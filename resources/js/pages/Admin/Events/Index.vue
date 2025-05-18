<template>
    <Head title="Events" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                                Events List
                            </h1>
                            <div class="flex space-x-2">
                                <Link :href="route('admin.ticket-definitions.index')" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition">
                                    Manage Ticket Definitions
                                </Link>
                                <Link :href="route('admin.events.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    Create Event
                                </Link>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="search_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search by Name</label>
                                <input type="text" v-model="filterForm.search_name" @input="searchEvents" id="search_name" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            </div>
                            <div>
                                <label for="event_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select v-model="filterForm.event_status" @change="searchEvents" id="event_status" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">All Statuses</option>
                                    <option v-for="status in eventStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                </select>
                            </div>
                             <!-- Add more filters for category, organizer if data provided from controller -->
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Organizer</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Visibility</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Featured</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Published At</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="events.data && events.data.length === 0">
                                        <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                            No events found.
                                        </td>
                                    </tr>
                                    <tr v-for="event in events.data" :key="event.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ getTranslation(event.name, currentLocale) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ event.category ? getTranslation(event.category.name, currentLocale) : 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ event.organizer ? event.organizer.name : 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span :class="statusClass(event.event_status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ formatStatus(event.event_status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatStatus(event.visibility) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span :class="event.is_featured ? 'text-green-500' : 'text-red-500'">{{ event.is_featured ? 'Yes' : 'No' }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(event.published_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('admin.events.edit', event.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">Edit</Link>
                                            <Link :href="route('admin.events.occurrences.index', { event: event.id })" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-600 mr-3">Occurrences</Link>
                                            <button @click="confirmDeleteEvent(event.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="events.links && events.links.length > 1" class="mt-6 flex justify-between items-center">
                             <div class="text-sm text-gray-700 dark:text-gray-400">
                                Showing {{ events.from }} to {{ events.to }} of {{ events.total }} results
                            </div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <Link
                                    v-for="(link, index) in events.links"
                                    :key="index"
                                    :href="link.url || ''"
                                    preserve-scroll
                                    preserve-state
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                    :class="{
                                        'bg-indigo-500 border-indigo-500 text-white dark:bg-indigo-600 dark:border-indigo-600': link.active,
                                        'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700': !link.active && link.url,
                                        'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:border-gray-600 dark:text-gray-500': !link.url
                                    }"
                                    v-html="link.label"
                                    :disabled="!link.url"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showConfirmDeleteModal" @update:open="showConfirmDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Are you sure you want to delete this event?</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    This action cannot be undone.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteEvent" class="ml-3">Delete Event</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<script setup lang="ts" >
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter
} from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { getTranslation, currentLocale } from '@/Utils/i18n'; // Assuming a i18n utility
import { throttle } from 'lodash';

interface Event {
    id: number;
    name: Record<string, string> | string;
    category?: { name: Record<string, string> | string };
    organizer?: { name: string };
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
    // eventStatuses: Array<{value: string, label: string}>, // Already defined locally
    // categories: Array,
    // organizers: Array,
}>();

const filterForm = useForm({
    search_name: props.filters.search_name || '',
    category_id: props.filters.category_id || '',
    organizer_id: props.filters.organizer_id || '',
    event_status: props.filters.event_status || '',
    per_page: props.events.per_page || 15,
});

const showConfirmDeleteModal = ref(false);
const eventIdToDelete = ref<number | null>(null);

// Assuming event statuses are fixed for now as per Event model, or pass from controller
// This would be better if passed from controller like in EventController's create method.
const eventStatuses = ref([
    { value: 'draft', label: 'Draft' },
    { value: 'pending_approval', label: 'Pending Approval' },
    { value: 'published', label: 'Published' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'completed', label: 'Completed' },
    { value: 'past', label: 'Past' },
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

const confirmDeleteEvent = (id: number) => {
    eventIdToDelete.value = id;
    showConfirmDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showConfirmDeleteModal.value = false;
    eventIdToDelete.value = null;
};

const deleteEvent = () => {
    if (eventIdToDelete.value) {
        router.delete(route('admin.events.destroy', eventIdToDelete.value), {
            onSuccess: () => closeDeleteModal(),
            // onError: (errors) => { /* Handle error */ },
            preserveState: false, // So the page reloads and event list is updated
        });
    }
};

const formatDate = (dateString: string | null): string => {
    if (!dateString) return 'N/A';
    const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString(undefined, options);
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

// Assumed i18n utility in @/Utils/i18n.js
// Example:
// export const currentLocale = ref(document.documentElement.lang || 'en');
// export function getTranslation(translatable, locale, fallbackLocale = 'en') {
//     if (!translatable) return '';
//     if (typeof translatable === 'string') return translatable;
//     return translatable[locale] || translatable[fallbackLocale] || Object.values(translatable)[0] || '';
// }

</script>

<style scoped>
/* Scoped styles if needed */
</style>
