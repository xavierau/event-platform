<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { usePage } from '@inertiajs/vue3';
import { formatTicketPrice } from '@/types/ticket';

interface Props {
    ticketPrice: number; // Price in cents
    currency: string;
    membershipPrice?: number | null; // Price in cents for members, null if no discount
    hasMembershipDiscount?: boolean;
    showSavings?: boolean;
    size?: 'sm' | 'md' | 'lg';
    alignment?: 'left' | 'center' | 'right';
}

const props = withDefaults(defineProps<Props>(), {
    membershipPrice: null,
    hasMembershipDiscount: false,
    showSavings: true,
    size: 'md',
    alignment: 'left',
});

const page = usePage();

const user = computed(() => page.props.auth?.user || null);
const isAuthenticated = computed(() => !!user.value);
const hasMembership = computed(() => !!user.value?.has_active_membership);

const shouldShowMemberPricing = computed(() => {
    return props.hasMembershipDiscount && props.membershipPrice !== null && props.membershipPrice !== props.ticketPrice;
});

const shouldShowLoginPrompt = computed(() => {
    return props.hasMembershipDiscount && !isAuthenticated.value;
});

const shouldShowMembershipUpgrade = computed(() => {
    return props.hasMembershipDiscount && isAuthenticated.value && !hasMembership.value;
});

const savings = computed(() => {
    if (!shouldShowMemberPricing.value || props.membershipPrice === null) {
        return 0;
    }
    return props.ticketPrice - props.membershipPrice;
});

const savingsPercentage = computed(() => {
    if (!shouldShowMemberPricing.value || props.membershipPrice === null || props.ticketPrice === 0) {
        return 0;
    }
    return Math.round((savings.value / props.ticketPrice) * 100);
});

const regularPriceFormatted = computed(() => formatTicketPrice(props.ticketPrice, props.currency));

const memberPriceFormatted = computed(() => {
    if (props.membershipPrice === null) return null;
    return formatTicketPrice(props.membershipPrice, props.currency);
});

const savingsFormatted = computed(() => formatTicketPrice(savings.value, props.currency));

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return {
                price: 'text-lg font-semibold',
                originalPrice: 'text-sm',
                memberPrice: 'text-lg font-bold',
                savings: 'text-xs',
                badge: 'text-xs px-2 py-0.5',
                prompt: 'text-xs'
            };
        case 'lg':
            return {
                price: 'text-2xl font-bold',
                originalPrice: 'text-lg',
                memberPrice: 'text-2xl font-bold',
                savings: 'text-sm',
                badge: 'text-sm px-3 py-1',
                prompt: 'text-sm'
            };
        default: // md
            return {
                price: 'text-xl font-semibold',
                originalPrice: 'text-base',
                memberPrice: 'text-xl font-bold',
                savings: 'text-sm',
                badge: 'text-sm px-2 py-1',
                prompt: 'text-sm'
            };
    }
});

const alignmentClasses = computed(() => {
    switch (props.alignment) {
        case 'center':
            return 'text-center';
        case 'right':
            return 'text-right';
        default:
            return 'text-left';
    }
});
</script>

<template>
    <div :class="[alignmentClasses, 'space-y-2']">
        <!-- Regular Price (no membership discount) -->
        <div v-if="!shouldShowMemberPricing && !shouldShowLoginPrompt && !shouldShowMembershipUpgrade">
            <span :class="sizeClasses.price" class="text-gray-900 dark:text-white">
                {{ regularPriceFormatted }}
            </span>
        </div>

        <!-- Member Pricing (authenticated user with membership) -->
        <div v-else-if="shouldShowMemberPricing" class="space-y-1">
            <!-- Member Price Badge -->
            <div class="flex items-center gap-2" :class="alignmentClasses">
                <Badge
                    variant="secondary"
                    :class="[sizeClasses.badge, 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200']"
                >
                    Member Price
                </Badge>
            </div>

            <!-- Price Comparison -->
            <div class="flex items-baseline gap-2" :class="alignmentClasses">
                <!-- Original Price (struck through) -->
                <span :class="[sizeClasses.originalPrice, 'text-gray-500 dark:text-gray-400 line-through']">
                    {{ regularPriceFormatted }}
                </span>

                <!-- Member Price -->
                <span :class="[sizeClasses.memberPrice, 'text-green-600 dark:text-green-400']">
                    {{ memberPriceFormatted }}
                </span>
            </div>

            <!-- Savings Display -->
            <div v-if="showSavings && savings > 0" :class="[alignmentClasses]">
                <span :class="[sizeClasses.savings, 'text-green-600 dark:text-green-400 font-medium']">
                    You save {{ savingsFormatted }} ({{ savingsPercentage }}%)
                </span>
            </div>
        </div>

        <!-- Login Prompt (not authenticated) -->
        <div v-else-if="shouldShowLoginPrompt" class="space-y-2">
            <!-- Regular Price -->
            <div>
                <span :class="sizeClasses.price" class="text-gray-900 dark:text-white">
                    {{ regularPriceFormatted }}
                </span>
            </div>

            <!-- Login Prompt -->
            <div :class="alignmentClasses">
                <p :class="[sizeClasses.prompt, 'text-blue-600 dark:text-blue-400']">
                    <a href="/login" class="hover:underline">Login</a>
                    to see member discounts
                </p>
            </div>
        </div>

        <!-- Membership Upgrade Prompt (authenticated but no membership) -->
        <div v-else-if="shouldShowMembershipUpgrade" class="space-y-2">
            <!-- Regular Price -->
            <div>
                <span :class="sizeClasses.price" class="text-gray-900 dark:text-white">
                    {{ regularPriceFormatted }}
                </span>
            </div>

            <!-- Membership Upgrade Prompt -->
            <div :class="alignmentClasses">
                <p :class="[sizeClasses.prompt, 'text-orange-600 dark:text-orange-400']">
                    <a href="/membership" class="hover:underline font-medium">Become a member</a>
                    for exclusive discounts
                </p>
            </div>
        </div>
    </div>
</template>