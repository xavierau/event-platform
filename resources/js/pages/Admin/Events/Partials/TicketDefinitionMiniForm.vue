<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { currentLocale as i18nLocale } from '@/Utils/i18n'; // Assuming similar utility for current locale
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export interface StatusOption {
    value: string;
    label: string;
}

export interface AvailableLocales {
    [key: string]: string; // e.g. { en: "English", "zh-TW": "繁體中文" }
}

const props = defineProps<{
    show: boolean;
    statuses: StatusOption[];
    availableLocales: AvailableLocales;
}>();

const emit = defineEmits(['close', 'ticketDefinitionCreated']);

const localeTabs = computed(() => Object.entries(props.availableLocales).map(([key, label]) => ({ key, label })));
const activeLocaleTab = ref(i18nLocale.value || (localeTabs.value.length > 0 ? localeTabs.value[0].key : 'en'));

const form = useForm({
    name: Object.fromEntries(Object.keys(props.availableLocales).map((locale) => [locale, ''])) as Record<string, string>,
    description: Object.fromEntries(Object.keys(props.availableLocales).map((locale) => [locale, ''])) as Record<string, string>,
    price: undefined as number | undefined,
    total_quantity: undefined as number | undefined,
    status:
        props.statuses && props.statuses.length > 0 ? props.statuses.find((s) => s.value === 'active')?.value || props.statuses[0].value : 'draft',
    availability_window_start: '',
    availability_window_end: '',
    min_per_order: 1,
    max_per_order: undefined as number | undefined,
    // metadata: undefined, // Omitting for mini-form simplicity for now
});

watch(
    () => props.show,
    (newValue) => {
        if (newValue) {
            form.reset();
            form.clearErrors();
            // Ensure status is reset to a valid default from the current props.statuses
            form.status =
                props.statuses && props.statuses.length > 0
                    ? props.statuses.find((s) => s.value === 'active')?.value || props.statuses[0].value
                    : 'draft';
            // Reset translatable fields based on current availableLocales
            form.name = Object.fromEntries(Object.keys(props.availableLocales).map((locale) => [locale, '']));
            form.description = Object.fromEntries(Object.keys(props.availableLocales).map((locale) => [locale, '']));
            // Reset active tab
            activeLocaleTab.value = i18nLocale.value || (localeTabs.value.length > 0 ? localeTabs.value[0].key : 'en');
        }
    },
);

const statusOptions = computed(() => {
    return props.statuses.map((status) => ({ value: status.value, label: status.label }));
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        price:
            typeof data.price === 'number' && data.price !== null
                ? Math.round(data.price * 100) // Convert to cents
                : data.price,
    })).post(route('admin.ticket-definitions.store'), {
        preserveScroll: true,
        onSuccess: (page) => {
            // Assuming the created ticket definition data might be in page.props
            // Or we might need an endpoint that returns the created object directly
            // For now, emitting the form data used for creation
            emit('ticketDefinitionCreated', page.props.jetstream?.flash?.ticketDefinition || form.data());
            closeModal();
        },
        onError: (errors) => {
            console.error('Mini form creation error:', errors);
            // Potentially revert price for display if it was transformed and an error occurred
            if (errors.price && typeof form.price === 'number') {
                // form.price = form.price / 100; // This might be tricky if original was undefined
            }
        },
    });
};

const closeModal = () => {
    emit('close');
};
</script>

<template>
    <Dialog :open="show" @update:open="show ? closeModal() : null">
        <DialogContent class="sm:max-w-[700px]" @escape-key-down="closeModal" @pointer-down-outside="closeModal">
            <DialogHeader>
                <DialogTitle>Create New Ticket Definition</DialogTitle>
                <DialogDescription>
                    Quickly define a new type of ticket. It can be associated with specific event occurrences later.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Language Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                        <button
                            v-for="tab in localeTabs"
                            :key="tab.key"
                            @click.prevent="activeLocaleTab = tab.key"
                            :class="[
                                activeLocaleTab === tab.key
                                    ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-300'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200',
                                'border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap focus:outline-none',
                            ]"
                            type="button"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Translatable Fields Content -->
                <div v-for="tab in localeTabs" :key="tab.key" v-show="activeLocaleTab === tab.key" class="space-y-4">
                    <div>
                        <Label :for="`name_${tab.key}`">Name ({{ tab.label }})</Label>
                        <Input :id="`name_${tab.key}`" type="text" v-model="form.name[tab.key]" class="mt-1 block w-full" />
                        <InputError :message="form.errors[`name.${tab.key}`]" class="mt-1" />
                    </div>
                    <div>
                        <Label :for="`description_${tab.key}`">Description ({{ tab.label }})</Label>
                        <textarea
                            :id="`description_${tab.key}`"
                            v-model="form.description[tab.key]"
                            rows="3"
                            class="focus:ring-opacity-50 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        ></textarea>
                        <InputError :message="form.errors[`description.${tab.key}`]" class="mt-1" />
                    </div>
                </div>

                <!-- Non-Translatable Fields -->
                <div class="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                    <div>
                        <Label for="price">Price</Label>
                        <Input id="price" type="number" step="0.01" v-model.number="form.price" placeholder="e.g., 10.00" class="mt-1 block w-full" />
                        <InputError :message="form.errors.price" class="mt-1" />
                    </div>

                    <div>
                        <Label for="total_quantity">Total Quantity (optional)</Label>
                        <Input
                            id="total_quantity"
                            type="number"
                            v-model.number="form.total_quantity"
                            placeholder="e.g., 100"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.total_quantity" class="mt-1" />
                    </div>

                    <div>
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="focus:ring-opacity-50 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option disabled value="">Select status</option>
                            <option v-for="statusItem in statusOptions" :key="statusItem.value" :value="statusItem.value">
                                {{ statusItem.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>
                    <div></div>
                    <!-- Spacer for grid -->

                    <div>
                        <Label for="availability_window_start">Sale Starts At (optional)</Label>
                        <Input
                            id="availability_window_start"
                            type="datetime-local"
                            v-model="form.availability_window_start"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.availability_window_start" class="mt-1" />
                    </div>
                    <div>
                        <Label for="availability_window_end">Sale Ends At (optional)</Label>
                        <Input id="availability_window_end" type="datetime-local" v-model="form.availability_window_end" class="mt-1 block w-full" />
                        <InputError :message="form.errors.availability_window_end" class="mt-1" />
                    </div>

                    <div>
                        <Label for="min_per_order">Min. Per Order</Label>
                        <Input
                            id="min_per_order"
                            type="number"
                            v-model.number="form.min_per_order"
                            placeholder="Default: 1"
                            min="1"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.min_per_order" class="mt-1" />
                    </div>
                    <div>
                        <Label for="max_per_order">Max. Per Order (optional)</Label>
                        <Input
                            id="max_per_order"
                            type="number"
                            v-model.number="form.max_per_order"
                            placeholder="e.g., 10"
                            min="1"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.max_per_order" class="mt-1" />
                    </div>
                </div>

                <DialogFooter class="pt-6">
                    <DialogClose as-child>
                        <Button type="button" variant="outline" @click="closeModal">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Ticket Definition' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
