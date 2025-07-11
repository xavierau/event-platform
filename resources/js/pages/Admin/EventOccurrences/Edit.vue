<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/Form/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { ref, computed, watch } from 'vue';
import { currentLocale } from '@/Utils/i18n';

// Ticket Management Components & Types
import TicketDefinitionMiniForm from '@/pages/Admin/Events/Partials/TicketDefinitionMiniForm.vue';
import TicketDefinitionSelector from '@/pages/Admin/Events/Partials/TicketDefinitionSelector.vue';
// TicketDefinitionOption now imported from centralized types
import type { StatusOption as TicketStatusOption } from '@/pages/Admin/Events/Partials/TicketDefinitionMiniForm.vue';

// Import centralized types
import type { OccurrenceTicketAssignment, TicketDefinitionOption } from '@/types/ticket';

// Define interfaces for props
interface EventProp {
    id: number;
    name: Record<string, string> | string; // name can be pre-fetched translated string or object
}

interface OccurrenceProp {
    id: number;
    name: Record<string, string>;
    description: Record<string, string>;
    start_at: string;
    end_at: string;
    venue_id: number | null;
    is_online: boolean;
    online_meeting_link: string | null;
    capacity: number | null;
    status: string;
    timezone: string;
}

interface VenueSelectItem {
    id: number;
    name: string; // Assuming simple name for select list items
}

interface LocaleInfo {
    [key: string]: string;
}

interface StatusItem {
    value: string;
    label: string;
}

interface BreadcrumbItem {
    title: string;
    href?: string;
}

interface PageProps {
    event: EventProp;
    occurrence: OccurrenceProp;
    venues: VenueSelectItem[];
    availableLocales: LocaleInfo;
    occurrenceStatuses: StatusItem[];
    errors?: Record<string, string>;
    pageTitle?: string;
    breadcrumbs?: BreadcrumbItem[];
    // Props for ticket management
    allAvailableTicketDefinitions: TicketDefinitionOption[];
    ticketDefinitionStatuses: TicketStatusOption[];
    assignedTickets: OccurrenceTicketAssignment[]; // Added as a top-level required prop
}

const props = defineProps<PageProps>();

// Props are now properly structured for ticket management

// Define interface for form data
interface OccurrenceFormData {
    _method: 'PUT';
    event_id: number;
    name: Record<string, string>;
    description: Record<string, string>;
    start_at: string;
    end_at: string;
    venue_id: number | null;
    is_online: boolean;
    online_meeting_link: string | null;
    capacity: number | null;
    status: string;
    timezone: string;
    assigned_tickets: OccurrenceTicketAssignment[]; // Added for form data
    [key: string]: any; // Add index signature for Inertia's FormDataType
}

const localeTabs = computed(() => Object.entries(props.availableLocales).map(([key, label]) => ({ key, label })));
const activeLocaleTab = ref(currentLocale.value || localeTabs.value[0]?.key || 'en');

const formatDateTimeForInput = (isoString: string | null | undefined): string => {
    if (!isoString) return '';
    try {
        const date = new Date(isoString);
        // Checks if the date is valid
        if (isNaN(date.getTime())) {
            // Fallback for strings that might be partially correct but not parsable as a Date
            return isoString.slice(0, 16);
        }
        // These methods return parts in the browser's local timezone
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');

        return `${year}-${month}-${day}T${hours}:${minutes}`;
    } catch (e) {
        console.error(`Could not parse date: ${isoString}`, e);
        // Fallback to simple slice if Date object construction fails
        return isoString.slice(0, 16);
    }
};

const initializeTranslatableField = (fieldData: Record<string, string> | undefined): Record<string, string> => {
    const translations: Record<string, string> = {};
    localeTabs.value.forEach(tab => {
        translations[tab.key] = fieldData && fieldData[tab.key] ? fieldData[tab.key] : '';
    });
    return translations;
};

const form = useForm<OccurrenceFormData>({
    _method: 'PUT',
    event_id: props.event.id,
    name: initializeTranslatableField(props.occurrence.name),
    description: initializeTranslatableField(props.occurrence.description),
    start_at: formatDateTimeForInput(props.occurrence.start_at),
    end_at: formatDateTimeForInput(props.occurrence.end_at),
    venue_id: props.occurrence.venue_id || null,
    is_online: props.occurrence.is_online || false,
    online_meeting_link: props.occurrence.online_meeting_link || '',
    capacity: props.occurrence.capacity ?? null,
    status: props.occurrence.status || (props.occurrenceStatuses.find(s => s.value === 'scheduled')?.value || props.occurrenceStatuses[0]?.value || ''),
    timezone: props.occurrence.timezone || 'Asia/Hong_Kong',
    assigned_tickets: props.assignedTickets ? JSON.parse(JSON.stringify(props.assignedTickets)) : [], // Use top-level prop
});

