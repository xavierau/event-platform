<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

import CategoryLink from '@/components/LandingPage/CategoryLink.vue';
import EventPreviewCard from '@/components/Shared/EventPreviewCard.vue';
import EventListItem from '@/components/Shared/EventListItem.vue';
import PublicHeader from '@/components/Shared/PublicHeader.vue';
import PromotionCarousel from '@/components/Shared/PromotionCarousel.vue';
import PromotionalDisplay from '@/components/PromotionalModal/PromotionalDisplay.vue';
import { computed, onMounted, ref } from 'vue';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import type { EventItem, Promotion } from '@/types';
import Footer from '@/components/Public/Footer.vue';
import ChatbotWidget from '@/components/chatbot/ChatbotWidget.vue';

dayjs.extend(utc);

const { t } = useI18n();

const page = usePage();

interface Category {
    id: number | string;
    name: string;
    slug: string;
    href: string;
    icon?: string; // Legacy icon field (emoji/text) - will be added by mapping logic as fallback
    icon_url?: string | null; // Media library icon URL from backend
}

const props = defineProps({
    initialCategories: Array as () => Category[],
    featuredEvent: Object as () => EventItem | null,
    todayEvents: Array as () => EventItem[], // Today's events specifically
    upcomingEvents: Array as () => EventItem[], // Broader upcoming events
    moreEvents: Array as () => EventItem[],
    activePromotions: Array as () => Promotion[],
    promotionCarouselTitle: String as () => string | null,
});

// Helper for icons, derived from original placeholder data
const categoryIconMap: { [key: string]: string } = {
    concerts: '🎵',
    'music-festivals': '🎉',
    livehouse: '🎸',
    'plays-musicals': '🎭',
    'talk-shows': '🎤',
    exhibitions: '🖼️',
    comedy: '😂',
};
const categories = computed(() => {
    const mappedCategories = props.initialCategories
        ? props.initialCategories
              .map((category) => ({
                  ...category,
                  // Keep the icon_url from backend, but add fallback emoji icon for legacy support
                  icon: categoryIconMap[category.slug] || '🎶', // Fallback emoji icon if no media icon
              }))
              .slice(0, 7)
        : []; // Take only first 7 categories

    // Add the "All Events" category manually
    return [...mappedCategories, { id: 'all-events', name: 'All Events', slug: 'all', icon: '🎶', href: '/events' }];
});

// State for managing which events to show
const activeFilter = ref('upcoming'); // 'today', 'upcoming'
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

onMounted(() => {});

function showUpcomingEvents() {
    activeFilter.value = 'upcoming';
}

const showCalendar = ref(false);
const today = dayjs().utc().startOf('day').toDate();
const selectedRange = ref([today, today]);
const minDate = dayjs().utc().startOf('day').toDate();
const maxDate = dayjs().utc().add(90, 'day').endOf('day').toDate();

function openCalendar(e: MouseEvent) {
    e.preventDefault();
    console.log('Calendar button clicked');
    showCalendar.value = true;
    console.log('showCalendar set to', showCalendar.value);
}

function onDateRangeSelected([start, end]: [Date | null, Date | null]) {
    console.log('Date range selected:', start, end);
    if (start && end) {
        const startUTC = dayjs(start).utc().format('YYYY-MM-DD');
        const endUTC = dayjs(end).utc().format('YYYY-MM-DD');
        showCalendar.value = false;
        console.log('Redirecting to:', `/events?start=${startUTC}&end=${endUTC}`);
        window.location.href = `/events?start=${startUTC}&end=${endUTC}`;
    }
}
</script>

