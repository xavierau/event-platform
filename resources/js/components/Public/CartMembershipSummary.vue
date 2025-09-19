<script setup lang="ts">
import { computed } from 'vue';
import { formatTicketPrice } from '@/types/ticket';

interface CartItem {
    id: string | number;
    name: string;
    originalPrice: number; // In cents
    memberPrice?: number | null; // In cents, null if no discount
    quantity: number;
    currency: string;
    hasMembershipDiscount: boolean;
}

interface Props {
    items: CartItem[];
    userHasMembership: boolean;
    showBreakdown?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showBreakdown: true,
});

const itemsWithDiscounts = computed(() => {
    return props.items.filter(item =>
        item.hasMembershipDiscount &&
        item.memberPrice !== null &&
        item.memberPrice !== item.originalPrice &&
        props.userHasMembership
    );
});

const totalOriginalPrice = computed(() => {
    return props.items.reduce((total, item) => total + (item.originalPrice * item.quantity), 0);
});

const totalMemberPrice = computed(() => {
    return props.items.reduce((total, item) => {
        const price = (props.userHasMembership && item.memberPrice !== null)
            ? item.memberPrice
            : item.originalPrice;
        return total + (price * item.quantity);
    }, 0);
});

const totalSavings = computed(() => {
    return totalOriginalPrice.value - totalMemberPrice.value;
});

const hasSavings = computed(() => {
    return props.userHasMembership && totalSavings.value > 0;
});

const formatPrice = (price: number, currency: string) => {
    return formatTicketPrice(price, currency);
};
</script>

<template>
    <div v-if="hasSavings" class="space-y-4">
        <!-- Membership Benefits Header -->
        <div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                <span class="font-medium text-green-800 dark:text-green-200">
                    Membership Benefits Applied
                </span>
            </div>
            <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                -{{ formatPrice(totalSavings, items[0]?.currency || 'USD') }}
            </span>
        </div>

        <!-- Detailed Breakdown -->
        <div v-if="showBreakdown && itemsWithDiscounts.length > 0" class="space-y-2">
            <div
                v-for="item in itemsWithDiscounts"
                :key="item.id"
                class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 pl-4"
            >
                <span>
                    {{ item.name }}
                    <template v-if="item.quantity > 1"> × {{ item.quantity }}</template>
                </span>
                <span class="text-green-600 dark:text-green-400">
                    -{{ formatPrice((item.originalPrice - (item.memberPrice || 0)) * item.quantity, item.currency) }}
                </span>
            </div>
        </div>

        <!-- Total Summary -->
        <div class="border-t border-gray-200 dark:border-gray-600 pt-3">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">
                    Original Total:
                </span>
                <span class="line-through text-gray-500">
                    {{ formatPrice(totalOriginalPrice, items[0]?.currency || 'USD') }}
                </span>
            </div>
            <div class="flex items-center justify-between font-semibold text-lg">
                <span class="text-gray-900 dark:text-white">
                    Member Total:
                </span>
                <span class="text-green-600 dark:text-green-400">
                    {{ formatPrice(totalMemberPrice, items[0]?.currency || 'USD') }}
                </span>
            </div>
        </div>

        <!-- Member Benefits Callout -->
        <div class="text-xs text-center text-gray-500 dark:text-gray-400 italic">
            Thank you for being a member! You've saved {{ formatPrice(totalSavings, items[0]?.currency || 'USD') }} on this order.
        </div>
    </div>

    <!-- No Savings Message (fallback) -->
    <div v-else-if="props.userHasMembership && itemsWithDiscounts.length === 0" class="text-sm text-gray-500 dark:text-gray-400 text-center py-2">
        No membership discounts available for these items
    </div>
</template>