<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import BookingItemComponent from '@/components/Shared/BookingItem.vue';
import BookingDetailsModal from '@/components/Modals/BookingDetailsModal.vue';
import type { BookingItem } from '@/types/booking';
import FrontendFooter from '@/components/FrontendFooter.vue';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

dayjs.extend(utc);

const { t } = useI18n();

const props = defineProps({
  bookings: {
    type: Array as () => BookingItem[],
    default: () => []
  }
});

// State for managing which bookings to show
const activeFilter = ref('upcoming'); // 'upcoming', 'past', 'all'

// State for booking details modal
const showDetailsModal = ref(false);
const selectedBooking = ref<BookingItem | null>(null);

const filteredBookings = computed(() => {
  if (!props.bookings) return [];

  const now = dayjs().utc();

  switch (activeFilter.value) {
    case 'upcoming':
      return props.bookings.filter(booking => {
        // Check if any event occurrence is upcoming
        if (!booking.event_occurrences?.length) return true; // Show if no date info
        return booking.event_occurrences.some(occurrence =>
          occurrence.start_at && dayjs(occurrence.start_at).utc().isAfter(now)
        );
      });
    case 'past':
      return props.bookings.filter(booking => {
        // Check if all event occurrences are past
        if (!booking.event_occurrences?.length) return false; // Don't show if no date info
        return booking.event_occurrences.every(occurrence =>
          occurrence.start_at && dayjs(occurrence.start_at).utc().isBefore(now)
        );
      });
    case 'all':
    default:
      return props.bookings;
  }
});

const groupedBookings = computed(() => {
  const groups: { [key: string]: BookingItem[] } = {};

  filteredBookings.value.forEach(booking => {
    const eventName = booking.event?.name || 'Unknown Event';
    const groupKey = eventName;

    if (!groups[groupKey]) {
      groups[groupKey] = [];
    }
    groups[groupKey].push(booking);
  });

  return groups;
});

function setFilter(filter: string) {
  activeFilter.value = filter;
}

function formatEventDate(startAt?: string, endAt?: string): string {
  if (!startAt) return t('common.date_tbd');

  const start = dayjs(startAt);
  const end = endAt ? dayjs(endAt) : null;

  if (end && !start.isSame(end, 'day')) {
    return `${start.format('MMM DD')} - ${end.format('MMM DD, YYYY')}`;
  }

  return start.format('MMM DD, YYYY • h:mm A');
}

function showTicketDetails(booking: BookingItem) {
  selectedBooking.value = booking;
  showDetailsModal.value = true;
}

function closeDetailsModal() {
  showDetailsModal.value = false;
  selectedBooking.value = null;
}

function refreshBookings() {
  // Refresh the current page to get updated booking statuses
  router.reload({ only: ['bookings'] });
}
</script>

<template>
  <Head :title="t('bookings.my_bookings')" />

  <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <!-- Header Section -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50 border-b dark:border-gray-700">
      <div class="container mx-auto flex items-center p-4 relative">
        <Link href="/" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 absolute left-4">
          {{ t('common.back_arrow') }}
        </Link>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 flex-1 text-center">{{ t('navigation.my_bookings') }}</h1>
      </div>
    </header>

    <main class="container mx-auto py-6 px-4 pb-24">
      <!-- Filter Section -->
      <section class="mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4 sm:mb-0">{{ t('bookings.your_tickets') }}</h2>
          <div class="flex space-x-2 text-sm flex-wrap">
            <button
              @click="setFilter('upcoming')"
              :class="[
                'px-3 py-1 rounded-full font-medium',
                activeFilter === 'upcoming'
                  ? 'bg-indigo-100 dark:bg-indigo-700 text-indigo-700 dark:text-indigo-200'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
              ]"
            >
              {{ t('bookings.filters.upcoming') }}
            </button>
            <button
              @click="setFilter('past')"
              :class="[
                'px-3 py-1 rounded-full font-medium',
                activeFilter === 'past'
                  ? 'bg-indigo-100 dark:bg-indigo-700 text-indigo-700 dark:text-indigo-200'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
              ]"
            >
              {{ t('bookings.filters.past') }}
            </button>
            <button
              @click="setFilter('all')"
              :class="[
                'px-3 py-1 rounded-full font-medium',
                activeFilter === 'all'
                  ? 'bg-indigo-100 dark:bg-indigo-700 text-indigo-700 dark:text-indigo-200'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
              ]"
            >
              {{ t('bookings.filters.all') }}
            </button>
          </div>
        </div>
      </section>

      <!-- Bookings Section -->
      <section v-if="Object.keys(groupedBookings).length > 0">
        <div class="space-y-6">
          <div v-for="(bookings, eventKey) in groupedBookings" :key="eventKey" class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <!-- Event Header -->
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ eventKey }}</h3>

              <!-- Show all event occurrences for this booking -->
              <div v-if="bookings[0]?.event_occurrences?.length" class="mt-4">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ t('bookings.valid_dates') }}</div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  <div
                    v-for="occurrence in bookings[0].event_occurrences"
                    :key="occurrence.id"
                    class="bg-white dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600 shadow-sm"
                  >
                    <div class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ occurrence.name || t('common.event_date') }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                      {{ formatEventDate(occurrence.start_at, occurrence.end_at) }}
                    </div>
                    <div v-if="occurrence.venue_name" class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex items-center">
                      <span class="mr-1">📍</span>
                      <span class="truncate">{{ occurrence.venue_name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Booking Items -->
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
              <BookingItemComponent
                v-for="booking in bookings"
                :key="booking.id"
                :booking="booking"
                @show-details="showTicketDetails"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- Empty State -->
      <section v-else class="text-center py-12">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8">
          <div class="text-6xl mb-4">🎫</div>
          <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
            <span v-if="activeFilter === 'upcoming'">{{ t('bookings.empty.no_upcoming') }}</span>
            <span v-else-if="activeFilter === 'past'">{{ t('bookings.empty.no_past') }}</span>
            <span v-else>{{ t('bookings.empty.no_bookings') }}</span>
          </h3>
          <p class="text-gray-600 dark:text-gray-300 mb-6">
            <span v-if="activeFilter === 'upcoming'">{{ t('bookings.empty.upcoming_description') }}</span>
            <span v-else-if="activeFilter === 'past'">{{ t('bookings.empty.past_description') }}</span>
            <span v-else>{{ t('bookings.empty.all_description') }}</span>
          </p>
          <Link
            href="/"
            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
          >
            {{ t('events.browse_events') }}
          </Link>
        </div>
      </section>

    </main>

   <FrontendFooter />

    <!-- Booking Details Modal -->
    <BookingDetailsModal
      :show-modal="showDetailsModal"
      :booking="selectedBooking"
      @close="closeDetailsModal"
      @refresh-bookings="refreshBookings"
    />
  </div>
</template>

<style scoped>
.shadow-top-lg {
  box-shadow: 0 -4px 6px -1px rgb(0 0 0 / 0.05), 0 -2px 4px -2px rgb(0 0 0 / 0.05);
}
</style>
