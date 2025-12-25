<template>
    <Head :title="`Edit: ${ticketHold.name}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="`Edit Hold: ${ticketHold.name}`" subtitle="Update ticket hold details">
                            <template #actions>
                                <Link :href="route('admin.ticket-holds.show', ticketHold.id)" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition mr-2">
                                    View Hold
                                </Link>
                                <Link :href="route('admin.ticket-holds.index')" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Back to Holds
                                </Link>
                            </template>
                        </PageHeader>

                        <div class="mt-8 max-w-4xl">
                            <HoldForm
                                :form="form"
                                :occurrences="occurrences"
                                :organizers="organizers"
                                :available-tickets="availableTickets"
                                :is-edit="true"
                                @occurrence-change="loadAvailableTickets"
                                @submit="updateHold"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import HoldForm from './components/HoldForm.vue';

type PricingMode = 'original' | 'fixed' | 'percentage_discount' | 'free';
type HoldStatus = 'active' | 'expired' | 'released' | 'exhausted';

interface AllocationFormData {
    ticket_definition_id: number | string;
    allocated_quantity: number | string;
    pricing_mode: PricingMode;
    custom_price: number | null;
    discount_percentage: number | null;
}

interface AllocationData {
    id: number;
    ticket_definition_id: number;
    allocated_quantity: number;
    purchased_quantity: number;
    pricing_mode: PricingMode;
    custom_price: number | null;
    discount_percentage: number | null;
}

interface TicketDefinition {
    id: number;
    name: Record<string, string> | string;
    price: number;
    available_quantity?: number;
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

interface TicketHold {
    id: number;
    uuid: string;
    event_occurrence_id: number;
    organizer_id: number | null;
    name: string;
    description: string | null;
    internal_notes: string | null;
    status: HoldStatus;
    expires_at: string | null;
    allocations: AllocationData[];
}

const props = defineProps<{
    ticketHold: TicketHold;
    occurrences: EventOccurrence[];
    organizers: Organizer[];
    availableTickets: TicketDefinition[];
}>();

const availableTickets = ref<TicketDefinition[]>(props.availableTickets || []);

const formatDateForInput = (dateString: string | null): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
};

const mapAllocationsToFormData = (allocations: AllocationData[]): AllocationFormData[] => {
    return allocations.map((allocation) => ({
        ticket_definition_id: allocation.ticket_definition_id,
        allocated_quantity: allocation.allocated_quantity,
        pricing_mode: allocation.pricing_mode,
        custom_price: allocation.custom_price,
        discount_percentage: allocation.discount_percentage,
    }));
};

const form = useForm({
    event_occurrence_id: props.ticketHold.event_occurrence_id as number | string,
    organizer_id: props.ticketHold.organizer_id as number | string | null,
    name: props.ticketHold.name,
    description: props.ticketHold.description || '',
    internal_notes: props.ticketHold.internal_notes || '',
    expires_at: formatDateForInput(props.ticketHold.expires_at),
    allocations: mapAllocationsToFormData(props.ticketHold.allocations || []),
});

const loadAvailableTickets = async (occurrenceId: number | string) => {
    if (!occurrenceId) {
        availableTickets.value = [];
        return;
    }

    try {
        const response = await fetch(route('admin.api.ticket-holds.available-tickets', { occurrence: occurrenceId }));
        if (response.ok) {
            availableTickets.value = await response.json();
        } else {
            availableTickets.value = [];
        }
    } catch {
        // Silently handle fetch errors - tickets will show empty state
        availableTickets.value = [];
    }
};

const updateHold = () => {
    form.put(route('admin.ticket-holds.update', props.ticketHold.id), {
        onSuccess: () => {
            // Redirect will be handled by the controller
        },
    });
};
</script>
