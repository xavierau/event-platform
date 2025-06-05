<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getTranslation, currentLocale } from '@/Utils/i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import type { TicketDefinition, TicketDefinitionsPaginated } from '@/types/ticket';

const props = defineProps<{
    ticketDefinitions: TicketDefinitionsPaginated;
    errors?: object;
}>();

const isDeleteDialogOpen = ref(false);
const ticketDefinitionToDelete = ref<TicketDefinition | null>(null);

const openDeleteModal = (ticketDefinition: TicketDefinition) => {
    ticketDefinitionToDelete.value = ticketDefinition;
    isDeleteDialogOpen.value = true;
};

const closeDeleteModal = () => {
    ticketDefinitionToDelete.value = null;
    isDeleteDialogOpen.value = false;
};

const deleteTicketDefinition = () => {
    if (!ticketDefinitionToDelete.value) return;

    router.delete(route('admin.ticket-definitions.destroy', ticketDefinitionToDelete.value.id), {
        onSuccess: () => {
            closeDeleteModal();
            alert('Ticket definition deleted successfully.');
        },
        onError: (errors) => {
            closeDeleteModal();
            const errorMessages = Object.values(errors).join(' ');
            alert(`Failed to delete ticket definition: ${errorMessages || 'Please try again.'}`);
            console.error("Error deleting ticket definition:", errors);
        },
        preserveScroll: true,
    });
};

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleString(currentLocale.value, options);
    } catch (e) {
        console.error("Error formatting date:", dateString, e);
        return dateString; // return original if formatting fails
    }
};

</script>

<template>
    <Head title="Ticket Definitions" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">

                        <!-- Header: Title and Create Button -->
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                                Ticket Definitions
                            </h1>
                            <div class="flex space-x-2">
                                <Link :href="route('admin.ticket-definitions.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    Create Ticket Definition
                                </Link>
                            </div>
                        </div>
                        <p class="-mt-4 mb-6 text-sm text-gray-700 dark:text-gray-300">
                            A list of all the ticket definitions in the system.
                        </p>

                        <!-- Table section -->
                        <div class="mt-8 flow-root">
                            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Availability Window</th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <template v-if="props.ticketDefinitions && props.ticketDefinitions.data && props.ticketDefinitions.data.length">
                                                <tr v-for="definition in props.ticketDefinitions.data" :key="definition.id">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ getTranslation(definition.name, currentLocale) }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ definition.price !== null ? (definition.price / 100).toFixed(2) : 'N/A' }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ definition.total_quantity !== null ? definition.total_quantity : 'Unlimited' }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                         <span
                                                            :class="{
                                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': definition.status === 'active',
                                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300': definition.status === 'draft',
                                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': definition.status === 'inactive',
                                                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': definition.status === 'archived'
                                                            }"
                                                            class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ring-opacity-20"
                                                        >
                                                            {{ definition.status ? definition.status.charAt(0).toUpperCase() + definition.status.slice(1) : 'N/A' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ formatDate(definition.availability_window_start) }} - {{ formatDate(definition.availability_window_end) }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <Link :href="route('admin.ticket-definitions.edit', definition.id)">
                                                            <Button variant="outline" size="sm">Edit</Button>
                                                        </Link>
                                                        <Button variant="destructive" size="sm" @click="openDeleteModal(definition)">Delete</Button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template v-else>
                                                <tr>
                                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                                        No ticket definitions found.
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="props.ticketDefinitions.links && props.ticketDefinitions.links.length > 1" class="mt-6 flex justify-between items-center">
                             <div class="text-sm text-gray-700 dark:text-gray-400">
                                Showing {{ props.ticketDefinitions.from }} to {{ props.ticketDefinitions.to }} of {{ props.ticketDefinitions.total }} results
                            </div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <Link
                                    v-for="(link, index) in props.ticketDefinitions.links"
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

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="isDeleteDialogOpen" @update:open="isDeleteDialogOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Ticket Definition</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete the ticket definition "{{ ticketDefinitionToDelete ? getTranslation(ticketDefinitionToDelete.name, currentLocale) : '' }}"?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteTicketDefinition">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
