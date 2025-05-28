<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';

defineProps({
  event: {
    type: Object,
    required: true,
    default: () => ({
      id: 0,
      name: 'Unnamed Event',
      href: '#',
      image_url: 'https://via.placeholder.com/400x300.png?text=Event+Image',
      price_from: 0,
      date_short: 'JAN 01',
      category_name: 'Music' // Example, can be adapted
    })
  }
});

const { formatPrice: formatCurrency, formatPriceRange } = useCurrency();

const formatPrice = (priceFrom: number, priceTo?: number, currency: string = 'USD') => {
  if (priceFrom === 0) return 'Free';

  // Backend already sends prices in currency units (divided by 100)
  // Convert back to cents for the currency formatter
  const priceFromCents = Math.round(priceFrom * 100);

  if (priceTo && priceTo !== priceFrom) {
    const priceToCents = Math.round(priceTo * 100);
    return `${formatPriceRange(priceFromCents, priceToCents, currency)}`;
  }

  return `${formatCurrency(priceFromCents, currency)}起`;
};

</script>

<template>
  <Link :href="event.href || '#'" class="block w-26 flex-shrink-0 mr-4 last:mr-0">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl dark:hover:shadow-gray-700/50 transition-shadow duration-300 ease-in-out overflow-hidden border border-transparent hover:border-indigo-300 dark:hover:border-indigo-700">
      <div class="aspect-[9/16] bg-gray-200 dark:bg-gray-700">
        <img
          :src="event.image_url"
          :alt="`Image for ${event.name}`"
          class="w-full h-full object-cover"
          loading="lazy"
        />
      </div>
      <div class="p-4">
        <h4 class="font-semibold text-gray-900 dark:text-gray-100 truncate mb-1" :title="event.name">
          {{ event.name }}
        </h4>
        <div class="flex justify-between items-center">
          <span class="text-indigo-600 dark:text-indigo-400">{{ formatPrice(event.price_from, event.price_to, event.currency) }}</span>
        </div>
      </div>
    </div>
  </Link>
</template>

<style scoped>
/* Add any specific styles for EventCard.vue if needed */
.aspect-\[4\/3\] {
  /* This class is no longer used by the template, but we can leave it or remove it. */
  /* For clarity, let's update it if we remove the 4/3 from the template, or add the new one */
  aspect-ratio: 4 / 3;
}
.aspect-\[3\/4\] { /* Added new class for portrait aspect ratio */
  aspect-ratio: 3 / 4;
}
</style>