watch(() => form.is_online, (isOnline) => {
    if (isOnline) {
        form.venue_id = null;
    } else {
        form.online_meeting_link = '';
    }
});

// Watch for changes in assigned tickets to ensure reactivity
watch(() => form.assigned_tickets, () => {
    // Tickets have been updated - form is reactive
}, { deep: true });

const venueOptions = computed(() => {
    return [
        { value: '' as any, label: 'Select a Venue (if applicable)' },
        ...props.venues.map(venue => ({ value: venue.id, label: venue.name }))
    ];
});

const statusOptions = computed(() => {
    return props.occurrenceStatuses.map(status => ({
        value: status.value,
        label: status.label
    }));
});

const submit = () => {
    form.post(route('admin.occurrences.update', { occurrence: props.occurrence.id }), {
        // Consider preserveState: true, preserveScroll: true if appropriate after backend update
        onSuccess: () => {
            // Potentially show a success notification
        },
        onError: (formErrors) => {
            console.error("Error updating occurrence:", formErrors);
            // Potentially show an error notification
        }
    });
};

watch(() => props.occurrence, (newOccurrence) => {
    if (newOccurrence) {
        form.name = initializeTranslatableField(newOccurrence.name);
        form.description = initializeTranslatableField(newOccurrence.description);
        form.start_at = formatDateTimeForInput(newOccurrence.start_at);
        form.end_at = formatDateTimeForInput(newOccurrence.end_at);
        form.venue_id = newOccurrence.venue_id || null;
        form.is_online = newOccurrence.is_online || false;
        form.online_meeting_link = newOccurrence.online_meeting_link || '';
        form.capacity = newOccurrence.capacity ?? null;
        form.status = newOccurrence.status || (props.occurrenceStatuses.find(s => s.value === 'scheduled')?.value || props.occurrenceStatuses[0]?.value || '');
        form.timezone = newOccurrence.timezone || 'Asia/Hong_Kong';
        form.errors = {}; // Clear previous errors
        // No longer need to update from newOccurrence.assigned_tickets as it's a separate prop now.
        // If assignedTickets prop itself becomes reactive and can change, a separate watcher for it might be needed.
        // For now, assuming it's loaded once with the page.
        // If a refresh of assigned_tickets is needed without full page reload, that's a more complex scenario.
    }
}, { deep: true });


// --- Ticket Management Logic ---
const showTicketMiniForm = ref(false);
const showTicketSelector = ref(false);

// Modal state management
watch(() => showTicketSelector.value, () => {
    // Ticket selector modal state changed
});

watch(() => showTicketMiniForm.value, () => {
    // Ticket mini form modal state changed
});

// This event informs that a ticket was created.
// The parent page (served by Inertia controller) should ensure that `props.allAvailableTicketDefinitions`
// is up-to-date on the next page load or through a subsequent data refresh mechanism.
const handleTicketDefinitionCreated = (newTicketDefData: any) => {
    showTicketMiniForm.value = false;
    // Emitting an event to the parent might be needed if dynamic refresh without full page reload is required.
    // For now, relies on backend to update `allAvailableTicketDefinitions` for subsequent interactions.
    // router.reload({ only: ['allAvailableTicketDefinitions'] }); // Example if such partial reload is setup
    alert('Ticket definition "' + (newTicketDefData.name?.en || newTicketDefData.id) + '" created. It will be available in the selector after a page refresh or next visit.');
};

const handleTicketDefinitionsSelected = (selectedIds: number[]) => {
    const newAssignments: OccurrenceTicketAssignment[] = [];
    selectedIds.forEach(id => {
        const existingAssignment = form.assigned_tickets.find(at => at.ticket_definition_id === id);
        if (existingAssignment) {
            newAssignments.push(existingAssignment);
        } else {
            const ticketDef = props.allAvailableTicketDefinitions.find(td => td.id === id);
            if (ticketDef) {
                newAssignments.push({
                    ticket_definition_id: id,
                    name: ticketDef.name,
                    original_price: ticketDef.price,
                    original_currency_code: ticketDef.currency_code,
                    quantity_for_occurrence: undefined, // Default, user can edit
                    price_override: undefined, // Default, user can edit
                });
            } else {
                console.error('Ticket definition not found for ID:', id);
            }
        }
    });

    form.assigned_tickets = newAssignments;
    showTicketSelector.value = false;
};

