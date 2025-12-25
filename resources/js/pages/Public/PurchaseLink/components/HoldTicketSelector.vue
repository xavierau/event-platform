<script setup lang="ts">
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import type { HoldTicketAllocationDisplay, PurchaseItem, PricingMode } from '@/types/ticket-hold';
import { useI18n } from 'vue-i18n';

interface Props {
    allocations: HoldTicketAllocationDisplay[];
    modelValue: PurchaseItem[];
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: PurchaseItem[]): void;
}>();

const { t } = useI18n();
const { formatPrice } = useCurrency();

// Get quantity for a specific ticket definition
function getQuantity(ticketDefinitionId: number): number {
    const item = props.modelValue.find(
        (i) => i.ticket_definition_id === ticketDefinitionId
    );
    return item?.quantity ?? 0;
}

// Update quantity for a specific ticket definition
function updateQuantity(ticketDefinitionId: number, quantity: number): void {
    const newItems = [...props.modelValue];
    const existingIndex = newItems.findIndex(
        (i) => i.ticket_definition_id === ticketDefinitionId
    );

    if (quantity <= 0) {
        // Remove item if quantity is zero or less
        if (existingIndex !== -1) {
            newItems.splice(existingIndex, 1);
        }
    } else {
        if (existingIndex !== -1) {
            newItems[existingIndex] = { ticket_definition_id: ticketDefinitionId, quantity };
        } else {
            newItems.push({ ticket_definition_id: ticketDefinitionId, quantity });
        }
    }

    emit('update:modelValue', newItems);
}

// Increment quantity
function increment(allocation: HoldTicketAllocationDisplay): void {
    if (props.disabled) return;

    const currentQty = getQuantity(allocation.ticket_definition_id);
    const maxQty = allocation.remaining_quantity;

    if (currentQty < maxQty) {
        updateQuantity(allocation.ticket_definition_id, currentQty + 1);
    }
}

// Decrement quantity
function decrement(allocation: HoldTicketAllocationDisplay): void {
    if (props.disabled) return;

    const currentQty = getQuantity(allocation.ticket_definition_id);
    if (currentQty > 0) {
        updateQuantity(allocation.ticket_definition_id, currentQty - 1);
    }
}

// Get ticket name (handle translatable)
function getTicketName(allocation: HoldTicketAllocationDisplay): string {
    const name = allocation.ticket_definition?.name;
    if (typeof name === 'string') {
        return name;
    }
    if (typeof name === 'object' && name !== null) {
        // Return first available translation
        return name['en'] || Object.values(name)[0] || 'Ticket';
    }
    return 'Ticket';
}

// Get savings badge text
function getSavingsBadge(allocation: HoldTicketAllocationDisplay): string | null {
    if (allocation.pricing_mode === 'free') {
        return 'FREE';
    }
    if (allocation.savings_percentage > 0) {
        return t('purchase_link.save_percent', { percent: allocation.savings_percentage });
    }
    return null;
}

// Check if allocation has a discount
function hasDiscount(allocation: HoldTicketAllocationDisplay): boolean {
    return (
        allocation.pricing_mode === 'free' ||
        allocation.pricing_mode === 'fixed' ||
        allocation.pricing_mode === 'percentage_discount'
    );
}

// Get currency from first allocation
const currency = computed(() => {
    const firstAllocation = props.allocations[0];
    return firstAllocation?.ticket_definition?.currency || 'USD';
});

// Check if any tickets are available
const hasAvailableTickets = computed(() => {
    return props.allocations.some((a) => a.remaining_quantity > 0);
});
</script>

<template>
    <div class="space-y-3">
        <!-- No tickets available message -->
        <div
            v-if="!hasAvailableTickets"
            class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-center dark:border-gray-700 dark:bg-gray-800"
        >
            <svg
                class="mx-auto h-12 w-12 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ t('purchase_link.no_tickets_available') }}
            </p>
        </div>

        <!-- Ticket list -->
        <div
            v-for="allocation in allocations"
            :key="allocation.id"
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
            :class="{
                'opacity-50': allocation.remaining_quantity === 0,
            }"
        >
            <div class="flex items-start justify-between gap-4">
                <!-- Ticket info -->
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ getTicketName(allocation) }}
                        </h3>
                        <!-- Savings badge -->
                        <span
                            v-if="getSavingsBadge(allocation)"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':
                                    allocation.pricing_mode !== 'free',
                                'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200':
                                    allocation.pricing_mode === 'free',
                            }"
                        >
                            {{ getSavingsBadge(allocation) }}
                        </span>
                    </div>

                    <!-- Price display -->
                    <div class="mt-1 flex items-baseline gap-2">
                        <!-- Effective price (hold price) -->
                        <span
                            class="text-lg font-bold"
                            :class="{
                                'text-pink-500 dark:text-pink-400': hasDiscount(allocation),
                                'text-gray-900 dark:text-gray-100': !hasDiscount(allocation),
                            }"
                        >
                            <template v-if="allocation.pricing_mode === 'free'">
                                {{ t('purchase_link.free') }}
                            </template>
                            <template v-else>
                                {{ formatPrice(allocation.effective_price, currency) }}
                            </template>
                        </span>

                        <!-- Original price (crossed out if discounted) -->
                        <span
                            v-if="hasDiscount(allocation) && allocation.pricing_mode !== 'free'"
                            class="text-sm text-gray-400 line-through dark:text-gray-500"
                        >
                            {{ formatPrice(allocation.original_price, currency) }}
                        </span>
                    </div>

                    <!-- Available quantity -->
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('purchase_link.available_count', { count: allocation.remaining_quantity }) }}
                    </p>
                </div>

                <!-- Quantity selector -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="decrement(allocation)"
                        :disabled="disabled || getQuantity(allocation.ticket_definition_id) === 0"
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                    </button>

                    <span class="w-8 text-center text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ getQuantity(allocation.ticket_definition_id) }}
                    </span>

                    <button
                        type="button"
                        @click="increment(allocation)"
                        :disabled="
                            disabled ||
                            allocation.remaining_quantity === 0 ||
                            getQuantity(allocation.ticket_definition_id) >= allocation.remaining_quantity
                        "
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-pink-500 bg-pink-500 text-white transition-colors hover:bg-pink-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
