<script setup lang="ts">
// --- Prop Types & Interfaces ---
// For ticket assignments within this occurrence
interface OccurrenceTicketAssignment {
    ticket_definition_id: number;
    name?: string; // For display convenience, fetched from main TicketDefinition
    original_price?: number; // For display convenience
    original_currency_code?: string; // For display convenience
    quantity_for_occurrence: number | undefined;
    price_override: number | undefined; // In cents
    // availability_status_for_occurrence: string | null; // Potentially managed by backend
}

import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
// Assuming i18n utility exists, similar to other forms
import { currentLocale as i18nLocale } from '@/Utils/i18n';
import { Textarea } from '@/components/ui/textarea'; // Assuming Textarea component exists
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'; // Assuming Select components

import TicketDefinitionMiniForm from './TicketDefinitionMiniForm.vue';
import TicketDefinitionSelector from './TicketDefinitionSelector.vue';
import type { TicketDefinitionOption as SelectorTicketDefinitionOption } from './TicketDefinitionSelector.vue';
import type { StatusOption as TicketStatusOption } from './TicketDefinitionMiniForm.vue';

// --- Prop Types ---
interface VenueOption {
    id: number;
    name: string; // Expecting translated name if applicable, or a generic name
}

interface StatusOption {
    value: string;
    label: string;
}

interface AvailableLocales {
    [key: string]: string; // e.g. { en: "English", "zh-TW": "繁體中文" }
}

// This interface should align with the EventOccurrenceData DTO structure
// It's directly mutated, so it's part of a parent form's reactive state.
interface EventOccurrenceFormData {
    id?: number | string; // Optional ID, might be a temporary string for new items
    name: Record<string, string>;
    description: Record<string, string>;
    venue_id: number | null;
    start_at: string; // ISO string or format suitable for datetime-local
    end_at: string;   // ISO string or format suitable for datetime-local
    timezone: string | null;
    capacity: number | null;
    status: string;
    is_online: boolean;
    online_meeting_link: string | null;
    // Any errors associated with this specific occurrence's fields, populated by parent form
    errors?: Record<string, string>;
    // To hold associated ticket definition IDs and their pivot data (simplified for now)
    ticket_definitions?: Array<{ id: number; quantity_for_occurrence?: number | null; price_override?: number | null }>;
    assigned_tickets: OccurrenceTicketAssignment[]; // Holds tickets assigned to this occurrence
    [key: string]: any; // To allow other properties if needed
}

const props = defineProps<{
    occurrence: EventOccurrenceFormData; // This will be form.occurrences[index] from parent
    index: number;
    availableVenues: VenueOption[];
    availableStatuses: StatusOption[];
    availableLocales: AvailableLocales;

    // Props needed for the ticket management components
    allAvailableTicketDefinitions: SelectorTicketDefinitionOption[]; // All possible tickets to select from
    ticketDefinitionStatuses: TicketStatusOption[]; // Statuses for new TicketDefinitions (via mini form)
}>();

const emit = defineEmits(['removeOccurrence', 'newTicketDefinitionCreated']);

// --- Modal Visibility State ---
const showTicketMiniForm = ref(false);
const showTicketSelector = ref(false);

// --- Translatable Fields Logic ---
const localeTabs = computed(() => Object.entries(props.availableLocales).map(([key, label]) => ({ key, label })));
const activeLocaleTab = ref(i18nLocale.value || (localeTabs.value.length > 0 ? localeTabs.value[0].key : 'en'));

// --- Computed properties for v-model on translatable fields ---
const currentName = computed({
    get: () => props.occurrence.name[activeLocaleTab.value] || '',
    set: (value) => {
        props.occurrence.name[activeLocaleTab.value] = value;
    }
});

const currentDescription = computed({
    get: () => props.occurrence.description[activeLocaleTab.value] || '',
    set: (value) => {
        props.occurrence.description[activeLocaleTab.value] = value;
    }
});

// --- Computed properties for nullable fields to interface with v-model ---
const computedTimezone = computed({
    get: () => props.occurrence.timezone || '',
    set: (value: string) => {
        props.occurrence.timezone = value === '' ? null : value;
    }
});