<template>
    <Head :title="t('common.welcome_title')" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Header Section (FE-LP-002) -->
        <PublicHeader />

        <!-- Promotional Modals -->
        <PromotionalDisplay page="home" />

        <main class="container mb-5 mx-auto px-4 py-4">
            <!-- Promotion Carousel Section -->
            <PromotionCarousel :promotions="activePromotions" :title="promotionCarouselTitle" class="mb-4" />

            <!-- Event Category Quick Links Section (FE-LP-003) -->
            <section id="event-categories" class="mb-6">
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-4 sm:gap-4 md:grid-cols-8">
                    <CategoryLink v-for="category in categories" :key="category.id" :category="category" />
                </div>
            </section>

            <!-- Upcoming Events Section (FE-LP-005) -->
            <section id="upcoming-events" class="mb-6">
                <div class="mb-4 flex flex-col items-start justify-between sm:flex-row sm:items-center">
                    <!-- <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-3 sm:mb-0">Upcoming Events</h2> -->
                    <div class="flex flex-wrap space-x-2 text-sm">
                        <!-- <button
                          @click="showTodayEvents"
                          :class="[
                            'px-3 py-1 rounded-full font-medium',
                            activeFilter === 'today'
                              ? 'bg-indigo-100 dark:bg-indigo-700 text-indigo-700 dark:text-indigo-200'
                              : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                          ]"
                        >
                          Today
                        </button> -->
                        <button
                            @click="showUpcomingEvents"
                            :class="[
                                'rounded-full px-3 py-1 font-medium',
                                activeFilter === 'upcoming'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-700 dark:text-indigo-200'
                                    : 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            {{ t('events.filters.upcoming') }}
                        </button>
                        <!-- <button class="px-3 py-1 rounded-full text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700" @click="goToTomorrow">Tomorrow</button> -->
                        <!-- <button class="px-3 py-1 rounded-full text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700" @click="goToThisWeek">This Week</button> -->
                        <button
                            class="flex items-center rounded-full px-3 py-1 text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="openCalendar"
                        >
                            {{ t('events.calendar') }} <span class="ml-1.5 text-base">📅</span>
                        </button>
                    </div>
                </div>
                <div
                    class="scrollbar-thin scrollbar-thumb-gray-400 dark:scrollbar-thumb-gray-500 scrollbar-track-gray-200 dark:scrollbar-track-gray-700 scrollbar-thumb-rounded-full -mb-4 flex overflow-x-auto pb-4"
                >
                    <EventPreviewCard v-for="event in displayedEvents" :key="event.id" :event="event" />
                    <!-- Add a few more for scrolling demonstration if needed -->
                    <div
                        v-if="!displayedEvents || displayedEvents.length === 0"
                        class="flex min-h-[200px] w-full items-center justify-center rounded-md border border-dashed border-gray-300 bg-white p-4 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                    >
                        <span v-if="activeFilter === 'today'">No events happening today.</span>
                        <span v-else>No upcoming events at the moment.</span>
                    </div>
                </div>
            </section>

            <!-- More Events Section (FE-LP-007) -->
            <section id="more-events">
                <div class="mb-4 flex flex-col items-start justify-between sm:flex-row sm:items-center">
                    <h2 class="mb-3 text-2xl font-semibold text-gray-800 sm:mb-0 dark:text-gray-200">{{ t('events.more_events') }}</h2>
                    <div class="flex flex-wrap space-x-2 text-sm">
                        <button class="rounded-full bg-indigo-100 px-3 py-1 font-medium text-indigo-700 dark:bg-indigo-700 dark:text-indigo-200">
                            {{ t('events.recommended') }}
                        </button>
                    </div>
                </div>
                <div>
                    <EventListItem v-for="event in moreEventsData" :key="event.id" :event="event" />
                    <div
                        v-if="!moreEventsData || moreEventsData.length === 0"
                        class="flex min-h-[150px] items-center justify-center rounded-md border border-dashed border-gray-300 bg-white p-4 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                    >
                        {{ t('events.no_more_events') }}
                    </div>
                    <!-- Potentially add a "Load More" button or pagination here -->
                </div>
            </section>
        </main>


        <Footer></Footer>

        <ChatbotWidget v-if="page.props.chatbot_enabled" />
    </div>

    <div
        v-if="showCalendar"
        style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
        "
        class="dark:bg-opacity-75 dark:bg-black"
    >
        <div
            style="background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 16px rgba(0, 0, 0, 0.15)"
            class="dark:bg-gray-800 dark:text-gray-200"
        >
            <Datepicker
                v-model="selectedRange"
                range
                inline
                :min-date="minDate"
                :max-date="maxDate"
                :enable-time-picker="false"
                @close="showCalendar = false"
                @update:model-value="onDateRangeSelected"
                :dark="true"
                calendar-cell-class-name="dp-custom-cell"
                calendar-class-name="dp-custom-calendar"
                menu-class-name="dp-custom-menu"
            />
            <button
                @click="showCalendar = false"
                style="margin-top: 16px"
                class="w-full rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
            >
                Close
            </button>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: #9ca3af #e5e7eb; /* thumb track */
}

.dark .scrollbar-thin {
    scrollbar-color: #6b7280 #374151; /* dark:thumb dark:track */
}

.scrollbar-thin::-webkit-scrollbar {
    height: 8px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 10px;
}

.dark .scrollbar-thin::-webkit-scrollbar-track {
    background: #374151; /* dark:track */
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: #9ca3af;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: #6b7280; /* dark:thumb */
    border-color: #374151; /* dark:track */
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: #6b7280;
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: #4b5563; /* dark:thumb-hover */
}

/* Custom styles for Vue Datepicker in dark mode */
:global(.dp-custom-calendar) {
    /* You might need to target specific elements within the datepicker for full control */
    --dp-background-color: #1f2937; /* dark:bg-gray-800 */
    --dp-text-color: #d1d5db; /* dark:text-gray-300 */
    --dp-hover-color: #374151; /* dark:bg-gray-700 */
    --dp-hover-text-color: #f9fafb; /* dark:text-gray-50 */
    --dp-active-color: #4f46e5; /* indigo-600 */
    --dp-active-text-color: #ffffff;
    --dp-border-color: #4b5563; /* dark:border-gray-600 */
    --dp-border-color-hover: #6b7280; /* dark:border-gray-500 */
    --dp-disabled-color: #4b5563; /* dark:text-gray-500 */
    --dp-highlight-color: rgba(79, 70, 229, 0.2);
}

:global(.dp-custom-menu) {
    background-color: #1f2937 !important; /* dark:bg-gray-800 */
    border: 1px solid #4b5563 !important; /* dark:border-gray-600 */
}

:global(.dp-custom-cell) {
    /* Example: customize individual cell appearance if needed */
}
</style>
