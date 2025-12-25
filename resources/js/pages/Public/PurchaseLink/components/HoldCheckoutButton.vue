<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import type { PurchaseItem } from '@/types/ticket-hold';
import { useI18n } from 'vue-i18n';

interface Props {
    canPurchase: boolean;
    isAuthenticated: boolean;
    linkCode: string;
    selectedItems: PurchaseItem[];
    isLoading: boolean;
    disabledReason?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'submit'): void;
}>();

const { t } = useI18n();

// Check if any items are selected
const hasSelectedItems = computed(() => {
    return props.selectedItems.some((item) => item.quantity > 0);
});

// Get total quantity
const totalQuantity = computed(() => {
    return props.selectedItems.reduce((sum, item) => sum + item.quantity, 0);
});

// Determine button state
const buttonState = computed(() => {
    if (props.isLoading) {
        return {
            disabled: true,
            text: t('purchase_link.processing'),
            icon: 'loading',
            class: 'bg-gray-400 dark:bg-gray-600 cursor-wait',
        };
    }

    if (!props.isAuthenticated) {
        return {
            disabled: false,
            text: t('purchase_link.login_to_purchase'),
            icon: 'lock',
            class: 'bg-pink-500 hover:bg-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700',
        };
    }

    if (!props.canPurchase) {
        return {
            disabled: true,
            text: props.disabledReason || t('purchase_link.cannot_purchase'),
            icon: 'blocked',
            class: 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed',
        };
    }

    if (!hasSelectedItems.value) {
        return {
            disabled: true,
            text: t('purchase_link.select_tickets'),
            icon: 'cart',
            class: 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed',
        };
    }

    return {
        disabled: false,
        text: t('purchase_link.complete_purchase'),
        icon: 'check',
        class: 'bg-pink-500 hover:bg-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700',
    };
});

// Handle button click
function handleClick(): void {
    if (props.isLoading) return;

    if (!props.isAuthenticated) {
        // Redirect to login with return URL
        const currentUrl = window.location.href;
        router.visit(route('login', { redirect: currentUrl }));
        return;
    }

    if (!props.canPurchase || !hasSelectedItems.value) {
        return;
    }

    emit('submit');
}
</script>

<template>
    <div class="space-y-3">
        <!-- Main checkout button -->
        <button
            type="button"
            @click="handleClick"
            :disabled="buttonState.disabled"
            class="flex w-full items-center justify-center gap-2 rounded-lg px-6 py-3 text-base font-semibold text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            :class="buttonState.class"
        >
            <!-- Loading spinner -->
            <svg
                v-if="buttonState.icon === 'loading'"
                class="h-5 w-5 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>

            <!-- Lock icon for login -->
            <svg
                v-else-if="buttonState.icon === 'lock'"
                class="h-5 w-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                />
            </svg>

            <!-- Blocked icon -->
            <svg
                v-else-if="buttonState.icon === 'blocked'"
                class="h-5 w-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                />
            </svg>

            <!-- Cart icon -->
            <svg
                v-else-if="buttonState.icon === 'cart'"
                class="h-5 w-5"
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

            <!-- Check icon -->
            <svg
                v-else-if="buttonState.icon === 'check'"
                class="h-5 w-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                />
            </svg>

            <span>{{ buttonState.text }}</span>

            <!-- Item count badge -->
            <span
                v-if="hasSelectedItems && buttonState.icon === 'check'"
                class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-sm"
            >
                {{ totalQuantity }}
            </span>
        </button>

        <!-- Secure checkout indicator -->
        <div class="flex items-center justify-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                />
            </svg>
            <span>{{ t('purchase_link.secure_checkout') }}</span>
        </div>

        <!-- Tooltip for disabled state -->
        <p
            v-if="!canPurchase && isAuthenticated && disabledReason"
            class="text-center text-xs text-red-500 dark:text-red-400"
        >
            {{ disabledReason }}
        </p>
    </div>
</template>
