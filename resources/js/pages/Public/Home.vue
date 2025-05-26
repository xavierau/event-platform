<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import CategoryLink from '@/components/LandingPage/CategoryLink.vue';
import EventPreviewCard from '@/components/Shared/EventPreviewCard.vue';
import EventListItem from '@/components/Shared/EventListItem.vue';
import { ref, computed, onMounted } from 'vue';
import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import Datepicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

dayjs.extend(utc)

interface Category {
  id: number | string;
  name: string;
  slug: string; // slug is needed for icon mapping
  href: string;
  icon?: string; // Icon will be added by the mapping logic
}

interface EventItem {
  id: number;
  name: string;
  href: string;
  image_url: string;
  price_from: number;
  date_short?: string; // For upcoming events
  date_range?: string; // For more events
  venue_name?: string; // For more events
  category_name: string;
}

const props = defineProps({
    initialCategories: Array as () => Category[],
    featuredEvent: Object as () => EventItem | null,
    todayEvents: Array as () => EventItem[], // Today's events specifically
    upcomingEvents: Array as () => EventItem[], // Broader upcoming events
    moreEvents: Array as () => EventItem[],
});

// Helper for icons, derived from original placeholder data
const categoryIconMap: { [key: string]: string } = {
  'concerts': '🎵',
  'music-festivals': '🎉',
  'livehouse': '🎸',
  'plays-musicals': '🎭',
  'talk-shows': '🎤',
  'exhibitions': '🖼️',
  'comedy': '😂',
};
const categories = computed(() => {
  const mappedCategories = props.initialCategories ? props.initialCategories.map(category => ({
    ...category,
    icon: categoryIconMap[category.slug] || '🎶', // Default icon if slug not in map
  })).slice(0, 7) : []; // Take only first 7 categories

  // Add the "All Events" category manually
  return [
    ...mappedCategories,
    { id: 'all-events', name: 'All Events', slug: 'all', icon: '🎶', href: '/events' }
  ];
});

// State for managing which events to show
const activeFilter = ref('today'); // 'today', 'upcoming'
const displayedEvents = computed(() => {
  switch (activeFilter.value) {
    case 'today':
      return props.todayEvents || [];
    case 'upcoming':
      return props.upcomingEvents || [];
    default:
      return props.todayEvents || [];
  }
});

// Placeholder data for more events - this would also come from props eventually
const moreEventsData = ref(props.moreEvents);

onMounted(() => {
    console.log('Today events:', props.todayEvents);
    console.log('Upcoming events:', props.upcomingEvents);
    console.log('More events:', moreEventsData.value);
});

function showTodayEvents() {
  activeFilter.value = 'today';
}

function showUpcomingEvents() {
  activeFilter.value = 'upcoming';
}

function goToTomorrow() {
  const tomorrow = dayjs().utc().add(1, 'day').format('YYYY-MM-DD')
  window.location.href = `/events?start=${tomorrow}&end=${tomorrow}`
}

function goToThisWeek() {
  const startOfWeek = dayjs().utc().startOf('week').format('YYYY-MM-DD')
  const endOfWeek = dayjs().utc().endOf('week').format('YYYY-MM-DD')
  window.location.href = `/events?start=${startOfWeek}&end=${endOfWeek}`
}

const showCalendar = ref(false)
const today = dayjs().utc().startOf('day').toDate()
const selectedRange = ref([today, today])
const minDate = dayjs().utc().startOf('day').toDate()
const maxDate = dayjs().utc().add(90, 'day').endOf('day').toDate()

function openCalendar(e: MouseEvent) {
  e.preventDefault()
  console.log('Calendar button clicked')
  showCalendar.value = true
  console.log('showCalendar set to', showCalendar.value)
}

function onDateRangeSelected([start, end]: [Date | null, Date | null]) {
  console.log('Date range selected:', start, end)
  if (start && end) {
    const startUTC = dayjs(start).utc().format('YYYY-MM-DD')
    const endUTC = dayjs(end).utc().format('YYYY-MM-DD')
    showCalendar.value = false
    console.log('Redirecting to:', `/events?start=${startUTC}&end=${endUTC}`)
    window.location.href = `/events?start=${startUTC}&end=${endUTC}`
  }
}

</script>

