<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import HoldTicketSelector from './components/HoldTicketSelector.vue';
import HoldPriceDisplay from './components/HoldPriceDisplay.vue';
import HoldCheckoutButton from './components/HoldCheckoutButton.vue';
import PublicHeader from '@/components/Shared/PublicHeader.vue';
import Footer from '@/components/Public/Footer.vue';

import type { PurchaseLinkShowProps, PurchaseItem, OrderLineItem } from '@/types/ticket-hold';

// Props from controller
const props = defineProps<PurchaseLinkShowProps>();

const { t } = useI18n();
const page = usePage();

// State
const selectedItems = ref<PurchaseItem[]>([]);
const isLoading = ref(false);
const errorMessage = ref<string | null>(props.errorMessage || null);

// Check if user is authenticated
const isAuthenticated = computed(() => !!(page.props.auth as any)?.user);

// Get currency from first allocation
const currency = computed(() => {
    const firstAllocation = props.allocations[0];
    return firstAllocation?.ticket_definition?.currency || 'USD';
});

// Convert selected items to order line items for display
const orderLineItems = computed<OrderLineItem[]>(() => {
    return selectedItems.value
        .filter((item) => item.quantity > 0)
        .map((item) => {
            const allocation = props.allocations.find(
                (a) => a.ticket_definition_id === item.ticket_definition_id
            );
            if (!allocation) return null;

            return {
                ticket: allocation.ticket_definition,
                quantity: item.quantity,
                unitPrice: allocation.effective_price,
                originalPrice: allocation.original_price,
            };
        })
        .filter((item): item is OrderLineItem => item !== null);
});

// Get disabled reason for checkout button
const disabledReason = computed(() => {
    if (!props.isUsable) {
        return props.errorMessage || t('purchase_link.link_not_usable');
    }
    if (!props.canPurchase) {
        return t('purchase_link.user_not_authorized');
    }
    return undefined;
});

// Get error display configuration
const errorDisplay = computed(() => {
    if (!props.errorMessage) return null;

    const status = props.link.status;
    let icon = 'warning';
    let color = 'red';

    if (status === 'expired') {
        icon = 'clock';
        color = 'yellow';
    } else if (status === 'revoked') {
        icon = 'x-circle';
        color = 'red';
    } else if (status === 'exhausted') {
        icon = 'check-circle';
        color = 'blue';
    }

    return { icon, color };
});

// Handle purchase submission
function handleSubmit(): void {
    if (isLoading.value) return;

    const itemsToPurchase = selectedItems.value.filter((item) => item.quantity > 0);
    if (itemsToPurchase.length === 0) {
        errorMessage.value = t('purchase_link.select_tickets_error');
        return;
    }

    isLoading.value = true;
    errorMessage.value = null;

    router.post(
        route('purchase-link.purchase', { code: props.link.code }),
        {
            items: itemsToPurchase,
        },
        {
            onError: (errors) => {
                // Handle validation errors from Inertia
                errorMessage.value =
                    Object.values(errors).flat().join(', ') ||
                    t('purchase_link.processing_error');
            },
            onFinish: () => {
                isLoading.value = false;
            },
        }
    );
}

// Format date for display
function formatDate(dateString: string): string {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateString;
    }
}
</script>

