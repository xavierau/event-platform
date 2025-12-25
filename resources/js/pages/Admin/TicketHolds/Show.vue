<template>
    <Head :title="`Hold: ${ticketHold.name}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <!-- Header -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <div>
                                <div class="flex items-center gap-3">
                                    <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                                        {{ ticketHold.name }}
                                    </h1>
                                    <HoldStatusBadge :status="ticketHold.status" />
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ ticketHold.description || 'No description provided.' }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    :href="route('admin.ticket-holds.edit', ticketHold.id)"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition"
                                >
                                    Edit Hold
                                </Link>
                                <button
                                    v-if="ticketHold.status === 'active'"
                                    @click="confirmRelease"
                                    class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-500 active:bg-yellow-700 focus:outline-none focus:border-yellow-700 focus:ring focus:ring-yellow-200 disabled:opacity-25 transition"
                                >
                                    Release Hold
                                </button>
                                <button
                                    @click="confirmDelete"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:border-red-700 focus:ring focus:ring-red-200 disabled:opacity-25 transition"
                                >
                                    Delete
                                </button>
                                <Link
                                    :href="route('admin.ticket-holds.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition"
                                >
                                    Back to Holds
                                </Link>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                            <!-- Details Card -->
                            <div class="lg:col-span-2 bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg shadow">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Hold Details</h3>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Event</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ getTranslation(ticketHold.event_occurrence?.event?.name) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Occurrence</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ formatDate(ticketHold.event_occurrence?.start_at) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Organizer</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ ticketHold.organizer ? getTranslation(ticketHold.organizer.name) : 'Admin Hold' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created By</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ ticketHold.creator?.name || 'N/A' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ formatDate(ticketHold.created_at) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires At</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ ticketHold.expires_at ? formatDate(ticketHold.expires_at) : 'Never' }}
                                        </dd>
                                    </div>
                                    <div v-if="ticketHold.internal_notes" class="sm:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Internal Notes</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-wrap">
                                            {{ ticketHold.internal_notes }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Statistics Card -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg shadow">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Statistics</h3>
                                <HoldStats :analytics="analytics" />
                            </div>
                        </div>

                        <!-- Allocations Table -->
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg shadow mb-8">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Ticket Allocations</h3>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ticket Type</TableHead>
                                        <TableHead>Allocated</TableHead>
                                        <TableHead>Purchased</TableHead>
                                        <TableHead>Remaining</TableHead>
                                        <TableHead>Pricing Mode</TableHead>
                                        <TableHead>Effective Price</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="!ticketHold.allocations || ticketHold.allocations.length === 0">
                                        <TableCell colspan="6" class="text-center text-gray-500">No allocations found</TableCell>
                                    </TableRow>
                                    <TableRow v-for="allocation in ticketHold.allocations" :key="allocation.id">
                                        <TableCell class="font-medium text-gray-900 dark:text-white">
                                            {{ getTranslation(allocation.ticket_definition?.name) }}
                                        </TableCell>
                                        <TableCell>{{ allocation.allocated_quantity }}</TableCell>
                                        <TableCell>{{ allocation.purchased_quantity }}</TableCell>
                                        <TableCell>{{ allocation.remaining_quantity }}</TableCell>
                                        <TableCell>{{ formatPricingMode(allocation.pricing_mode) }}</TableCell>
                                        <TableCell class="font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ formatEffectivePrice(allocation) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Purchase Links Section -->
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg shadow">
                            <PurchaseLinkList
                                :links="ticketHold.purchase_links || []"
                                :ticket-hold-id="ticketHold.id"
                            />
                        </div>
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
                    Are you sure you want to release this hold? All unallocated tickets will return to the public pool and can no longer be purchased through the associated links.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="showReleaseModal = false">Cancel</Button>
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
                    Are you sure you want to delete this hold? This action cannot be undone. All purchase links will also be deleted.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteModal = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteHold" class="ml-3">Delete Hold</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from '@/components/ui/table';
import { getTranslation } from '@/Utils/i18n';
import { useTicketHoldFormatters } from '@/composables/useTicketHoldFormatters';
import HoldStatusBadge from './components/HoldStatusBadge.vue';
import HoldStats from './components/HoldStats.vue';
import PurchaseLinkList from './components/PurchaseLinkList.vue';

type HoldStatus = 'active' | 'expired' | 'released' | 'exhausted';
type PricingMode = 'original' | 'fixed' | 'percentage_discount' | 'free';
type LinkStatus = 'active' | 'expired' | 'revoked' | 'exhausted';
type QuantityMode = 'fixed' | 'maximum' | 'unlimited';

interface User {
    id: number;
    name: string;
    email: string;
}

interface TicketDefinition {
    id: number;
    name: Record<string, string> | string;
    price: number;
}

interface AllocationData {
    id: number;
    ticket_definition_id: number;
    allocated_quantity: number;
    purchased_quantity: number;
    remaining_quantity: number;
    pricing_mode: PricingMode;
    custom_price: number | null;
    discount_percentage: number | null;
    ticket_definition?: TicketDefinition;
}

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

interface PurchaseLink {
    id: number;
    uuid: string;
    code: string;
    name: string | null;
    status: LinkStatus;
    quantity_mode: QuantityMode;
    quantity_limit: number | null;
    quantity_purchased: number;
    assigned_user_id: number | null;
    assigned_user?: User | null;
    expires_at: string | null;
    notes: string | null;
    full_url: string;
    created_at: string;
}

interface TicketHold {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    internal_notes: string | null;
    status: HoldStatus;
    expires_at: string | null;
    created_at: string;
    event_occurrence?: EventOccurrence;
    organizer?: Organizer;
    creator?: User;
    allocations: AllocationData[];
    purchase_links?: PurchaseLink[];
}

interface HoldAnalytics {
    totalAllocated: number;
    totalPurchased: number;
    totalRemaining: number;
    utilizationRate: number;
    linkCount: number;
    activeLinkCount: number;
}

const props = defineProps<{
    ticketHold: TicketHold;
    analytics: HoldAnalytics;
}>();

const { formatDate } = useTicketHoldFormatters();

const showReleaseModal = ref(false);
const showDeleteModal = ref(false);

const formatPricingMode = (mode: PricingMode): string => {
    const labels: Record<PricingMode, string> = {
        original: 'Original Price',
        fixed: 'Fixed Price',
        percentage_discount: 'Percentage Discount',
        free: 'Free',
    };
    return labels[mode] || mode;
};

const formatPrice = (cents: number): string => {
    return `$${(cents / 100).toFixed(2)}`;
};

const formatEffectivePrice = (allocation: AllocationData): string => {
    const originalPrice = allocation.ticket_definition?.price || 0;

    switch (allocation.pricing_mode) {
        case 'original':
            return formatPrice(originalPrice);
        case 'fixed':
            return allocation.custom_price !== null
                ? formatPrice(allocation.custom_price)
                : '-';
        case 'percentage_discount':
            if (allocation.discount_percentage !== null) {
                const discountedPrice = Math.round(
                    originalPrice * (1 - allocation.discount_percentage / 100)
                );
                return `${formatPrice(discountedPrice)} (${allocation.discount_percentage}% off)`;
            }
            return '-';
        case 'free':
            return '$0.00';
        default:
            return '-';
    }
};

const confirmRelease = () => {
    showReleaseModal.value = true;
};

const confirmDelete = () => {
    showDeleteModal.value = true;
};

const releaseHold = () => {
    router.post(route('admin.ticket-holds.release', props.ticketHold.id), {}, {
        preserveState: false,
        onSuccess: () => {
            showReleaseModal.value = false;
        },
    });
};

const deleteHold = () => {
    router.delete(route('admin.ticket-holds.destroy', props.ticketHold.id), {
        preserveState: false,
        onSuccess: () => {
            // Will redirect to index
        },
    });
};
</script>
