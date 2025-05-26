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

const { formatPrice: formatCurrency } = useCurrency();

const formatPrice = (price: number) => {
  if (price === 0) return 'Free';
  // Assuming price is already in currency units, convert to cents and format with CNY
  return `${formatCurrency(price * 100, 'CNY')}起`;
};

</script>

<template>
  <Link :href="event.href || '#'" class="block w-26 flex-shrink-0 mr-4 last:mr-0">
    <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 ease-in-out overflow-hidden">
      <div class="aspect-[9/16] bg-gray-200">
        <img
          :src="event.image_url"
          :alt="`Image for ${event.name}`"
          class="w-full h-full object-cover"
          loading="lazy"
        />
      </div>
      <div class="p-4">
        <h4 class="font-semibold text-gray-900 truncate mb-1" :title="event.name">
          {{ event.name }}
        </h4>
        <div class="flex justify-between items-center">
          <span class="font-bold text-indigo-600">{{ formatPrice(event.price_from) }}</span>
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
