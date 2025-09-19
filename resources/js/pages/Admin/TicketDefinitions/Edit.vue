<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getTranslation, currentLocale as importedCurrentLocale } from '@/Utils/i18n';

interface StatusOption {
    value: string;
    label: string;
}

interface AvailableLocales {
    [key: string]: string;
}

// For the incoming prop
import type { TicketDefinitionProp, MembershipLevel, MembershipDiscount } from '@/types/ticket'; // TicketDefinitionEditFormData removed as form structure defined directly
import type { EventOccurrenceOption } from '@/types/ticket';
import MembershipDiscountConfig from '@/components/Admin/MembershipDiscountConfig.vue';

const props = defineProps<{
    ticketDefinition: TicketDefinitionProp;
    statuses: StatusOption[];
    availableLocales: AvailableLocales;
    timezones: string[]; // Added
    eventOccurrences: EventOccurrenceOption[];
    membershipLevels: MembershipLevel[];
    membershipDiscounts: MembershipDiscount[];
    errors?: Record<string, string>;
}>();

const currentLocaleForDisplay = ref(importedCurrentLocale.value || Object.keys(props.availableLocales)[0] || 'en');
const activeLocaleTab = ref(currentLocaleForDisplay.value);

// Define a more specific type for the form, including timezone
interface EditFormType {
    _method: 'PUT';
    name: Record<string, string>;
    description: Record<string, string>;
    price: number | string | undefined;
    currency: string;
    total_quantity: number | string | undefined;
    status: string;
    availability_window_start: string;
    availability_window_end: string;
    min_per_order: number;
    max_per_order: number | string | undefined;
    timezone: string | null;
    event_occurrence_ids: number[] | null;
    membership_discounts: MembershipDiscount[];
    [key: string]: any; // Index signature to satisfy FormDataType constraint
}

const form = useForm<EditFormType>({
    _method: 'PUT',
    name: {} as Record<string, string>,
    description: {} as Record<string, string>,
    price: typeof props.ticketDefinition?.price === 'number' && props.ticketDefinition.price !== null
        ? props.ticketDefinition.price / 100 // Convert cents to display value
        : undefined,
    currency: props.ticketDefinition?.currency || 'HKD', // Initialize with prop or default
    total_quantity: props.ticketDefinition?.total_quantity ?? undefined,
    status: props.ticketDefinition?.status ?? (props.statuses?.[0]?.value || 'draft'),
    availability_window_start: props.ticketDefinition?.availability_window_start || '',
    availability_window_end: props.ticketDefinition?.availability_window_end || '',
    min_per_order: props.ticketDefinition?.min_per_order ?? 1,
    max_per_order: props.ticketDefinition?.max_per_order ?? undefined,
    timezone: props.ticketDefinition?.timezone ?? null, // Added and initialized
    event_occurrence_ids: props.ticketDefinition?.event_occurrence_ids ?? [],
    membership_discounts: props.membershipDiscounts || [],
    // metadata: props.ticketDefinition?.metadata || null, // Removed
});

// Initialize and populate translatable fields
if (props.availableLocales) {
    Object.keys(props.availableLocales).forEach(locale => {
        form.name[locale] = getTranslation(props.ticketDefinition?.name, locale) || '';
        form.description[locale] = getTranslation(props.ticketDefinition?.description, locale) || '';
    });
}

const statusOptions = computed(() => {
    return props.statuses?.map(status => ({ value: status.value, label: status.label })) ?? [];
});

const localeTabs = computed(() => {
    return Object.entries(props.availableLocales || {}).map(([key, label]) => ({
        key,
        label: label as string, // Cast label to string
    }));
});

const submit = () => {
    if (!props.ticketDefinition?.id) {
        alert('Ticket definition ID is missing. Cannot update.');
        return;
    }
    form.transform(data => ({
        ...data,
        price: (typeof data.price === 'number' && data.price !== null)
            ? Math.round(data.price * 100) // Convert display value back to cents
            : data.price,
    })).post(route('admin.ticket-definitions.update', props.ticketDefinition.id), {
        onSuccess: () => {
            alert('Ticket definition updated successfully.');
        },
        onError: (formErrors) => {
            console.error("Update Error:", formErrors);
            const errorMessages = Object.values(formErrors).join('\n');
            alert(errorMessages || 'Please check the form for errors.');
        }
    });
};

</script>

