<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TicketPurchaseModal from '@/components/Modals/TicketPurchaseModal.vue';
import CustomContainer from '@/components/Shared/CustomContainer.vue';

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
  if (selectedOccurrence.value && selectedOccurrence.value.tickets && selectedOccurrence.value.tickets.length > 0) {
    showPurchaseModal.value = true;
  } else {
    // Optionally, handle the case where there are no tickets or no occurrence selected
    // For now, we can just prevent the modal from opening or show an alert.
    alert('当前场次暂无可售票品或未选择场次。');
    console.warn('Attempted to open purchase modal without tickets or selected occurrence.', selectedOccurrence.value);
  }
};

const closePurchaseModal = () => {
  showPurchaseModal.value = false;
};

const addToWishlist = () => {
  // Check if user is logged in - similar to openPurchaseModal
  if (!isAuthenticated.value) {
    // If using Ziggy for named routes, route('login') is correct.
    // If not, replace with the actual path e.g., '/login'.
    router.visit(route('login'));
    return;
  }
  // Placeholder for wishlist logic
  // TODO: Implement actual wishlist functionality (e.g., API call)
  // This will be tracked by a task in prd/tasks.md
  console.log('Add to wishlist clicked for event:', props.event.id);
  alert('想看功能待实现 (Wishlist feature pending implementation) Event ID: ' + props.event.id);
};

if (props.event.occurrences && props.event.occurrences.length > 0) {
  selectOccurrence(props.event.occurrences[0]);
}

</script>

<template>

    <CustomContainer :title="event.name" :poster_url="event.landscape_poster_url" >
        <div class="min-h-screen bg-gray-100 pb-20"> <!-- padding-bottom for fixed footer -->

    <!-- Hero/Header Section -->
    <section class="bg-white p-4 shadow-sm">
      <div class="container mx-auto flex">
        <div class="w-1/4 md:w-1/5 flex-shrink-0">
          <img :src="event.thumbnail_url || 'https://via.placeholder.com/150x200.png?text=Event'" :alt="event.name" class="w-full h-auto object-cover rounded" />
        </div>
        <div class="w-3/4 md:w-4/5 pl-4">
          <span class="inline-block bg-gray-200 text-gray-700 text-xs font-semibold px-2 py-0.5 rounded mb-1">{{ event.category_tag }}</span>
          <h1 class="text-lg md:text-xl font-bold text-gray-900 leading-tight mb-1">{{ event.name }}</h1>
          <p class="text-sm text-gray-600 mb-1">{{ heroDateRange }}</p>
          <p class="text-xs text-gray-500">{{ event.duration_info }}</p>
        </div>
      </div>
    </section>

    <!-- Price Section -->
    <section class="bg-white p-4 mt-3 shadow-sm">
      <div class="container mx-auto flex justify-between items-center">
        <div>
          <span class="text-2xl font-bold text-red-500">
            <span class="text-base">{{ eventPrice.currency }}</span>{{ eventPrice.amount }}
          </span>
          <span class="text-2xl font-bold text-red-500">{{ eventPrice.suffix }}</span>
          <span v-if="event.discount_info" class="ml-2 bg-red-100 text-red-600 text-xs font-semibold px-2 py-0.5 rounded">
            {{ event.discount_info }}
          </span>
        </div>
      </div>
    </section>

    <!-- Occurrences Section -->
    <section v-if="event.occurrences && event.occurrences.length > 1" class="bg-white pt-4 pb-2 mt-3 shadow-sm">
      <div class="container mx-auto">
        <div class="flex overflow-x-auto whitespace-nowrap pb-2 -mb-2 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 scrollbar-thumb-rounded">
          <button
            v-for="occurrence in event.occurrences"
            :key="occurrence.id"
            @click="selectOccurrence(occurrence)"
            :class="[
              'flex-shrink-0 text-center px-4 py-2 rounded-t-md mr-1 focus:outline-none relative',
              selectedOccurrence?.id === occurrence.id
                ? 'bg-pink-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            ]"
          >
            <span class="block text-sm font-medium">{{ occurrence.name }}</span>
            <span class="block text-xs">{{ occurrence.date_short }}</span>
          </button>
        </div>
      </div>
    </section>

    <!-- Selected Occurrence Date/Time and Duration -->
    <section v-if="selectedOccurrence" class="bg-white p-3 pb-4 shadow-sm" :class="{'mt-0': event.occurrences && event.occurrences.length > 0, 'mt-3': !event.occurrences || event.occurrences.length === 0 }">
      <div class="container mx-auto">
        <p class="text-base font-semibold text-gray-900">{{ selectedOccurrence.full_date_time }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ event.duration_info }}</p>
      </div>
    </section>

    <!-- Venue Information Section -->
    <section class="bg-white p-4 mt-3 shadow-sm" :class="{'mt-3': selectedOccurrence}">
      <div class="container mx-auto">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-md font-semibold mb-1">{{ currentVenueName }}</h2>
                <p class="text-sm text-gray-600 mb-2">{{ currentVenueAddress }}</p>
            </div>
          <Link href="#" class="text-sm text-indigo-600 hover:underline w-1/5 text-right">查看地图 ></Link>
        </div>
      </div>
    </section>

    <!-- Content Section ("演出介绍") -->
    <section class="bg-white p-4 mt-1 shadow-sm">
      <div class="container mx-auto">
        <h2 class="text-md font-semibold mb-3">演出介绍</h2>
        <div class="prose max-w-none" v-html="event.description_html"></div>
        <!-- Placeholder for more images/media -->
      </div>
    </section>

    <!-- Fixed Footer/Bottom Bar -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-3 shadow-top-lg z-50">
      <div class="container mx-auto flex justify-between items-center">
        <div class="flex space-x-4 text-center">
          <Link href="/" class="text-xs text-gray-600 hover:text-indigo-600">
            <!-- Placeholder for Home Icon -->
            <span class="block text-xl">🏠</span>
            <span>首页</span>
          </Link>
          <Link href="#" class="text-xs text-gray-600 hover:text-indigo-600">
            <!-- Placeholder for My Orders Icon -->
            <span class="block text-xl">🎫</span>
            <span>我的订单</span>
          </Link>
        </div>
        <div class="flex space-x-2">
          <button
            class="px-4 py-2 text-sm border border-pink-500 text-pink-500 rounded-full hover:bg-pink-50"
            @click="addToWishlist"
          >
            <!-- Placeholder for Heart Icon --> ❤️ Add to Wishlist
          </button>
          <button
            class="px-6 py-2 text-sm bg-pink-500 text-white rounded-full hover:bg-pink-600 font-semibold"
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
.shadow-top-lg {
  box-shadow: 0 -4px 6px -1px rgb(0 0 0 / 0.05), 0 -2px 4px -2px rgb(0 0 0 / 0.05);
}

/* Custom scrollbar styles */
.scrollbar-thin {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #e2e8f0; /* gray-300 gray-100 */
}

.scrollbar-thin::-webkit-scrollbar {
  height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: #e2e8f0; /* gray-100 */
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #cbd5e1; /* gray-300 */
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8; /* gray-400 */
}
</style>
