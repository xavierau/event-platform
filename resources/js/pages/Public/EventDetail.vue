<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import TicketPurchaseModal from '@/components/Modals/TicketPurchaseModal.vue';
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import WishlistButton from '@/components/Shared/WishlistButton.vue';

import type { PublicTicketType } from '@/types/ticket';

interface EventOccurrence {
  id: string | number;
  name: string; // e.g., "上海站", "深圳站"
  date_short: string; // e.g., "06.14"
  full_date_time: string; // e.g., "2025.06.21 周六 19:00"
  status_tag?: string; // e.g., "预约"
  venue_name?: string; // Venue specific to this occurrence
  venue_address?: string; // Address specific to this occurrence
  tickets?: PublicTicketType[]; // Added tickets array
}

interface EventDetails {
  id: string | number;
  name: string;
  category_tag: string;
  duration_info: string;
  price_range: string;
  discount_info?: string;
  venue_name: string; // Default/main venue name
  venue_address: string; // Default/main venue address
  tags?: string[]; // Made optional as it was removed from user's last template for venue section
  rating?: number;
  rating_count?: number;
  reviews_summary?: string;
  review_highlight_link_text?: string;
  want_to_see_count?: number;
  main_poster_url?: string;
  thumbnail_url?: string;
  description_html?: string;
  occurrences?: EventOccurrence[];
  landscape_poster_url?: string;
}

// Props will be passed from the controller, containing the event details
const props = defineProps({
  event: {
    type: Object as () => EventDetails,
    required: true,
  },
  // It's good practice to explicitly define auth props if they are passed directly
  // However, $page.props.auth.user is generally available globally if middleware is set up.
});

const page = usePage(); // Get access to $page

// Accessing auth state for login check
// Ensure your HandleInertiaRequests middleware shares 'auth.user'
const isAuthenticated = computed(() => !!(page.props.auth as any)?.user);

const selectedOccurrence = ref<EventOccurrence | undefined>(
  props.event.occurrences && props.event.occurrences.length > 1
    ? props.event.occurrences[1] // Default to 2nd item if exists (e.g. Shenzhen)
    : (props.event.occurrences && props.event.occurrences.length > 0 ? props.event.occurrences[0] : undefined) // Else 1st or undefined
);

const selectOccurrence = (occurrence: EventOccurrence) => {
  selectedOccurrence.value = occurrence;
};

const formatPrice = (priceRange: string | null) => {
  if (!priceRange) {
    return { currency: '', amount: 'Free', suffix: '' };
  }

  // Try to extract currency symbol and amount from the formatted price range
  const parts = priceRange.match(/([¥€$£₩฿RM₱₫Rp₹]|HK\$|NT\$|S\$|A\$|C\$)?([0-9]+(?:\.[0-9]+)?)(.*)/);
  if (parts) {
    return {
      currency: parts[1] || '',
      amount: parts[2],
      suffix: parts[3] || ''
    };
  }
  return { currency: '', amount: priceRange, suffix: '' };
};

const eventPrice = formatPrice(props.event.price_range);

// Computed property for the overall date range shown in the hero section
const heroDateRange = computed(() => {
  if (props.event.occurrences && props.event.occurrences.length > 0) {
    const firstDate = props.event.occurrences[0].full_date_time.split(' ')[0];
    const lastDate = props.event.occurrences[props.event.occurrences.length - 1].full_date_time.split(' ')[0];
    if (firstDate === lastDate) return firstDate;
    return `${firstDate} - ${lastDate}`;
  }
  return props.event.duration_info; // Fallback to duration_info if no occurrences for a date indication
});

const currentVenueName = computed(() => {
  return selectedOccurrence.value?.venue_name || props.event.venue_name;
});

const currentVenueAddress = computed(() => {
  return selectedOccurrence.value?.venue_address || props.event.venue_address;
});

// Added for modal visibility
const showPurchaseModal = ref(false);

const openPurchaseModal = () => {
  // Check if user is logged in
  if (!isAuthenticated.value) {
    console.log('not authenticated');

    // If using Ziggy for named routes, route('login') is correct.
    // If not, replace with the actual path e.g., '/login'.
    router.visit(route('login'));
    return;
  }

  // Existing logic to open modal if tickets are available
  if (selectedOccurrence.value && selectedOcurrenceHasTickets.value) {
    showPurchaseModal.value = true;
  } else {
    // Optionally, handle the case where there are no tickets or no occurrence selected
    // For now, we can just prevent the modal from opening or show an alert.
    alert('No tickets available for this occurrence.');
    console.warn('Attempted to open purchase modal without tickets or selected occurrence.', selectedOccurrence.value);
  }
};