const removeAssignedTicket = (ticketDefIdToRemove: number) => {
    form.assigned_tickets = form.assigned_tickets.filter(
        (at) => at.ticket_definition_id !== ticketDefIdToRemove
    );
};

const getTicketDefinitionName = (id: number): string => {
    return props.allAvailableTicketDefinitions.find(td => td.id === id)?.name || 'Unknown Ticket';
};

const formatPrice = (price: number | undefined | null, currencyCode: string | undefined | null): string => {
    if (price === null || price === undefined || currencyCode === null || currencyCode === undefined) return 'N/A';
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currencyCode }).format(price / 100);
    } catch {
        return ` ${(price / 100).toFixed(2)} ${currencyCode}`;
    }
};

const assignedTicketFieldError = (index: number, fieldName: keyof OccurrenceTicketAssignment) => {
    const errorKey = `assigned_tickets.${index}.${fieldName}`;
    return (form.errors as Record<string, string>)?.[errorKey];
};

</script>

<template>
    <Head :title="props.pageTitle || `Edit Occurrence: ${props.occurrence.name?.[activeLocaleTab] || props.occurrence.id}`" />
    <AppLayout :page-title="props.pageTitle || `Edit Occurrence for Event: ${props.event.name}`" :breadcrumbs="props.breadcrumbs">
        <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <form @submit.prevent="submit" class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div class="p-6">
                        <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Edit Occurrence <span class="text-indigo-600 dark:text-indigo-400">#{{ props.occurrence.id }}</span> for: {{ props.event.name }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Update the details for this event occurrence.
                        </p>
                    </div>

                    <div class="px-6 py-5">
                        <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button
                                    v-for="tab in localeTabs"
                                    :key="tab.key"
                                    @click.prevent="activeLocaleTab = tab.key"
                                    :class="[
                                        activeLocaleTab === tab.key
                                            ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-300'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600',
                                        'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm focus:outline-none'
                                    ]"
                                >
                                    {{ tab.label }}
                                </button>
                            </nav>
                        </div>

                        <div v-for="tab in localeTabs" :key="tab.key" v-show="activeLocaleTab === tab.key">
                            <div class="mb-4">
                                <Label :for="'name_' + tab.key">{{ 'Name (' + tab.label + ')' }}</Label>
                                <Input
                                    :id="'name_' + tab.key"
                                    v-model="form.name[tab.key]"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :placeholder="'Occurrence Name in ' + tab.label"
                                />
                                <InputError :message="(form.errors as Record<string, string>)[`name.${tab.key}`]" class="mt-1" />
                            </div>
                            <div class="mb-4">
                                <Label :for="'description_' + tab.key">{{ 'Description (' + tab.label + ')' }}</Label>
                                <RichTextEditor
                                    :id="'description_' + tab.key"
                                    v-model="form.description[tab.key]"
                                    class="mt-1 block w-full"
                                    :placeholder="'Detailed description in ' + tab.label + ' (optional)'"
                                />
                                <InputError :message="(form.errors as Record<string, string>)[`description.${tab.key}`]" class="mt-1" />
                            </div>
                        </div>
                        <InputError :message="form.errors.name" class="mt-1" />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <div class="px-6 py-5 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                        <div>
                            <Label for="start_at">Start Date & Time</Label>
                            <Input id="start_at" v-model="form.start_at" type="datetime-local" class="mt-1 block w-full" />
                            <InputError :message="form.errors.start_at" class="mt-1" />
                        </div>
                        <div>
                            <Label for="end_at">End Date & Time</Label>
                            <Input id="end_at" v-model="form.end_at" type="datetime-local" class="mt-1 block w-full" />
                            <InputError :message="form.errors.end_at" class="mt-1" />
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <div class="mb-4">
                            <Label class="flex items-center">
                                <Checkbox id="is_online" v-model:checked="form.is_online" />
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">This is an online occurrence</span>
                            </Label>
                            <InputError :message="form.errors.is_online" class="mt-1" />
                        </div>

                        <div v-if="form.is_online" class="mb-4">
                            <Label for="online_meeting_link">Online Meeting Link (e.g., Zoom, Google Meet)</Label>
                            <Input
                                id="online_meeting_link"
                                :model-value="form.online_meeting_link === null ? '' : form.online_meeting_link"
                                @update:model-value="form.online_meeting_link = ($event === '' || $event === null) ? null : String($event)"
                                type="url"
                                class="mt-1 block w-full"
                                placeholder="https://..."
                            />
                            <InputError :message="(form.errors as Record<string, string>).online_meeting_link" class="mt-1" />
                        </div>

                        <div v-if="!form.is_online" class="mb-4">
                            <Label for="venue_id">Venue</Label>
                            <select id="venue_id" v-model="form.venue_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option v-for="option in venueOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.venue_id" class="mt-1" />
                        </div>

                        <div class="mb-4">
                            <Label for="capacity">Capacity (leave blank for unlimited)</Label>
                            <Input
                                id="capacity"
                                :model-value="form.capacity === null ? undefined : form.capacity"
                                @update:model-value="val => form.capacity = (val === '' || val === undefined || val === null) ? null : Number(val)"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                                placeholder="e.g., 100"
                            />
                            <InputError :message="(form.errors as Record<string, string>).capacity" class="mt-1" />
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <Label for="timezone">Timezone</Label>
                            <Input id="timezone" v-model="form.timezone" type="text" class="mt-1 block w-full" placeholder="e.g., Asia/Hong_Kong" />
                            <InputError :message="form.errors.timezone" class="mt-1" />
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <div class="mb-4">
                            <Label for="status">Status</Label>
                            <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.status" class="mt-1" />
                        </div>
                    </div>

                    <!-- Ticket Management Section -->
                    <div class="px-6 py-5 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-base font-medium text-gray-900 dark:text-white">Manage Tickets for this Occurrence</h3>
                            <div class="space-x-2">
                                <Button type="button" variant="outline" size="sm" @click="showTicketSelector = true">
                                    Assign Existing Tickets
                                </Button>
                                <Button type="button" variant="outline" size="sm" @click="showTicketMiniForm = true">
                                    Create New Ticket Type
                                </Button>
                            </div>
                        </div>



                        <div v-if="!form.assigned_tickets || form.assigned_tickets.length === 0" class="p-4 border border-dashed rounded-md text-center text-gray-500 dark:text-gray-400">
                            No tickets assigned to this specific occurrence yet. (Count: {{ form.assigned_tickets?.length || 0 }})
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="(assignedTicket, ticketIndex) in form.assigned_tickets" :key="assignedTicket.ticket_definition_id" class="p-4 border rounded-md bg-gray-50 dark:bg-gray-700/50">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-semibold text-gray-800 dark:text-gray-100">{{ assignedTicket.name || getTicketDefinitionName(assignedTicket.ticket_definition_id) }}</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Original Price: {{ formatPrice(assignedTicket.original_price, assignedTicket.original_currency_code) }}
                                        </p>
                                    </div>
                                    <Button type="button" variant="ghost" size="sm" @click="removeAssignedTicket(assignedTicket.ticket_definition_id)" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400">
                                        Remove
                                    </Button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <Label :for="`assigned_ticket_qty_${ticketIndex}`">Quantity for Occurrence</Label>
                                        <Input :id="`assigned_ticket_qty_${ticketIndex}`" type="number" v-model.number="assignedTicket.quantity_for_occurrence" placeholder="Unlimited" class="mt-1 block w-full" min="0"/>
                                        <InputError :message="assignedTicketFieldError(ticketIndex, 'quantity_for_occurrence')" class="mt-1" />
                                    </div>
                                    <div>
                                        <Label :for="`assigned_ticket_price_override_${ticketIndex}`">Price Override (in cents)</Label>
                                        <Input :id="`assigned_ticket_price_override_${ticketIndex}`" type="number" v-model.number="assignedTicket.price_override" placeholder="Use original price" class="mt-1 block w-full" min="0"/>
                                        <InputError :message="assignedTicketFieldError(ticketIndex, 'price_override')" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 flex justify-end space-x-3">
                        <Link :href="route('admin.events.occurrences.index', {event: props.event.id})" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            Cancel
                        </Link>
                        <Button :disabled="form.processing">
                            Update Occurrence
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modals for Ticket Management -->
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
            :initially-selected-ids="form.assigned_tickets.map(at => at.ticket_definition_id)"
            @close="showTicketSelector = false"
            @ticket-definitions-selected="handleTicketDefinitionsSelected"
        />

    </AppLayout>
</template>
