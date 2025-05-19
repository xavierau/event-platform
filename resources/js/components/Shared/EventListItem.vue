<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps({
  event: {
    type: Object,
    required: true,
    default: () => ({
      id: 0,
      name: 'Unnamed Event - Long Title That Might Need to Span Multiple Lines',
      href: '#',
      image_url: 'https://via.placeholder.com/300x400.png?text=Event+Portrait',
      price_from: 0,
      date_range: 'YYYY.MM.DD - MM.DD',
      venue_name: 'Some Venue Hall - City Center Complex',
      category_name: 'Category'
    })
  }
});

const formatPrice = (price: number) => {
  if (price === 0) return 'Free';
  return `¥${price}起`;
};

</script>

<template>
  <Link :href="event.href || '#'" class="block bg-white rounded-lg shadow hover:shadow-lg transition-shadow duration-200 ease-in-out mb-4 overflow-hidden">
    <div class="flex">
      <!-- Image Section -->
      <div class="w-1/4 md:w-1/5 flex-shrink-0">
        <div class="aspect-[9/16] bg-gray-200">
          <img
            :src="event.image_url"
            :alt="`Image for ${event.name}`"
            class="w-full h-full object-cover"
            loading="lazy"
          />
        </div>
      </div>

      <!-- Details Section -->
      <div class="w-3/4 md:w-4/5 p-4 flex flex-col justify-between">
        <div>
          <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-semibold px-2 py-0.5 rounded-full mb-1">
            {{ event.category_name }}
          </span>
          <h4 class="text-base md:text-lg font-semibold text-gray-900 mb-1 leading-tight line-clamp-2" :title="event.name">
            {{ event.name }}
          </h4>
          <p class="text-xs md:text-sm text-gray-600 mb-1">{{ event.date_range }}</p>
          <p class="text-xs md:text-sm text-gray-600 truncate" :title="event.venue_name">
            {{ event.venue_name }}
          </p>
        </div>
        <div class="text-right mt-2">
          <span class="text-base md:text-lg font-bold text-red-500">{{ formatPrice(event.price_from) }}</span>
        </div>
      </div>
    </div>
  </Link>
</template>

<style scoped>
.aspect-\[3\/4\] {
  aspect-ratio: 3 / 4;
}
</style>