const computedCapacity = computed({
    get: () => props.occurrence.capacity === null || props.occurrence.capacity === undefined ? '' : props.occurrence.capacity.toString(),
    set: (value: string) => {
        const num = parseInt(value, 10);
        props.occurrence.capacity = isNaN(num) ? null : num;
    }
});

const computedOnlineMeetingLink = computed({
    get: () => props.occurrence.online_meeting_link || '',
    set: (value: string) => {
        props.occurrence.online_meeting_link = value === '' ? null : value;
    }
});

const removeThisOccurrence = () => {
    emit('removeOccurrence', props.index);
};

// Helper to get error message for a field
// Parent form should populate occurrence.errors
const fieldError = (fieldName: string) => {
    const key = `${fieldName}`; // Adjust if parent prefixes with `occurrences.${props.index}.`
    return props.occurrence.errors?.[key];
};
const translatableFieldError = (fieldName: string, locale: string) => {
    const key = `${fieldName}.${locale}`;
    return props.occurrence.errors?.[key];
}

// --- Ticket Management Logic ---

const handleTicketDefinitionCreated = (newTicketDefData: any) => {
    // Parent needs to be informed to refresh `allAvailableTicketDefinitions`
    // and then potentially auto-assign this new one here.
    emit('newTicketDefinitionCreated', newTicketDefData);
    // For now, just close. Parent will handle data refresh and re-render.
    showTicketMiniForm.value = false;
    // Optionally: try to find the full newTicketDef in the (soon to be updated) allAvailableTicketDefinitions
    // and add it to props.occurrence.assigned_tickets
};

const handleTicketDefinitionsSelected = (selectedIds: number[]) => {
    const newAssignments: OccurrenceTicketAssignment[] = [];
    selectedIds.forEach(id => {
        const existingAssignment = props.occurrence.assigned_tickets.find(at => at.ticket_definition_id === id);
        if (existingAssignment) {
            newAssignments.push(existingAssignment);
        } else {
            const ticketDef = props.allAvailableTicketDefinitions.find(td => td.id === id);
            newAssignments.push({
                ticket_definition_id: id,
                name: ticketDef?.name, // Store name for easy display
                original_price: ticketDef?.price, // Store original price
                original_currency_code: ticketDef?.currency_code,
                quantity_for_occurrence: undefined, // Default, user can edit
                price_override: undefined, // Default, user can edit
            });
        }
    });
    props.occurrence.assigned_tickets = newAssignments;
    showTicketSelector.value = false;
};

const removeAssignedTicket = (ticketDefIdToRemove: number) => {
    props.occurrence.assigned_tickets = props.occurrence.assigned_tickets.filter(
        (at) => at.ticket_definition_id !== ticketDefIdToRemove
    );
};

// Helper to get ticket name from allAvailableTicketDefinitions
const getTicketDefinitionName = (id: number): string => {
    return props.allAvailableTicketDefinitions.find(td => td.id === id)?.name || 'Unknown Ticket';
};

const formatPrice = (price: number | null | undefined, currencyCode: string | null | undefined) => {
    if (price === null || price === undefined || !currencyCode) return 'N/A';
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currencyCode }).format(price / 100);
    } catch {
        return `${(price / 100).toFixed(2)} ${currencyCode}`;
    }
};

// --- Error Handling Helpers ---
const assignedTicketFieldError = (index: number, fieldName: keyof OccurrenceTicketAssignment) => {
    // Error key might be like: assigned_tickets.0.quantity_for_occurrence
    return props.occurrence.errors?.[`assigned_tickets.${index}.${fieldName}`];
}

</script>

