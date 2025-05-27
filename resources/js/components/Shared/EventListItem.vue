<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';
import WishlistButton from '@/components/Shared/WishlistButton.vue';

const props = defineProps({
  event: {
    type: Object,
    required: true,
    default: () => ({
      id: 0,
      name: 'Unnamed Event - Long Title That Might Need to Span Multiple Lines',
      href: '#',
      image_url: 'https://via.placeholder.com/300x400.png?text=Event+Portrait',
      price_from: 0,
      price_to: 0,
      date_range: 'YYYY.MM.DD - MM.DD',
      venue_name: 'Some Venue Hall - City Center Complex',
      category_name: 'Category'
    })
  },
  showWishlistButton: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['wishlistChanged', 'error']);

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
  <Link :href="event.href || '#'" class="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg dark:hover:shadow-gray-700/50 transition-shadow duration-200 ease-in-out mb-4 overflow-hidden">
    <div class="flex">
      <!-- Image Section -->
      <div class="w-1/4 md:w-1/5 flex-shrink-0">
        <div class="aspect-[9/16] bg-gray-200 dark:bg-gray-700">
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
          <div class="flex justify-between items-start mb-1">
            <span class="inline-block bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 text-xs font-semibold px-2 py-0.5 rounded-full">
              {{ event.category_name }}
            </span>
            <WishlistButton
              v-if="props.showWishlistButton"
              :event-id="event.id"
              variant="icon"
              size="sm"
              @wishlistChanged="(inWishlist) => emit('wishlistChanged', inWishlist)"
              @error="(error) => emit('error', error)"
            />
          </div>
          <h4 class="text-base md:text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1 leading-tight line-clamp-2" :title="event.name">
            {{ event.name }}
          </h4>
          <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400 mb-1">{{ event.date_range }}</p>
          <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400 truncate" :title="event.venue_name">
            {{ event.venue_name }}
          </p>
        </div>
        <div class="text-right mt-2">
          <span class="text-base md:text-lg font-bold text-red-500 dark:text-red-400">{{ formatPrice(event.price_from, event.price_to, event.currency) }}</span>
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
