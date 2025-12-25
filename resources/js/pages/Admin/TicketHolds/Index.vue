<template>
    <Head title="Ticket Holds" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader title="Ticket Hold Management" subtitle="Manage private ticket holds and purchase links">
                            <template #actions>
                                <Link :href="route('admin.ticket-holds.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    Create New Hold
                                </Link>
                            </template>
                        </PageHeader>

                        <AdminDataTable>
                            <!-- Filters Slot -->
                            <template #filters>
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                                    <input
                                        type="text"
                                        v-model="filterForm.search"
                                        @input="searchHolds"
                                        id="search"
                                        placeholder="Search by name..."
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                </div>
                                <div>
                                    <label for="organizer_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organizer</label>
                                    <select
                                        v-model="filterForm.organizer_id"
                                        @change="searchHolds"
                                        id="organizer_filter"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">All Organizers</option>
                                        <option v-for="organizer in organizers" :key="organizer.id" :value="organizer.id">
                                            {{ getTranslation(organizer.name) }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="occurrence_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Event Occurrence</label>
                                    <select
                                        v-model="filterForm.occurrence_id"
                                        @change="searchHolds"
                                        id="occurrence_filter"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">All Occurrences</option>
                                        <option v-for="occurrence in occurrences" :key="occurrence.id" :value="occurrence.id">
                                            {{ formatOccurrenceLabel(occurrence) }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="status_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                    <select
                                        v-model="filterForm.status"
                                        @change="searchHolds"
                                        id="status_filter"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="expired">Expired</option>
                                        <option value="released">Released</option>
                                        <option value="exhausted">Exhausted</option>
                                    </select>
                                </div>
                            </template>

                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>Name</TableHead>
                                <TableHead>Event / Occurrence</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Allocated</TableHead>
                                <TableHead>Remaining</TableHead>
                                <TableHead>Links</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="ticketHolds.data && ticketHolds.data.length === 0">
                                    <TableCell colspan="7" class="text-center">No ticket holds found</TableCell>
                                </TableRow>
                                <TableRow v-for="hold in ticketHolds.data" :key="hold.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">
                                        {{ hold.name }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ getTranslation(hold.event_occurrence?.event?.name) }}
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-400">
                                                {{ formatDate(hold.event_occurrence?.start_at) }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <HoldStatusBadge :status="hold.status" />
                                    </TableCell>
                                    <TableCell>{{ hold.total_allocated }}</TableCell>
                                    <TableCell>{{ hold.total_remaining }}</TableCell>
                                    <TableCell>{{ hold.purchase_links_count || 0 }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('admin.ticket-holds.show', hold.id)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-600 mr-3">
                                            View
                                        </Link>
                                        <Link :href="route('admin.ticket-holds.edit', hold.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">
                                            Edit
                                        </Link>
                                        <button
                                            v-if="hold.status === 'active'"
                                            @click="confirmReleaseHold(hold)"
                                            class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-600 mr-3"
                                        >
                                            Release
                                        </button>
                                        <button
                                            @click="confirmDeleteHold(hold)"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600"
                                        >
                                            Delete
                                        </button>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="ticketHolds.links" :from="ticketHolds.from" :to="ticketHolds.to" :total="ticketHolds.total" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Release Confirmation Modal -->
        <Dialog :open="showReleaseModal" @update:open="showReleaseModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Release Ticket Hold</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to release the hold "{{ holdToRelease?.name }}"? This will return all unallocated tickets to the public pool.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeReleaseModal">Cancel</Button>
                    <Button variant="default" @click="releaseHold" class="ml-3 bg-yellow-600 hover:bg-yellow-700">Release Hold</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showDeleteModal" @update:open="showDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Delete Ticket Hold</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete the hold "{{ holdToDelete?.name }}"? This action cannot be undone.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteHold" class="ml-3">Delete Hold</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { getTranslation } from '@/Utils/i18n';
import { throttle } from 'lodash';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { useTicketHoldFormatters } from '@/composables/useTicketHoldFormatters';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import { TableHead, TableRow, TableCell } from '@/components/ui/table';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import HoldStatusBadge from './components/HoldStatusBadge.vue';

type HoldStatus = 'active' | 'expired' | 'released' | 'exhausted';

interface EventOccurrence {
    id: number;
    event: {
        id: number;
        name: Record<string, string> | string;
    };
    start_at: string;
    end_at?: string;
}

interface Organizer {
    id: number;
    name: Record<string, string> | string;
}

interface TicketHold {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    status: HoldStatus;
    expires_at: string | null;
    total_allocated: number;
    total_remaining: number;
    purchase_links_count: number;
    event_occurrence?: EventOccurrence;
    organizer?: Organizer;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTicketHolds {
    data: TicketHold[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
    per_page: number;
}

interface Filters {
    search?: string;
    organizer_id?: string;
    occurrence_id?: string;
    status?: string;
}

const props = defineProps<{
    ticketHolds: PaginatedTicketHolds;
    organizers: Organizer[];
    occurrences: EventOccurrence[];
    filters?: Filters;
}>();

const filterForm = useForm({
    search: props.filters?.search || '',
    organizer_id: props.filters?.organizer_id || '',
    occurrence_id: props.filters?.occurrence_id || '',
    status: props.filters?.status || '',
    per_page: props.ticketHolds?.per_page || 15,
});

const showReleaseModal = ref(false);
const showDeleteModal = ref(false);
const holdToRelease = ref<TicketHold | null>(null);
const holdToDelete = ref<TicketHold | null>(null);

const { formatDate } = useTicketHoldFormatters();

const searchHolds = throttle(() => {
    filterForm.get(route('admin.ticket-holds.index'), {
        preserveState: true,
        replace: true,
    });
}, 300);

const formatOccurrenceLabel = (occurrence: EventOccurrence): string => {
    const eventName = getTranslation(occurrence.event.name);
    const date = new Date(occurrence.start_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
    return `${eventName} - ${date}`;
};

const confirmReleaseHold = (hold: TicketHold) => {
    holdToRelease.value = hold;
    showReleaseModal.value = true;
};

const closeReleaseModal = () => {
    showReleaseModal.value = false;
    holdToRelease.value = null;
};

const releaseHold = () => {
    if (holdToRelease.value) {
        router.post(route('admin.ticket-holds.release', holdToRelease.value.id), {}, {
            preserveState: false,
            onSuccess: () => closeReleaseModal(),
        });
    }
};

const confirmDeleteHold = (hold: TicketHold) => {
    holdToDelete.value = hold;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    holdToDelete.value = null;
};

const deleteHold = () => {
    if (holdToDelete.value) {
        router.delete(route('admin.ticket-holds.destroy', holdToDelete.value.id), {
            preserveState: false,
            onSuccess: () => closeDeleteModal(),
        });
    }
};
</script>