<template>
  <Head title="Welcome to EventPlatform" />

  <div class="min-h-screen bg-gray-100">
    <!-- Header Section (FE-LP-002) -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
      <div class="container mx-auto flex justify-between items-center p-4">
        <div class="flex items-center space-x-4">
          <div class="text-sm text-gray-600 cursor-pointer hover:text-indigo-600">
            <span class="font-semibold">Nationwide</span> ▼
          </div>
          <input type="search" placeholder="Search events, artists, venues..." class="px-4 py-2 border border-gray-300 rounded-full text-sm focus:ring-indigo-500 focus:border-indigo-500 w-64 md:w-96" />
        </div>
      </div>
    </header>

    <main class="container mx-auto py-8 px-4">
      <!-- Event Category Quick Links Section (FE-LP-003) -->
      <section id="event-categories" class="mb-12">
        <div class="grid grid-cols-4 sm:grid-cols-4 md:grid-cols-8 gap-2 sm:gap-4">
          <CategoryLink v-for="category in categories" :key="category.id" :category="category" />
        </div>
      </section>

      <!-- Upcoming Events Section (FE-LP-005) -->
      <section id="upcoming-events" class="mb-12">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-3 sm:mb-0">Upcoming Events</h2>
            <div class="flex space-x-2 text-sm flex-wrap">
              <button
                @click="showTodayEvents"
                :class="[
                  'px-3 py-1 rounded-full font-medium',
                  activeFilter === 'today'
                    ? 'bg-indigo-100 text-indigo-700'
                    : 'hover:bg-gray-200'
                ]"
              >
                Today
              </button>
              <button
                @click="showUpcomingEvents"
                :class="[
                  'px-3 py-1 rounded-full font-medium',
                  activeFilter === 'upcoming'
                    ? 'bg-indigo-100 text-indigo-700'
                    : 'hover:bg-gray-200'
                ]"
              >
                Upcoming
              </button>
              <button class="px-3 py-1 rounded-full hover:bg-gray-200" @click="goToTomorrow">Tomorrow</button>
              <button class="px-3 py-1 rounded-full hover:bg-gray-200" @click="goToThisWeek">This Week</button>
              <button class="px-3 py-1 rounded-full hover:bg-gray-200 flex items-center" @click="openCalendar">
                Calendar <span class="ml-1.5 text-base">📅</span>
              </button>
            </div>
          </div>
          <div class="flex overflow-x-auto pb-4 -mb-4 scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-200 scrollbar-thumb-rounded-full">
            <EventPreviewCard v-for="event in displayedEvents" :key="event.id" :event="event" />
            <!-- Add a few more for scrolling demonstration if needed -->
             <div v-if="!displayedEvents || displayedEvents.length === 0" class="w-full p-4 border border-dashed border-gray-300 rounded-md min-h-[200px] text-center text-gray-500 flex items-center justify-center">
                <span v-if="activeFilter === 'today'">No events happening today.</span>
                <span v-else>No upcoming events at the moment.</span>
            </div>
          </div>
      </section>

      <!-- More Events Section (FE-LP-007) -->
      <section id="more-events">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
          <h2 class="text-2xl font-semibold text-gray-800 mb-3 sm:mb-0">More Events</h2>
          <div class="flex space-x-2 text-sm flex-wrap">
            <button class="px-3 py-1 rounded-full hover:bg-gray-200">All Categories ▼</button>
            <button class="px-3 py-1 rounded-full hover:bg-gray-200">All Times ▼</button>
            <button class="px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 font-medium">Recommended</button>
            <button class="px-3 py-1 rounded-full hover:bg-gray-200">Nearest</button>
          </div>
        </div>
        <div>
          <EventListItem v-for="event in moreEventsData" :key="event.id" :event="event" />
          <div v-if="!moreEventsData || moreEventsData.length === 0" class="p-4 border border-dashed border-gray-300 rounded-md min-h-[150px] text-center text-gray-500 flex items-center justify-center">
            No more events to display.
          </div>
          <!-- Potentially add a "Load More" button or pagination here -->
        </div>
      </section>
    </main>

    <footer class="bg-gray-800 text-white p-8 text-center mt-12">
      <p>&copy; {{ new Date().getFullYear() }} EventPlatform. All rights reserved. Made with ❤️</p>
    </footer>
  </div>

  <div v-if="showCalendar" style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;display:flex;justify-content:center;align-items:center;background:rgba(0,0,0,0.3);">
    <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.15);">
      <Datepicker
        v-model="selectedRange"
        range
        inline
        :min-date="minDate"
        :max-date="maxDate"
        :enable-time-picker="false"
        @close="showCalendar = false"
        @update:model-value="onDateRangeSelected"
      />
      <button @click="showCalendar = false" style="margin-top:16px;">Close</button>
    </div>
  </div>
</template>

<style scoped>
.scrollbar-thin {
  scrollbar-width: thin;
  scrollbar-color: #9ca3af #e5e7eb;
}

.scrollbar-thin::-webkit-scrollbar {
  height: 8px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: #e5e7eb;
  border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #9ca3af;
  border-radius: 10px;
  border: 2px solid #e5e7eb;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: #6b7280;
}
</style>
