<script setup lang="ts">
import { computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { getTranslation } from '@/Utils/i18n';
import AllocationTable from './AllocationTable.vue';

type PricingMode = 'original' | 'fixed' | 'percentage_discount' | 'free';

interface AllocationFormData {
    ticket_definition_id: number | string;
    allocated_quantity: number | string;
    pricing_mode: PricingMode;
    custom_price: number | null;
    discount_percentage: number | null;
}

interface TicketHoldFormData {
    event_occurrence_id: number | string;
    organizer_id: number | string | null;
    name: string;
    description: string;
    internal_notes: string;
    expires_at: string;
    allocations: AllocationFormData[];
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

interface Props {
    form: InertiaForm<TicketHoldFormData>;
    occurrences: EventOccurrence[];
    organizers: Organizer[];
    availableTickets?: TicketDefinition[];
    isEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isEdit: false,
    availableTickets: () => [],
});

const emit = defineEmits<{
    (e: 'occurrenceChange', occurrenceId: number | string): void;
    (e: 'submit'): void;
}>();

const formatOccurrenceLabel = (occurrence: EventOccurrence): string => {
    const eventName = getTranslation(occurrence.event.name);
    const date = new Date(occurrence.start_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
    return `${eventName} - ${date}`;
};

watch(
    () => props.form.event_occurrence_id,
    (newValue) => {
        if (newValue) {
            emit('occurrenceChange', newValue);
        }
    }
);

const handleSubmit = () => {
    emit('submit');
};

const formErrors = computed(() => {
    const errors: Record<string, string> = {};
    Object.entries(props.form.errors).forEach(([key, value]) => {
        errors[key] = value as string;
    });
    return errors;
});
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Left Column -->
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Hold Information</h3>

                <!-- Event Occurrence -->
                <div>
                    <label for="event_occurrence_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Event Occurrence *
                    </label>
                    <select
                        v-model="form.event_occurrence_id"
                        id="event_occurrence_id"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        :class="{ 'border-red-500': form.errors.event_occurrence_id }"
                        :disabled="isEdit"
                    >
                        <option value="">Select an event occurrence</option>
                        <option
                            v-for="occurrence in occurrences"
                            :key="occurrence.id"
                            :value="occurrence.id"
                        >
                            {{ formatOccurrenceLabel(occurrence) }}
                        </option>
                    </select>
                    <p v-if="form.errors.event_occurrence_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.event_occurrence_id }}
                    </p>
                </div>

                <!-- Organizer -->
                <div>
                    <label for="organizer_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Organizer (Optional)
                    </label>
                    <select
                        v-model="form.organizer_id"
                        id="organizer_id"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        :class="{ 'border-red-500': form.errors.organizer_id }"
                    >
                        <option :value="null">No organizer (Admin hold)</option>
                        <option
                            v-for="organizer in organizers"
                            :key="organizer.id"
                            :value="organizer.id"
                        >
                            {{ getTranslation(organizer.name) }}
                        </option>
                    </select>
                    <p v-if="form.errors.organizer_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.organizer_id }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Assign to an organizer to restrict access
                    </p>
                </div>

                <!-- Hold Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Hold Name *
                    </label>
                    <input
                        type="text"
                        v-model="form.name"
                        id="name"
                        maxlength="255"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        :class="{ 'border-red-500': form.errors.name }"
                        placeholder="e.g., VIP Sponsor Hold, Press Tickets"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Description
                    </label>
                    <textarea
                        v-model="form.description"
                        id="description"
                        rows="3"
                        maxlength="5000"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        :class="{ 'border-red-500': form.errors.description }"
                        placeholder="Describe the purpose of this hold..."
                    ></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.description }}
                    </p>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Additional Details</h3>

                <!-- Internal Notes -->
                <div>
                    <label for="internal_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Internal Notes
                    </label>
                    <textarea
                        v-model="form.internal_notes"
                        id="internal_notes"
                        rows="3"
                        maxlength="5000"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        :class="{ 'border-red-500': form.errors.internal_notes }"
                        placeholder="Notes visible only to admins..."
                    ></textarea>
                    <p v-if="form.errors.internal_notes" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.internal_notes }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        These notes are for internal use only
                    </p>
                </div>

                <!-- Expires At -->
                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Expiration Date
                    </label>
                    <input
                        type="datetime-local"
                        v-model="form.expires_at"
                        id="expires_at"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        :class="{ 'border-red-500': form.errors.expires_at }"
                    />
                    <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.expires_at }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Leave empty for no expiration. After expiration, held tickets return to public pool.
                    </p>
                </div>
            </div>
        </div>

        <!-- Allocations Section -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Ticket Allocations</h3>
            <div v-if="!form.event_occurrence_id" class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                Select an event occurrence to configure ticket allocations.
            </div>
            <AllocationTable
                v-else
                v-model="form.allocations"
                :available-tickets="availableTickets"
                :errors="formErrors"
            />
        </div>

        <!-- Form Actions -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <div class="flex justify-end space-x-3">
                <Link
                    :href="route('admin.ticket-holds.index')"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ form.processing ? (isEdit ? 'Updating...' : 'Creating...') : (isEdit ? 'Update Hold' : 'Create Hold') }}
                </button>
            </div>
        </div>
    </form>
</template>
