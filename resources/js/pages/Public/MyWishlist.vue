<script setup lang="ts">
import FrontendFooter from '@/components/FrontendFooter.vue';
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import EventListItem from '@/components/Shared/EventListItem.vue';
import PublicHeader from '@/components/Shared/PublicHeader.vue';
import { useWishlist } from '@/composables/useWishlist';
import type { EventItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const { isLoading, error, wishlistItems, wishlistCount, getUserWishlist, clearWishlist, clearError } = useWishlist();

// Wishlist items are now properly formatted from the backend
const transformedEvents = computed((): EventItem[] => {
    return wishlistItems.value;
});

const hasEvents = computed(() => transformedEvents.value.length > 0);

const handleClearWishlist = async () => {
    if (confirm('Are you sure you want to clear your entire wishlist?')) {
        await clearWishlist();
    }
};

const handleWishlistChanged = (inWishlist: boolean) => {
    if (!inWishlist) {
        // Event was removed from wishlist, refresh the list
        getUserWishlist();
    }
};

const handleError = (errorMessage: string) => {
    console.error('Wishlist error:', errorMessage);
};

// Load wishlist on component mount
onMounted(() => {
    getUserWishlist();
});
</script>

<template>
    <Head title="My Wishlist" />

    <div>
        <PublicHeader />
        <CustomContainer title="My Wishlist" :poster_url="undefined">
            <div class="container mx-auto px-4 py-8">
                <!-- Loading State -->
                <div v-if="isLoading" class="flex items-center justify-center py-12">
                    <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
                    <span class="ml-3 text-gray-600 dark:text-gray-400">Loading your wishlist...</span>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20">
                    <div class="flex items-center">
                        <svg class="mr-2 h-5 w-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd"
                            ></path>
                        </svg>
                        <p class="text-red-800 dark:text-red-200">{{ error }}</p>
                    </div>
                    <button
                        @click="clearError"
                        class="mt-3 text-sm text-red-600 underline hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                    >
                        Dismiss
                    </button>
                </div>

                <!-- Wishlist Header with Actions -->
                <div v-else-if="hasEvents" class="mb-6">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-gray-600 dark:text-gray-400">
                            {{ wishlistCount }} {{ wishlistCount === 1 ? 'event' : 'events' }} in your wishlist
                        </p>
                        <button
                            @click="handleClearWishlist"
                            class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 hover:text-red-800 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-200"
                        >
                            Clear All
                        </button>
                    </div>

                    <!-- Events List -->
                    <div class="space-y-4">
                        <EventListItem
                            v-for="event in transformedEvents"
                            :key="event.id"
                            :event="event"
                            @wishlistChanged="handleWishlistChanged"
                            @error="handleError"
                        />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="py-16 text-center">
                    <div class="mx-auto max-w-md">
                        <!-- Heart Icon -->
                        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                ></path>
                            </svg>
                        </div>

                        <h3 class="mb-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Your wishlist is empty</h3>

                        <p class="mb-6 text-gray-600 dark:text-gray-400">
                            Start exploring events and save the ones you're interested in to your wishlist.
                        </p>

                        <a
                            href="/events"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 font-medium text-white transition-colors hover:bg-blue-700"
                        >
                            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                ></path>
                            </svg>
                            Browse Events
                        </a>
                    </div>
                </div>
            </div>
        
            <FrontendFooter />
        </CustomContainer>
    </div>
</template>
