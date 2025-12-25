<script setup lang="ts">
import { computed } from 'vue';
import { Plus, Trash2 } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import { getTranslation } from '@/Utils/i18n';

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

interface Props {
    modelValue: AllocationFormData[];
    availableTickets: TicketDefinition[];
    errors?: Record<string, string>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:modelValue', value: AllocationFormData[]): void;
}>();

const pricingModes = [
    { value: 'original', label: 'Original Price' },
    { value: 'fixed', label: 'Custom Fixed Price' },
    { value: 'percentage_discount', label: 'Percentage Discount' },
    { value: 'free', label: 'Free (Complimentary)' },
];

const addAllocation = () => {
    const newAllocations = [
        ...props.modelValue,
        {
            ticket_definition_id: '',
            allocated_quantity: '',
            pricing_mode: 'original' as PricingMode,
            custom_price: null,
            discount_percentage: null,
        },
    ];
    emit('update:modelValue', newAllocations);
};

const removeAllocation = (index: number) => {
    const newAllocations = props.modelValue.filter((_, i) => i !== index);
    emit('update:modelValue', newAllocations);
};

const updateAllocation = (index: number, field: keyof AllocationFormData, value: any) => {
    const newAllocations = [...props.modelValue];
    newAllocations[index] = { ...newAllocations[index], [field]: value };

    // Reset dependent fields when pricing mode changes
    if (field === 'pricing_mode') {
        if (value === 'original' || value === 'free') {
            newAllocations[index].custom_price = null;
            newAllocations[index].discount_percentage = null;
        } else if (value === 'fixed') {
            newAllocations[index].discount_percentage = null;
        } else if (value === 'percentage_discount') {
            newAllocations[index].custom_price = null;
        }
    }

    emit('update:modelValue', newAllocations);
};

const getTicketById = (id: number | string): TicketDefinition | undefined => {
    return props.availableTickets.find((t) => t.id === Number(id));
};

const formatPrice = (cents: number): string => {
    return `$${(cents / 100).toFixed(2)}`;
};

const calculateEffectivePrice = (allocation: AllocationFormData): string => {
    const ticket = getTicketById(allocation.ticket_definition_id);
    if (!ticket) return '-';

    const originalPrice = ticket.price;

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

const getMaxQuantity = (ticketId: number | string): number | undefined => {
    const ticket = getTicketById(ticketId);
    return ticket?.available_quantity;
};

const getFieldError = (index: number, field: string): string | undefined => {
    const key = `allocations.${index}.${field}`;
    return props.errors?.[key];
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Ticket Allocations
            </h4>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addAllocation"
                :disabled="availableTickets.length === 0"
            >
                <Plus class="w-4 h-4 mr-1" />
                Add Ticket
            </Button>
        </div>

        <div v-if="modelValue.length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            No ticket allocations yet. Click "Add Ticket" to allocate tickets to this hold.
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="(allocation, index) in modelValue"
                :key="index"
                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-4"
            >
                <div class="flex justify-between items-start">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Allocation #{{ index + 1 }}
                    </span>
                    <button
                        type="button"
                        @click="removeAllocation(index)"
                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Ticket Definition -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Ticket Type *
                        </label>
                        <select
                            :value="allocation.ticket_definition_id"
                            @change="updateAllocation(index, 'ticket_definition_id', ($event.target as HTMLSelectElement).value)"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            :class="{ 'border-red-500': getFieldError(index, 'ticket_definition_id') }"
                        >
                            <option value="">Select a ticket type</option>
                            <option
                                v-for="ticket in availableTickets"
                                :key="ticket.id"
                                :value="ticket.id"
                            >
                                {{ getTranslation(ticket.name) }} - {{ formatPrice(ticket.price) }}
                                <template v-if="ticket.available_quantity !== undefined">
                                    ({{ ticket.available_quantity }} available)
                                </template>
                            </option>
                        </select>
                        <p v-if="getFieldError(index, 'ticket_definition_id')" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ getFieldError(index, 'ticket_definition_id') }}
                        </p>
                    </div>

                    <!-- Allocated Quantity -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Quantity *
                            <span v-if="getMaxQuantity(allocation.ticket_definition_id)" class="text-gray-500 font-normal">
                                (max: {{ getMaxQuantity(allocation.ticket_definition_id) }})
                            </span>
                        </label>
                        <input
                            type="number"
                            :value="allocation.allocated_quantity"
                            @input="updateAllocation(index, 'allocated_quantity', Number(($event.target as HTMLInputElement).value))"
                            min="1"
                            :max="getMaxQuantity(allocation.ticket_definition_id)"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            :class="{ 'border-red-500': getFieldError(index, 'allocated_quantity') }"
                            placeholder="Enter quantity"
                        />
                        <p v-if="getFieldError(index, 'allocated_quantity')" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ getFieldError(index, 'allocated_quantity') }}
                        </p>
                    </div>

                    <!-- Pricing Mode -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Pricing Mode
                        </label>
                        <select
                            :value="allocation.pricing_mode"
                            @change="updateAllocation(index, 'pricing_mode', ($event.target as HTMLSelectElement).value)"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        >
                            <option
                                v-for="mode in pricingModes"
                                :key="mode.value"
                                :value="mode.value"
                            >
                                {{ mode.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Custom Price (for fixed mode) -->
                    <div v-if="allocation.pricing_mode === 'fixed'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Custom Price (in dollars)
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input
                                type="number"
                                :value="allocation.custom_price !== null ? allocation.custom_price / 100 : ''"
                                @input="updateAllocation(index, 'custom_price', Math.round(Number(($event.target as HTMLInputElement).value) * 100))"
                                min="0"
                                step="0.01"
                                class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                :class="{ 'border-red-500': getFieldError(index, 'custom_price') }"
                                placeholder="0.00"
                            />
                        </div>
                        <p v-if="getFieldError(index, 'custom_price')" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ getFieldError(index, 'custom_price') }}
                        </p>
                    </div>

                    <!-- Discount Percentage (for percentage_discount mode) -->
                    <div v-if="allocation.pricing_mode === 'percentage_discount'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Discount Percentage
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input
                                type="number"
                                :value="allocation.discount_percentage"
                                @input="updateAllocation(index, 'discount_percentage', Number(($event.target as HTMLInputElement).value))"
                                min="0"
                                max="100"
                                class="block w-full pr-8 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                :class="{ 'border-red-500': getFieldError(index, 'discount_percentage') }"
                                placeholder="10"
                            />
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">%</span>
                            </div>
                        </div>
                        <p v-if="getFieldError(index, 'discount_percentage')" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ getFieldError(index, 'discount_percentage') }}
                        </p>
                    </div>
                </div>

                <!-- Effective Price Display -->
                <div v-if="allocation.ticket_definition_id" class="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Effective Price:
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ calculateEffectivePrice(allocation) }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