<template>
    <div>
        <Head :title="t('purchase_link.page_title', { event: event.name })" />

        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <!-- Header -->
            <PublicHeader />

            <main class="container mx-auto px-4 py-6">
                <!-- Error state -->
                <div
                    v-if="!isUsable && errorMessage"
                    class="mb-6 rounded-lg border-l-4 p-4"
                    :class="{
                        'border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20': errorDisplay?.color === 'yellow',
                        'border-red-400 bg-red-50 dark:bg-red-900/20': errorDisplay?.color === 'red',
                        'border-blue-400 bg-blue-50 dark:bg-blue-900/20': errorDisplay?.color === 'blue',
                    }"
                >
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <!-- Clock icon for expired -->
                            <svg
                                v-if="errorDisplay?.icon === 'clock'"
                                class="h-5 w-5 text-yellow-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <!-- X-circle icon for revoked -->
                            <svg
                                v-else-if="errorDisplay?.icon === 'x-circle'"
                                class="h-5 w-5 text-red-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <!-- Check-circle icon for exhausted -->
                            <svg
                                v-else-if="errorDisplay?.icon === 'check-circle'"
                                class="h-5 w-5 text-blue-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <!-- Default warning icon -->
                            <svg
                                v-else
                                class="h-5 w-5 text-red-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3
                                class="text-sm font-medium"
                                :class="{
                                    'text-yellow-800 dark:text-yellow-200': errorDisplay?.color === 'yellow',
                                    'text-red-800 dark:text-red-200': errorDisplay?.color === 'red',
                                    'text-blue-800 dark:text-blue-200': errorDisplay?.color === 'blue',
                                }"
                            >
                                {{ errorMessage }}
                            </h3>
                            <div class="mt-2">
                                <Link
                                    :href="`/events/${event.id}`"
                                    class="text-sm font-medium underline"
                                    :class="{
                                        'text-yellow-700 hover:text-yellow-600 dark:text-yellow-300': errorDisplay?.color === 'yellow',
                                        'text-red-700 hover:text-red-600 dark:text-red-300': errorDisplay?.color === 'red',
                                        'text-blue-700 hover:text-blue-600 dark:text-blue-300': errorDisplay?.color === 'blue',
                                    }"
                                >
                                    {{ t('purchase_link.view_event') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User not authorized warning (for user-tied links) -->
                <div
                    v-else-if="isUsable && !canPurchase && isAuthenticated"
                    class="mb-6 rounded-lg border-l-4 border-orange-400 bg-orange-50 p-4 dark:bg-orange-900/20"
                >
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                {{ t('purchase_link.user_not_authorized') }}
                            </h3>
                            <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                {{ t('purchase_link.user_not_authorized_description') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Main content -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Left column: Event info + Ticket selection -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Event header card -->
                        <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                            <div class="flex flex-col sm:flex-row">
                                <!-- Event image -->
                                <div class="flex-shrink-0 sm:w-48">
                                    <img
                                        :src="event.image_url || '/images/placeholder-event.jpg'"
                                        :alt="event.name"
                                        class="h-48 w-full object-cover sm:h-full"
                                    />
                                </div>
                                <!-- Event details -->
                                <div class="flex-1 p-4 sm:p-6">
                                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 sm:text-2xl">
                                        {{ event.name }}
                                    </h1>
                                    <div class="mt-3 space-y-2">
                                        <!-- Date -->
                                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                />
                                            </svg>
                                            <span>{{ formatDate(event.date) }}</span>
                                        </div>
                                        <!-- Venue -->
                                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                                />
                                            </svg>
                                            <span>{{ event.venue }}</span>
                                        </div>
                                    </div>

                                    <!-- Special pricing badge -->
                                    <div class="mt-4">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-pink-100 px-3 py-1 text-sm font-medium text-pink-800 dark:bg-pink-900 dark:text-pink-200"
                                        >
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                            {{ t('purchase_link.exclusive_pricing') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket selection -->
                        <div class="rounded-lg bg-white p-4 shadow-sm sm:p-6 dark:bg-gray-800">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ t('purchase_link.select_tickets') }}
                            </h2>

                            <HoldTicketSelector
                                v-model="selectedItems"
                                :allocations="allocations"
                                :disabled="!isUsable || !canPurchase || isLoading"
                            />
                        </div>
                    </div>

                    <!-- Right column: Order summary + Checkout -->
                    <div class="space-y-6">
                        <!-- Order summary -->
                        <HoldPriceDisplay :items="orderLineItems" :currency="currency" />

                        <!-- Checkout button -->
                        <HoldCheckoutButton
                            :can-purchase="canPurchase"
                            :is-authenticated="isAuthenticated"
                            :link-code="link.code"
                            :selected-items="selectedItems"
                            :is-loading="isLoading"
                            :disabled-reason="disabledReason"
                            @submit="handleSubmit"
                        />

                        <!-- Error message from submission -->
                        <div
                            v-if="errorMessage && isUsable"
                            class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20"
                        >
                            <p class="text-sm text-red-600 dark:text-red-400">
                                {{ errorMessage }}
                            </p>
                        </div>

                        <!-- Link expiration info -->
                        <div
                            v-if="link.expires_at && isUsable"
                            class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <span>
                                    {{ t('purchase_link.expires_at', { date: formatDate(link.expires_at) }) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <Footer />
        </div>
    </div>
</template>
