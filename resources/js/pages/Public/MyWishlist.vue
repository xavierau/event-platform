<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import EventListItem from '@/components/Shared/EventListItem.vue';
import PublicHeader from '@/components/Shared/PublicHeader.vue';
import { useWishlist } from '@/composables/useWishlist';
import type { EventItem } from '@/types';

const {
  isLoading,
  error,
  wishlistItems,
  wishlistCount,
  getUserWishlist,
  clearWishlist,
  clearError
} = useWishlist();

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
    <CustomContainer
      title="My Wishlist"
      :poster_url="undefined"
    >
    <div class="container mx-auto py-8 px-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-3 text-gray-600 dark:text-gray-400">Loading your wishlist...</span>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="p-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <div class="flex items-center">
          <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
          </svg>
          <p class="text-red-800 dark:text-red-200">{{ error }}</p>
        </div>
        <button
          @click="clearError"
          class="mt-3 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 underline"
        >
          Dismiss
        </button>
      </div>

      <!-- Wishlist Header with Actions -->
      <div v-else-if="hasEvents" class="mb-6">
        <div class="flex justify-between items-center mb-4">
          <p class="text-gray-600 dark:text-gray-400">
            {{ wishlistCount }} {{ wishlistCount === 1 ? 'event' : 'events' }} in your wishlist
          </p>
          <button
            @click="handleClearWishlist"
            class="px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
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
      <div v-else class="text-center py-16">
        <div class="max-w-md mx-auto">
          <!-- Heart Icon -->
          <div class="mx-auto w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
          </div>

          <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3">
            Your wishlist is empty
          </h3>

          <p class="text-gray-600 dark:text-gray-400 mb-6">
            Start exploring events and save the ones you're interested in to your wishlist.
          </p>

          <a
            href="/events"
            class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Browse Events
          </a>
        </div>
      </div>
    </div>
  </CustomContainer>
  </div>
</template>
