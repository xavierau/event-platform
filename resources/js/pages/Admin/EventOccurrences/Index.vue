<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getTranslation, currentLocale } from '@/Utils/i18n'; // Assuming i18n utility
import { ref } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

const props = defineProps({
    event: Object, // Parent Event: { id, name }
    occurrences: Object, // Paginated EventOccurrenceData: { data, links, from, to, total, ... }
    pageTitle: String,
    breadcrumbs: Array,
});

const showConfirmDeleteModal = ref(false);
const occurrenceIdToDelete = ref(null);

const confirmDeleteOccurrence = (id: number) => {
    occurrenceIdToDelete.value = id;
    showConfirmDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showConfirmDeleteModal.value = false;
    occurrenceIdToDelete.value = null;
};

const deleteOccurrence = () => {
    if (occurrenceIdToDelete.value) {
        router.delete(route('admin.occurrences.destroy', occurrenceIdToDelete.value), { // Shallow route
            onSuccess: () => closeDeleteModal(),
            preserveState: false, // Ensure list updates
        });
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'N/A';
    try {
        const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleDateString(currentLocale.value || undefined, options);
    } catch (e) {
        console.error('Error formatting date:', dateString, e); // Log the error
        return dateString; // Fallback to original string if date is invalid
    }
};

const formatStatus = (status: string | null) => {
    if (!status) return 'N/A';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

</script>

<template>
    <Head :title="props.pageTitle || 'Event Occurrences'" />
    <AppLayout :page-title="props.pageTitle" :breadcrumbs="props.breadcrumbs">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                                Occurrences for: {{ props.event.name }}
                            </h1>
                            <Link :href="route('admin.events.occurrences.create', { event: props.event.id })" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                Create New Occurrence
                            </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name/Desc</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Start At</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">End At</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Venue / Online</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Capacity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="!props.occurrences || props.occurrences.data.length === 0">
                                        <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                            No occurrences found for this event.
                                        </td>
                                    </tr>
                                    <tr v-for="occurrence in props.occurrences.data" :key="occurrence.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <div v-if="occurrence.name && getTranslation(occurrence.name, currentLocale)">{{ getTranslation(occurrence.name, currentLocale) }}</div>
                                            <div v-else class="text-xs text-gray-500 italic">{{ getTranslation(occurrence.description, currentLocale, 'en').substring(0,50) || 'No name/desc' }}...</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(occurrence.start_at_utc) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(occurrence.end_at_utc) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span v-if="occurrence.is_online">Online: {{ occurrence.online_meeting_link }}</span>
                                            <span v-else-if="occurrence.venue && occurrence.venue.name">{{ getTranslation(occurrence.venue.name, currentLocale) }}</span>
                                            <span v-else-if="occurrence.venue && !occurrence.venue.name">Unknown Venue Name</span>
                                            <span v-else>N/A</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ occurrence.capacity !== null ? occurrence.capacity : 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                             <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="{
                                                'bg-green-100 text-green-800': occurrence.status === 'scheduled',
                                                'bg-red-100 text-red-800': occurrence.status === 'cancelled',
                                                'bg-yellow-100 text-yellow-800': occurrence.status === 'postponed',
                                                'bg-blue-100 text-blue-800': occurrence.status === 'completed'
                                            }">
                                                {{ formatStatus(occurrence.status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('admin.occurrences.edit', occurrence.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">Edit</Link>
                                            <button @click="confirmDeleteOccurrence(occurrence.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="props.occurrences && props.occurrences.links && props.occurrences.links.length > 1" class="mt-6 flex justify-between items-center">
                             <div class="text-sm text-gray-700 dark:text-gray-400">
                                Showing {{ props.occurrences.from }} to {{ props.occurrences.to }} of {{ props.occurrences.total }} results
                            </div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <Link
                                    v-for="(link, index) in props.occurrences.links"
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
                    <DialogTitle>Delete Occurrence?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this occurrence? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="pt-4">
                    <DialogClose as-child>
                        <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="deleteOccurrence" class="ml-3">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles if needed */
</style>
