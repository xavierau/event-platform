<template>
    <Head title="Create Ticket Hold" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader title="Create New Ticket Hold" subtitle="Reserve tickets for private distribution">
                            <template #actions>
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
                                @occurrence-change="loadAvailableTickets"
                                @submit="createHold"
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
import { ref } from 'vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import HoldForm from './components/HoldForm.vue';

type PricingMode = 'original' | 'fixed' | 'percentage_discount' | 'free';

interface AllocationFormData {
    ticket_definition_id: number | string;
    allocated_quantity: number | string;
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

defineProps<{
    occurrences: EventOccurrence[];
    organizers: Organizer[];
}>();

const availableTickets = ref<TicketDefinition[]>([]);

const form = useForm({
    event_occurrence_id: '' as number | string,
    organizer_id: null as number | string | null,
    name: '',
    description: '',
    internal_notes: '',
    expires_at: '',
    allocations: [] as AllocationFormData[],
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

const createHold = () => {
    form.post(route('admin.ticket-holds.store'), {
        onSuccess: () => {
            // Redirect will be handled by the controller
        },
    });
};
</script>