<template>
    <div class="p-4 md:p-6 border rounded-lg shadow space-y-6 mb-6 bg-card text-card-foreground">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Event Occurrence #{{ index + 1 }}</h3>
            <Button type="button" variant="destructive" @click="removeThisOccurrence" size="sm">
                Remove Occurrence
            </Button>
        </div>

        <!-- Translatable Fields: Name and Description -->
        <div class="border-b border-border mb-4">
            <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                <button
                    v-for="tab in localeTabs"
                    :key="tab.key"
                    @click.prevent="activeLocaleTab = tab.key"
                    :class="[
                        activeLocaleTab === tab.key
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                        'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm focus:outline-none'
                    ]"
                    type="button"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <div v-for="tab in localeTabs" :key="tab.key" v-show="activeLocaleTab === tab.key" class="space-y-4">
            <div>
                <Label :for="`occurrence_name_${index}_${tab.key}`">Occurrence Name ({{ tab.label }}) <span class="text-xs text-muted-foreground">(Optional)</span></Label>
                <Input
                    :id="`occurrence_name_${index}_${tab.key}`"
                    type="text"
                    v-model="currentName"
                    class="mt-1 block w-full"
                    placeholder="e.g., Afternoon Session, Day 1 Workshop"
                />
                <InputError :message="translatableFieldError('name', tab.key)" class="mt-1" />
            </div>
            <div>
                <Label :for="`occurrence_description_${index}_${tab.key}`">Occurrence Description ({{ tab.label }}) <span class="text-xs text-muted-foreground">(Optional)</span></Label>
                <Textarea
                    :id="`occurrence_description_${index}_${tab.key}`"
                    v-model="currentDescription"
                    rows="3"
                    class="mt-1 block w-full"
                    placeholder="Specific details for this occurrence..."
                />
                <InputError :message="translatableFieldError('description', tab.key)" class="mt-1" />
            </div>
        </div>

        <!-- Non-Translatable Fields -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div>
                <Label :for="`occurrence_venue_${index}`" required>Venue</Label>
                 <Select v-model="occurrence.venue_id">
                    <SelectTrigger :id="`occurrence_venue_${index}`" class="mt-1 w-full">
                        <SelectValue placeholder="Select a venue" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="!availableVenues || availableVenues.length === 0" :value="null" disabled>
                            No venues available
                        </SelectItem>
                        <SelectItem v-for="venue in availableVenues" :key="venue.id" :value="venue.id">
                            {{ venue.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="fieldError('venue_id')" class="mt-1" />
            </div>

            <div>
                <Label :for="`occurrence_status_${index}`" required>Status</Label>
                <Select v-model="occurrence.status">
                     <SelectTrigger :id="`occurrence_status_${index}`" class="mt-1 w-full">
                        <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="status in availableStatuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="fieldError('status')" class="mt-1" />
            </div>

            <div>
                <Label :for="`occurrence_start_at_${index}`" required>Start Date & Time</Label>
                <Input
                    :id="`occurrence_start_at_${index}`"
                    type="datetime-local"
                    v-model="occurrence.start_at"
                    class="mt-1 block w-full"
                />
                <InputError :message="fieldError('start_at')" class="mt-1" />
            </div>

            <div>
                <Label :for="`occurrence_end_at_${index}`" required>End Date & Time</Label>
                <Input
                    :id="`occurrence_end_at_${index}`"
                    type="datetime-local"
                    v-model="occurrence.end_at"
                    class="mt-1 block w-full"
                />
                <InputError :message="fieldError('end_at')" class="mt-1" />
            </div>

             <div>
                <Label :for="`occurrence_timezone_${index}`">Timezone</Label>
                <Input
                    :id="`occurrence_timezone_${index}`"
                    type="text"
                    v-model="computedTimezone"
                    class="mt-1 block w-full"
                    placeholder="e.g., America/New_York or UTC"
                />
                <InputError :message="fieldError('timezone')" class="mt-1" />
            </div>

            <div>
                <Label :for="`occurrence_capacity_${index}`">Capacity <span class="text-xs text-muted-foreground">(Optional)</span></Label>
                <Input
                    :id="`occurrence_capacity_${index}`"
                    type="number"
                    v-model="computedCapacity"
                    class="mt-1 block w-full"
                    placeholder="e.g., 100"
                    min="0"
                />
                <InputError :message="fieldError('capacity')" class="mt-1" />
            </div>

            <div class="md:col-span-2 space-y-2">
                 <div class="flex items-center space-x-2">
                    <Checkbox :id="`occurrence_is_online_${index}`" v-model:checked="occurrence.is_online" />
                    <Label :for="`occurrence_is_online_${index}`" class="font-normal">This occurrence is online</Label>
                </div>
                 <InputError :message="fieldError('is_online')" class="mt-1" />
            </div>


            <div v-if="occurrence.is_online" class="md:col-span-2">
                <Label :for="`occurrence_online_link_${index}`">Online Meeting Link</Label>
                <Input
                    :id="`occurrence_online_link_${index}`"
                    type="url"
                    v-model="computedOnlineMeetingLink"
                    class="mt-1 block w-full"
                    placeholder="e.g., https://zoom.us/j/..."
                />
                <InputError :message="fieldError('online_meeting_link')" class="mt-1" />
            </div>
        </div>

        <!-- Ticket Definition Management section -->
        <div class="mt-6 pt-6 border-t border-border">
            <div class="flex justify-between items-center mb-3">
                <h4 class="text-md font-semibold">Tickets for this Occurrence</h4>
                <div class="space-x-2">
                    <Button type="button" variant="outline" size="sm" @click="showTicketSelector = true">
                        Assign Tickets
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="showTicketMiniForm = true">
                        Create New Ticket Type
                    </Button>
                </div>
            </div>

            <div v-if="!occurrence.assigned_tickets || occurrence.assigned_tickets.length === 0" class="p-4 border border-dashed rounded-md text-center text-muted-foreground">
                No tickets assigned to this specific occurrence yet.
            </div>
            <div v-else class="space-y-4">
                <div v-for="(assignedTicket, ticketIndex) in occurrence.assigned_tickets" :key="assignedTicket.ticket_definition_id" class="p-4 border rounded-md bg-muted/30">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h5 class="font-semibold">{{ assignedTicket.name || getTicketDefinitionName(assignedTicket.ticket_definition_id) }}</h5>
                            <p class="text-xs text-muted-foreground">
                                Original Price: {{ formatPrice(assignedTicket.original_price, assignedTicket.original_currency_code) }}
                            </p>
                        </div>
                        <Button type="button" variant="ghost" size="sm" @click="removeAssignedTicket(assignedTicket.ticket_definition_id)" class="text-destructive hover:text-destructive-hover">
                            Remove
                        </Button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label :for="`assigned_ticket_qty_${index}_${ticketIndex}`">Quantity for this Occurrence</Label>
                            <Input :id="`assigned_ticket_qty_${index}_${ticketIndex}`" type="number" v-model.number="assignedTicket.quantity_for_occurrence" placeholder="Unlimited" class="mt-1 block w-full" min="0"/>
                            <InputError :message="assignedTicketFieldError(ticketIndex, 'quantity_for_occurrence')" class="mt-1" />
                        </div>
                        <div>
                            <Label :for="`assigned_ticket_price_override_${index}_${ticketIndex}`">Price Override (in cents)</Label>
                            <Input :id="`assigned_ticket_price_override_${index}_${ticketIndex}`" type="number" v-model.number="assignedTicket.price_override" placeholder="Use original price" class="mt-1 block w-full" min="0"/>
                            <InputError :message="assignedTicketFieldError(ticketIndex, 'price_override')" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <TicketDefinitionMiniForm
            :show="showTicketMiniForm"
            :statuses="props.ticketDefinitionStatuses"
            :available-locales="props.availableLocales"
            @close="showTicketMiniForm = false"
            @ticket-definition-created="handleTicketDefinitionCreated"
        />

        <TicketDefinitionSelector
            :show="showTicketSelector"
            :available-ticket-definitions="props.allAvailableTicketDefinitions"
            :initially-selected-ids="occurrence.assigned_tickets.map(at => at.ticket_definition_id)"
            @close="showTicketSelector = false"
            @ticket-definitions-selected="handleTicketDefinitionsSelected"
        />
    </div>
</template>
