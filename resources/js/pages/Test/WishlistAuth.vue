<script setup lang="ts">
import { ref } from 'vue';
import WishlistButton from '@/components/Shared/WishlistButton.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

// Mock event data for testing
const testEvent = {
  id: 1,
  name: 'Test Event',
  description: 'A test event for wishlist functionality'
};

const message = ref('');
const messageType = ref<'success' | 'error'>('success');

const handleWishlistChanged = (inWishlist: boolean) => {
  message.value = `Event ${inWishlist ? 'added to' : 'removed from'} wishlist!`;
  messageType.value = 'success';

  // Clear message after 3 seconds
  setTimeout(() => {
    message.value = '';
  }, 3000);
};

const handleWishlistError = (errorMessage: string) => {
  message.value = `Error: ${errorMessage}`;
  messageType.value = 'error';

  // Clear message after 5 seconds
  setTimeout(() => {
    message.value = '';
  }, 5000);
};

const isAuthenticated = (page.props.auth as any)?.user;
</script>

<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-12">
    <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">
        Wishlist Authentication Test
      </h1>

      <!-- Authentication Status -->
      <div class="mb-6 p-4 rounded-lg" :class="isAuthenticated ? 'bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200'">
        <h2 class="font-semibold mb-2">Authentication Status:</h2>
        <p v-if="isAuthenticated">
          ✅ Logged in as: {{ isAuthenticated.name }} ({{ isAuthenticated.email }})
        </p>
        <p v-else>
          ❌ Not authenticated - wishlist actions will redirect to login
        </p>
      </div>

      <!-- Test Event Card -->
      <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
          {{ testEvent.name }}
        </h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
          {{ testEvent.description }}
        </p>

        <!-- Wishlist Button Tests -->
        <div class="space-y-4">
          <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button Variant:</h4>
            <WishlistButton
              :event-id="testEvent.id"
              variant="button"
              size="md"
              :show-text="true"
              @wishlist-changed="handleWishlistChanged"
              @error="handleWishlistError"
            />
          </div>

          <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Icon Variant:</h4>
            <WishlistButton
              :event-id="testEvent.id"
              variant="icon"
              size="md"
              :show-text="false"
              @wishlist-changed="handleWishlistChanged"
              @error="handleWishlistError"
            />
          </div>

          <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Text Variant:</h4>
            <WishlistButton
              :event-id="testEvent.id"
              variant="text"
              size="md"
              :show-text="true"
              @wishlist-changed="handleWishlistChanged"
              @error="handleWishlistError"
            />
          </div>
        </div>
      </div>

      <!-- Message Display -->
      <div v-if="message" class="p-4 rounded-lg mb-4" :class="messageType === 'success' ? 'bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200'">
        {{ message }}
      </div>

      <!-- Instructions -->
      <div class="text-sm text-gray-600 dark:text-gray-400">
        <h4 class="font-medium mb-2">Test Instructions:</h4>
        <ul class="list-disc list-inside space-y-1">
          <li v-if="!isAuthenticated">Click any wishlist button - you should be redirected to login</li>
          <li v-if="isAuthenticated">Click wishlist buttons to add/remove from wishlist</li>
          <li v-if="isAuthenticated">Try different button variants and sizes</li>
          <li v-if="isAuthenticated">Check that the heart icon fills/unfills based on wishlist status</li>
        </ul>
      </div>
    </div>
  </div>
</template>