const closePurchaseModal = () => {
  showPurchaseModal.value = false;
};

// Wishlist functionality is now handled by the WishlistButton component
const handleWishlistChanged = (inWishlist: boolean) => {
  console.log(`Event ${props.event.id} wishlist status changed:`, inWishlist);
  // You can add additional logic here if needed, such as showing a toast notification
};

const handleWishlistError = (message: string) => {
  console.error('Wishlist error:', message);
  // You can show a toast notification or handle the error as needed
  alert(`Wishlist error: ${message}`);
};

if (props.event.occurrences && props.event.occurrences.length > 0) {
  selectOccurrence(props.event.occurrences[0]);
}

const selectedOcurrenceHasTickets = computed(() => {
  return selectedOccurrence.value?.tickets && selectedOccurrence.value?.tickets?.length > 0;
});

onMounted(() => {
    console.log(props.event);
});

</script>

<template>

    <CustomContainer :title="event.name" :poster_url="event.landscape_poster_url" >
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 pb-20"> <!-- padding-bottom for fixed footer -->

    <!-- Hero/Header Section -->
    <section class="bg-white dark:bg-gray-800 p-4 shadow-sm">
      <div class="container mx-auto flex">
        <div class="w-1/4 md:w-1/5 flex-shrink-0">
          <img :src="event.thumbnail_url || 'https://via.placeholder.com/150x200.png?text=Event'" :alt="event.name" class="w-full h-auto object-cover rounded" />
        </div>
        <div class="w-3/4 md:w-4/5 pl-4">
          <span class="inline-block bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold px-2 py-0.5 rounded mb-1">{{ event.category_tag }}</span>
          <h1 class="text-lg md:text-xl font-bold text-gray-900 dark:text-gray-100 leading-tight mb-1">{{ event.name }}</h1>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ heroDateRange }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">{{ event.duration_info }}</p>
        </div>
      </div>
    </section>

    <!-- Price Section -->
    <section class="bg-white dark:bg-gray-800 p-4 mt-3 shadow-sm" v-if="selectedOcurrenceHasTickets">
      <div class="container mx-auto flex justify-between items-center">
        <div>
          <span class="text-2xl font-bold text-red-500 dark:text-red-400">
            <span class="text-base">{{ eventPrice.currency }}</span>{{ eventPrice.amount }}
          </span>
          <span class="text-2xl font-bold text-red-500 dark:text-red-400">{{ eventPrice.suffix }}</span>
          <span v-if="event.discount_info" class="ml-2 bg-red-100 dark:bg-red-700 text-red-600 dark:text-red-300 text-xs font-semibold px-2 py-0.5 rounded">
            {{ event.discount_info }}
          </span>
        </div>
      </div>
    </section>

    <!-- Occurrences Section -->
    <section v-if="event.occurrences && event.occurrences.length > 1" class="bg-white dark:bg-gray-800 pt-4 pb-2 mt-3 shadow-sm">
      <div class="container mx-auto">
        <div class="flex flex-row overflow-x-auto whitespace-nowrap pb-2 -mb-2 scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600 scrollbar-track-gray-100 dark:scrollbar-track-gray-700 scrollbar-thumb-rounded">
          <button
            v-for="occurrence in event.occurrences"
            :key="occurrence.id"
            @click="selectOccurrence(occurrence)"
            :class="[
              'flex-shrink-0 text-center px-4 py-2 rounded-t-md mr-1 focus:outline-none relative',
              selectedOccurrence?.id === occurrence.id
                ? 'bg-pink-500 text-white'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
            ]"
          >
            <span class="block text-sm font-medium">{{ occurrence.name }}</span>
            <span class="block text-xs">{{ occurrence.date_short }}</span>
          </button>
        </div>
      </div>
    </section>

    <!-- Selected Occurrence Date/Time and Duration -->
    <section v-if="selectedOccurrence" class="bg-white dark:bg-gray-800 p-3 pb-4 shadow-sm" :class="{'mt-0': event.occurrences && event.occurrences.length > 0, 'mt-3': !event.occurrences || event.occurrences.length === 0 }">
      <div class="container mx-auto">
        <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ selectedOccurrence.full_date_time }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ event.duration_info }}</p>
      </div>
    </section>

    <!-- Venue Information Section -->
    <section class="bg-white dark:bg-gray-800 p-4 mt-3 shadow-sm" :class="{'mt-3': selectedOccurrence}">
      <div class="container mx-auto">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-md font-semibold mb-1 text-gray-900 dark:text-gray-100">{{ currentVenueName }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ currentVenueAddress }}</p>
            </div>
          <a
            :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(currentVenueAddress)}`"
            target="_blank"
            class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline w-1/5 text-right"
          >
            View Map >
          </a>
        </div>
      </div>
    </section>

    <!-- Event Description Section  -->
    <section class="bg-white dark:bg-gray-800 p-4 mt-1 shadow-sm">
      <div class="container mx-auto max-w-full">
        <h2 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">Event Description</h2>
        <div class="prose dark:prose-invert max-w-full prose-img:max-w-full prose-img:h-auto break-words event-description" v-html="event.description_html"></div>
        <!-- Placeholder for more images/media -->
      </div>
    </section>

    <!-- Fixed Footer/Bottom Bar -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-3 shadow-top-lg z-50 max-w-[100vw] overflow-hidden">
      <div class="container mx-auto flex justify-between items-center min-w-0">
        <div class="flex space-x-2 sm:space-x-4 text-center flex-shrink-0">
          <Link href="/" class="text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
            <!-- Placeholder for Home Icon -->
            <span class="block text-xl">🏠</span>
            <span class="hidden sm:inline">Home</span>
          </Link>
          <Link href="/my-bookings" class="text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
            <!-- Placeholder for My Orders Icon -->
            <span class="block text-xl">🎫</span>
            <span class="hidden sm:inline">My Bookings</span>
          </Link>
        </div>
        <div class="flex space-x-1 sm:space-x-2 flex-shrink-0 min-w-0">
          <WishlistButton
            :event-id="Number(event.id)"
            variant="button"
            size="sm"
            :show-text="false"
            @wishlist-changed="handleWishlistChanged"
            @error="handleWishlistError"
          />
          <button
            class="px-3 sm:px-6 py-2 text-sm bg-pink-500 hover:bg-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700 text-white rounded-full font-semibold whitespace-nowrap"
            @click="openPurchaseModal"
          >
            Purchase
          </button>
        </div>
      </div>
    </footer>

    <!-- Ticket Purchase Modal -->
    <TicketPurchaseModal
      :show-modal="showPurchaseModal"
      :occurrence="selectedOccurrence"
      @close="closePurchaseModal"
    />
    </div>

</CustomContainer>


</template>

<style scoped>
.prose :where(img):not(:where([class~="not-prose"] *)) {
    margin-top: 0;
    margin-bottom: 0;
}

/* Responsive YouTube and video embeds */
.event-description :deep(iframe) {
  max-width: 100%;
  height: auto;
  aspect-ratio: 16/9;
}

.event-description :deep(iframe[src*="youtube.com"]),
.event-description :deep(iframe[src*="youtu.be"]),
.event-description :deep(iframe[src*="vimeo.com"]) {
  width: 100%;
  max-width: 100%;
  height: auto;
  aspect-ratio: 16/9;
}

/* Responsive video containers */
.event-description :deep(.video-container),
.event-description :deep(.embed-responsive) {
  position: relative;
  width: 100%;
  max-width: 100%;
  aspect-ratio: 16/9;
  overflow: hidden;
}

.event-description :deep(.video-container iframe),
.event-description :deep(.embed-responsive iframe) {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.shadow-top-lg {
  box-shadow: 0 -4px 6px -1px rgb(0 0 0 / 0.05), 0 -2px 4px -2px rgb(0 0 0 / 0.05);
}

/* Custom scrollbar styles */
.scrollbar-thin {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #e2e8f0; /* gray-300 gray-100 */
}

.dark .scrollbar-thin { /* Target for dark mode */
  scrollbar-color: #4b5563 #374151; /* dark:gray-600 dark:gray-700 */
}

.scrollbar-thin::-webkit-scrollbar {
  height: 6px;
  width: 6px; /* Added for vertical scrollbars if any */
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: #e2e8f0; /* gray-100 */
  border-radius: 3px;
}

.dark .scrollbar-thin::-webkit-scrollbar-track {
  background: #374151; /* dark:gray-700 */
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #cbd5e1; /* gray-300 */
  border-radius: 3px;
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #4b5563; /* dark:gray-600 */
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8; /* gray-400 */
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: #6b7280; /* dark:gray-500 */
}
</style>
