<script setup lang="ts">
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import type { OrderLineItem } from '@/types/ticket-hold';
import { useI18n } from 'vue-i18n';

interface Props {
    items: OrderLineItem[];
    currency: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatPrice } = useCurrency();

// Get ticket name (handle translatable)
function getTicketName(item: OrderLineItem): string {
    const name = item.ticket?.name;
    if (typeof name === 'string') {
        return name;
    }
    if (typeof name === 'object' && name !== null) {
        return name['en'] || Object.values(name)[0] || 'Ticket';
    }
    return 'Ticket';
}

// Calculate subtotal (sum of all items at hold prices)
const subtotal = computed(() => {
    return props.items.reduce((sum, item) => {
        return sum + item.unitPrice * item.quantity;
    }, 0);
});

// Calculate original total (sum of all items at original prices)
const originalTotal = computed(() => {
    return props.items.reduce((sum, item) => {
        return sum + item.originalPrice * item.quantity;
    }, 0);
});

// Calculate total savings
const totalSavings = computed(() => {
    return originalTotal.value - subtotal.value;
});

// Check if there are any savings
const hasSavings = computed(() => {
    return totalSavings.value > 0;
});

// Calculate savings percentage
const savingsPercentage = computed(() => {
    if (originalTotal.value === 0) return 0;
    return Math.round((totalSavings.value / originalTotal.value) * 100);
});

// Check if there are any items
const hasItems = computed(() => {
    return props.items.length > 0 && props.items.some((item) => item.quantity > 0);
});

// Total item count
const totalItems = computed(() => {
    return props.items.reduce((sum, item) => sum + item.quantity, 0);
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
            {{ t('purchase_link.order_summary') }}
        </h3>

        <!-- Empty state -->
        <div v-if="!hasItems" class="py-6 text-center">
            <svg
                class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ t('purchase_link.no_items_selected') }}
            </p>
        </div>

        <!-- Items list -->
        <div v-else class="space-y-3">
            <!-- Line items -->
            <div
                v-for="item in items.filter((i) => i.quantity > 0)"
                :key="item.ticket.id"
                class="flex items-center justify-between border-b border-gray-100 pb-3 last:border-0 last:pb-0 dark:border-gray-700"
            >
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ getTicketName(item) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ item.quantity }} x {{ formatPrice(item.unitPrice, currency) }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ formatPrice(item.unitPrice * item.quantity, currency) }}
                    </p>
                    <!-- Show original price if discounted -->
                    <p
                        v-if="item.originalPrice > item.unitPrice"
                        class="text-xs text-gray-400 line-through dark:text-gray-500"
                    >
                        {{ formatPrice(item.originalPrice * item.quantity, currency) }}
                    </p>
                </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-gray-200 pt-3 dark:border-gray-600">
                <!-- Subtotal -->
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">
                        {{ t('purchase_link.subtotal') }} ({{ totalItems }} {{ totalItems === 1 ? t('purchase_link.item') : t('purchase_link.items') }})
                    </span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                        {{ formatPrice(subtotal, currency) }}
                    </span>
                </div>

                <!-- Total savings -->
                <div v-if="hasSavings" class="mt-2 flex items-center justify-between">
                    <span class="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        {{ t('purchase_link.you_save') }}
                    </span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">
                        {{ formatPrice(totalSavings, currency) }} ({{ savingsPercentage }}%)
                    </span>
                </div>
            </div>

            <!-- Grand total -->
            <div class="border-t border-gray-200 pt-3 dark:border-gray-600">
                <div class="flex items-center justify-between">
                    <span class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('purchase_link.total') }}
                    </span>
                    <span class="text-xl font-bold text-pink-500 dark:text-pink-400">
                        {{ formatPrice(subtotal, currency) }}
                    </span>
                </div>
                <!-- Original total if savings -->
                <div v-if="hasSavings" class="mt-1 text-right">
                    <span class="text-sm text-gray-400 line-through dark:text-gray-500">
                        {{ formatPrice(originalTotal, currency) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
