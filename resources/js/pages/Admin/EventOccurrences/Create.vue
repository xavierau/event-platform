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

const props = defineProps({
    event: {
        type: Object,
        required: true, // { id, name }
    },
    venues: {
        type: Array, // [{ id, name }]
        required: true,
    },
    availableLocales: {
        type: Object, // { en: 'English', ... }
        required: true,
    },
    occurrenceStatuses: {
        type: Array, // [{ value: 'scheduled', name: 'Scheduled' }, ...] - from Enum::cases()
        required: true,
    },
    errors: Object,
    pageTitle: String,
    breadcrumbs: Array,
});

const localeTabs = computed(() => Object.entries(props.availableLocales).map(([key, label]) => ({ key, label })));
const activeLocaleTab = ref(currentLocale.value || localeTabs.value[0]?.key || 'en');

const initialName = {};
const initialDescription = {};
localeTabs.value.forEach(tab => {
    initialName[tab.key] = '';
    initialDescription[tab.key] = '';
});

const form = useForm({
    event_id: props.event.id,
    name: initialName,
    description: initialDescription,
    start_at: '',
    end_at: '',
    venue_id: null as number | null,
    is_online: false,
    online_meeting_link: '',
    capacity: null as number | null,
    status: props.occurrenceStatuses.find(s => s.value === 'scheduled')?.value || props.occurrenceStatuses[0]?.value || '',
    timezone: props.occurrence?.timezone || 'Asia/Hong_Kong',
});

watch(() => form.is_online, (isOnline) => {
    if (isOnline) {
        form.venue_id = null; // Clear venue if online
    } else {
        form.online_meeting_link = ''; // Clear online link if not online
    }
});

const venueOptions = computed(() => {
    return [
        { value: null as number | null, label: 'Select a Venue (if applicable)' },
        ...props.venues.map(venue => ({ value: venue.id as number, label: venue.name as string }))
    ];
});

const statusOptions = computed(() => {
    return props.occurrenceStatuses.map(status => ({
        value: status.value,
        label: status.label
    }));
});

const submit = () => {
    form.post(route('admin.events.occurrences.store', { event: props.event.id }), {
    });
};
</script>

<template>
    <Head :title="props.pageTitle || 'Create Event Occurrence'" />
    <AppLayout :page-title="props.pageTitle" :breadcrumbs="props.breadcrumbs">
        <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <form @submit.prevent="submit" class="divide-y divide-gray-200 dark:divide-gray-700">
                    <!-- Header -->
                    <div class="p-6">
                        <h2 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Create New Occurrence for: {{ props.event.name }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Fill in the details for the new event occurrence.
                        </p>
                    </div>

                    <!-- Translatable Fields with Tabs -->
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
                                <InputError :message="form.errors[`name.${tab.key}`]" class="mt-1" />
                            </div>
                            <div class="mb-4">
                                <Label :for="'description_' + tab.key">{{ 'Description (' + tab.label + ')' }}</Label>
                                <RichTextEditor
                                    :id="'description_' + tab.key"
                                    v-model="form.description[tab.key]"
                                    class="mt-1 block w-full"
                                    :placeholder="'Detailed description in ' + tab.label + ' (optional)'"
                                />
                                <InputError :message="form.errors[`description.${tab.key}`]" class="mt-1" />
                            </div>
                        </div>
                        <InputError :message="form.errors.name" class="mt-1" />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <!-- Date & Time Fields -->
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

                    <!-- Location & Capacity Fields -->
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
                            <Input id="online_meeting_link" v-model="form.online_meeting_link" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError :message="form.errors.online_meeting_link" class="mt-1" />
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
                            <InputError :message="form.errors.capacity" class="mt-1" />
                        </div>

                        <!-- Timezone -->
                        <div class="col-span-6 sm:col-span-3">
                            <Label for="timezone">Timezone</Label>
                            <Input id="timezone" v-model="form.timezone" type="text" class="mt-1 block w-full" placeholder="e.g., Asia/Hong_Kong" />
                            <InputError :message="form.errors.timezone" class="mt-1" />
                        </div>
                    </div>

                     <!-- Status & Metadata -->
                    <div class="px-6 py-5">
                        <div class="mb-4">
                            <Label for="status">Status</Label>
                             <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.status" class="mt-1" />
                        </div>
                    </div>


                    <!-- Actions -->
                    <div class="p-6 flex justify-end space-x-3">
                        <Link :href="route('admin.events.occurrences.index', {event: props.event.id})" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            Cancel
                        </Link>
                        <Button :disabled="form.processing">
                            Create Occurrence
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
