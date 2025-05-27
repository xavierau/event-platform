<script setup lang="ts">
import { ref, computed } from 'vue';
import type { EventItem, Promotion } from '@/types';

interface Props {
  events?: EventItem[];
  promotions?: Promotion[];
  title?: string;
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

const currentSlide = ref(0);

const nextSlide = () => {
  currentSlide.value = (currentSlide.value + 1) % displayItems.value.length;
};

const prevSlide = () => {
  currentSlide.value = currentSlide.value === 0 ? displayItems.value.length - 1 : currentSlide.value - 1;
};
</script>

<template>
  <section id="promotion-carousel" class="mb-12">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ title }}</h2>
      <div v-if="displayItems.length > 1" class="flex space-x-2">
        <button class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700" @click="prevSlide">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <button class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700" @click="nextSlide">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>

    <div v-if="displayItems.length > 0" class="relative">
      <div class="overflow-hidden">
        <div class="flex transition-transform duration-500 ease-in-out" :style="{ transform: `translateX(-${currentSlide * 100}%)` }">
          <div v-for="item in displayItems" :key="item.id" class="w-full flex-shrink-0">
            <a :href="item.url" class="block relative h-[400px] rounded-lg overflow-hidden group">
              <img :src="item.image" :alt="item.title" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent">
                <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                  <h3 class="text-2xl font-bold mb-2">{{ item.title }}</h3>
                  <p class="text-sm mb-4">{{ item.subtitle }}</p>
                  <div class="flex items-center space-x-2">
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm capitalize">{{ item.type }}</span>
                  </div>
                </div>
              </div>
            </a>
          </div>
        </div>
      </div>

      <!-- Slide Indicators -->
      <div v-if="displayItems.length > 1" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
        <button
          v-for="(_, index) in displayItems"
          :key="index"
          @click="currentSlide = index"
          class="w-2 h-2 rounded-full transition-colors duration-200"
          :class="currentSlide === index ? 'bg-white' : 'bg-white/50'"
        ></button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="relative h-[400px] rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
      <div class="text-center text-gray-500 dark:text-gray-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <p class="text-lg font-medium">No content available</p>
        <p class="text-sm">Check back later for exciting content!</p>
      </div>
    </div>
  </section>
</template>
