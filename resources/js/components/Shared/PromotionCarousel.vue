<script setup lang="ts">
import { computed } from 'vue';
import type { EventItem, Promotion } from '@/types';
import 'vue3-carousel/dist/carousel.css';
import { Carousel, Slide, Pagination } from 'vue3-carousel';

interface Props {
  events?: EventItem[];
  promotions?: Promotion[];
  title?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Promotions',
});

// Combine events and promotions into a unified format for display
const displayItems = computed(() => {
  const items: Array<{
    id: number;
    title: string;
    subtitle: string;
    image: string;
    url: string;
    type: 'event' | 'promotion';
  }> = [];

  // Add events
  if (props.events) {
    props.events.forEach(event => {
      items.push({
        id: event.id,
        title: event.name,
        subtitle: `${event.date_range || event.date_short} • ${event.venue_name}`,
        image: event.image_url,
        url: event.href,
        type: 'event'
      });
    });
  }

  // Add promotions
  if (props.promotions) {
    props.promotions.forEach(promotion => {
      items.push({
        id: promotion.id,
        title: promotion.title,
        subtitle: promotion.subtitle,
        image: promotion.banner,
        url: promotion.url,
        type: 'promotion'
      });
    });
  }

  return items;
});

</script>

<template>
  <section id="promotion-carousel">
    <div v-if="title" class="flex justify-between items-center mb-4">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ title }}</h2>
    </div>

    <div v-if="displayItems.length > 0" class="relative">
      <Carousel :items-to-show="1" :wrap-around="true" :autoplay="3000">
        <Slide v-for="item in displayItems" :key="item.id">
          <div class="carousel__item w-full">
            <a :href="item.url" class="block relative overflow-hidden group" style="aspect-ratio: 2.35 / 1;">
              <img :src="item.image" :alt="item.title" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent">
              </div>
            </a>
          </div>
        </Slide>
        <template #addons>
          <Pagination />
        </template>
      </Carousel>
    </div>

    <!-- Empty State -->
    <div v-else class="relative aspect-video rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
      <div class="text-center text-gray-500 dark:text-gray-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <p class="text-lg font-medium">No content available</p>
        <p class="text-sm">Check back later for updates</p>
      </div>
    </div>
  </section>
</template>

<style>
.carousel__prev,
.carousel__next {
  background-color: rgba(255, 255, 255, 0.5); /* Semi-transparent white background */
  border-radius: 50%; /* Circular buttons */
  width: 3rem; /* Adjust size as needed */
  height: 3rem; /* Adjust size as needed */
  display: flex;
  align-items: center;
  justify-content: center;
  color: #333; /* Icon color */
}

.carousel__prev:hover,
.carousel__next:hover {
  background-color: rgba(255, 255, 255, 0.8); /* Lighter background on hover */
}

.carousel__icon {
  fill: currentColor; /* Use the text color for the icon */
}

.carousel__pagination-button {
  background-color: rgba(255, 255, 255, 0.5);
}

.carousel__pagination-button--active {
  background-color: white;
}
</style>