<template>
    <Head title="Edit Ticket Definition" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                                Edit Ticket Definition: {{ getTranslation(props.ticketDefinition.name, currentLocaleForDisplay) }}
                            </h1>
                            <Link :href="route('admin.ticket-definitions.index')">
                                <Button variant="outline">Back to List</Button>
                            </Link>
                        </div>
                        <p class="-mt-4 mb-6 text-sm text-gray-700 dark:text-gray-300">
                            Modify the details of this ticket definition.
                        </p>

                        <form @submit.prevent="submit">
                             <Card class="mb-6">
                                <CardHeader>
                                    <CardTitle>Translatable Information</CardTitle>
                                </CardHeader>
                                <CardContent class="py-5 px-6">
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
                                        <div class="space-y-6 mt-4">
                                            <div>
                                                <Label :for="`name_${tab.key}`">Name ({{ tab.label }})</Label>
                                                <Input :id="`name_${tab.key}`" type="text" v-model="form.name[tab.key]" class="mt-1 block w-full" />
                                                <InputError :message="form.errors[`name.${tab.key}`]" class="mt-1" />
                                            </div>
                                            <div>
                                                <Label :for="`description_${tab.key}`">Description ({{ tab.label }})</Label>
                                                <textarea :id="`description_${tab.key}`" v-model="form.description[tab.key]" rows="4" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                                                <InputError :message="form.errors[`description.${tab.key}`]" class="mt-1" />
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card class="mb-6">
                                <CardHeader>
                                    <CardTitle>Basic Information</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-6">
                                    <div>
                                        <Label for="price">Price</Label>
                                        <Input id="price" type="number" step="0.01" v-model.number="form.price" placeholder="e.g., 10.00" />
                                        <InputError :message="form.errors.price" class="mt-1" />
                                    </div>

                                    <div>
                                        <Label for="currency">Currency</Label>
                                        <Input id="currency" type="text" v-model="form.currency" placeholder="e.g., USD" maxlength="3" />
                                        <InputError :message="form.errors.currency" class="mt-1" />
                                    </div>

                                    <div>
                                        <Label for="total_quantity">Quantity Available (leave blank for unlimited)</Label>
                                        <Input id="total_quantity" type="number" v-model.number="form.total_quantity" placeholder="e.g., 100" />
                                        <InputError :message="form.errors.total_quantity" class="mt-1" />
                                    </div>

                                    <div>
                                        <Label for="status">Status</Label>
                                        <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option v-for="statusItem in statusOptions" :key="statusItem.value" :value="statusItem.value">
                                                {{ statusItem.label }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.status" class="mt-1" />
                                    </div>

                                    <div>
                                        <Label for="timezone">Timezone</Label>
                                        <select id="timezone" v-model="form.timezone" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option :value="null">-- Select Timezone (uses server default) --</option>
                                            <option v-for="tz in props.timezones" :key="tz" :value="tz">
                                                {{ tz }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.timezone" class="mt-1" />
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <Label for="availability_window_start">Availability Window Start (Local Time)</Label>
                                            <Input id="availability_window_start" type="datetime-local" v-model="form.availability_window_start" />
                                            <InputError :message="form.errors.availability_window_start" class="mt-1" />
                                        </div>
                                        <div>
                                            <Label for="availability_window_end">Availability Window End (Local Time)</Label>
                                            <Input id="availability_window_end" type="datetime-local" v-model="form.availability_window_end" />
                                            <InputError :message="form.errors.availability_window_end" class="mt-1" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <Label for="min_per_order">Min Per Order</Label>
                                            <Input id="min_per_order" type="number" v-model.number="form.min_per_order" placeholder="Default: 1" min="1"/>
                                            <InputError :message="form.errors.min_per_order" class="mt-1" />
                                        </div>
                                        <div>
                                            <Label for="max_per_order">Max Per Order (optional)</Label>
                                            <Input id="max_per_order" type="number" v-model.number="form.max_per_order" placeholder="e.g., 10" min="1"/>
                                            <InputError :message="form.errors.max_per_order" class="mt-1" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>



                            <Card class="mb-6">
                                <CardHeader>
                                    <CardTitle>Event Associations</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-6">
                                    <div>
                                        <Label for="event_occurrence_ids">Associated Event Occurrences (optional)</Label>
                                        <select
                                            id="event_occurrence_ids"
                                            v-model="form.event_occurrence_ids"
                                            multiple
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            size="6"
                                        >
                                            <option v-for="occurrence in props.eventOccurrences" :key="occurrence.id" :value="occurrence.id">
                                                {{ occurrence.event_name }} - {{ occurrence.name }}
                                                <span v-if="occurrence.start_at">({{ occurrence.start_at }})</span>
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.event_occurrence_ids" class="mt-1" />
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            Hold Ctrl/Cmd to select multiple occurrences. Leave empty to make this ticket available for all events.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Membership Discounts Configuration -->
                            <MembershipDiscountConfig
                                v-model="form.membership_discounts"
                                :membership-levels="props.membershipLevels"
                                :ticket-price="form.price ? Math.round(form.price * 100) : null"
                                :currency="form.currency"
                                :errors="form.errors"
                            />

                            <div class="mt-6 flex justify-end space-x-4">
                                <Link :href="route('admin.ticket-definitions.index')">
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : 'Update Ticket Definition' }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
