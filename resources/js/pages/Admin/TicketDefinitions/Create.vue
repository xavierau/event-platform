<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { currentLocale } from '@/Utils/i18n';

interface StatusOption {
    value: string;
    label: string;
}

interface AvailableLocales {
    [key: string]: string;
}

interface TicketDefinitionCreateFormData {
    name: Record<string, string>;
    description: Record<string, string>;
    price: number | undefined;
    total_quantity: number | undefined;
    status: string;
    availability_window_start: string;
    availability_window_end: string;
    min_per_order: number;
    max_per_order: number | undefined;
    metadata: Record<string, any> | undefined;
    [key: string]: any; // Index signature for form.errors flexibility
}

const props = defineProps<{
    statuses: StatusOption[];
    availableLocales: AvailableLocales;
    errors?: Record<string, string>;
}>();

const localeTabs = computed(() => Object.entries(props.availableLocales).map(([key, label]) => ({ key, label: label as string })));
const activeLocaleTab = ref(currentLocale.value || (localeTabs.value.length > 0 ? localeTabs.value[0].key : 'en'));

const form = useForm<TicketDefinitionCreateFormData>({
    name: Object.fromEntries(Object.keys(props.availableLocales).map(locale => [locale, ''])) as Record<string, string>,
    description: Object.fromEntries(Object.keys(props.availableLocales).map(locale => [locale, ''])) as Record<string, string>,
    price: undefined,
    total_quantity: undefined,
    status: (props.statuses && props.statuses.length > 0 ? props.statuses.find(s => s.value === 'active')?.value || props.statuses[0].value : 'draft'),
    availability_window_start: '',
    availability_window_end: '',
    min_per_order: 1,
    max_per_order: undefined,
    metadata: undefined,
});

const statusOptions = computed(() => {
    return props.statuses.map(status => ({ value: status.value, label: status.label }));
});

const submit = () => {
    form.transform(data => ({
        ...data,
        price: (typeof data.price === 'number' && data.price !== null)
               ? Math.round(data.price * 100)
               : data.price, // Send original value (e.g. undefined) if not a valid number for conversion
    })).post(route('admin.ticket-definitions.store'), {
        onSuccess: () => {
            alert('Ticket definition created successfully.');
            // Optionally reset form: form.reset();
        },
        onError: (formErrors) => {
            console.error("Create Error:", formErrors);
            const errorMessages = Object.values(formErrors).join(' \n ');
            alert(errorMessages || 'Please check the form for errors.');
            // If price transformation caused an issue, and errors are on 'price',
            // you might need to revert form.price to its decimal display value if you don't redirect.
            // However, onSuccess typically leads to a redirect or form reset.
        }
    });
};

</script>

<template>
    <Head title="Create Ticket Definition" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                                Create Ticket Definition
                            </h1>
                            <Link :href="route('admin.ticket-definitions.index')">
                                <Button variant="outline">Back to List</Button>
                            </Link>
                        </div>

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
                            <Card >
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
                                        <Label for="total_quantity">Total Quantity Available (leave blank for unlimited)</Label>
                                        <Input id="total_quantity" type="number" v-model.number="form.total_quantity" placeholder="e.g., 100" />
                                        <InputError :message="form.errors.total_quantity" class="mt-1" />
                                    </div>

                                    <div>
                                        <Label for="status">Status</Label>
                                        <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option disabled value="">Select status</option>
                                            <option v-for="statusItem in statusOptions" :key="statusItem.value" :value="statusItem.value">
                                                {{ statusItem.label }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.status" class="mt-1" />
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <Label for="availability_window_start">Availability Window Start</Label>
                                            <Input id="availability_window_start" type="datetime-local" v-model="form.availability_window_start" />
                                            <InputError :message="form.errors.availability_window_start" class="mt-1" />
                                        </div>
                                        <div>
                                            <Label for="availability_window_end">Availability Window End</Label>
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


                            <div class="mt-6 flex justify-end space-x-4">
                                <Link :href="route('admin.ticket-definitions.index')">
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : 'Create Ticket Definition' }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
