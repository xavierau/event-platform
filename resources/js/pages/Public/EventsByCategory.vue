<script setup lang="ts">
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import EventListItem from '@/components/Shared/EventListItem.vue';
import { computed } from 'vue';

interface EventItemType {
  id: number | string;
  name: string;
  href?: string;
  image_url?: string;
  price_from?: number;
  price_to?: number;
  date_range?: string;
  venue_name?: string;
  category_name?: string;
  currency?: string;
  // Add other properties as needed based on what EventListItem uses
}

const props = defineProps({
  title: {
    type: String,
    default: 'Events' // Provide a default title
  },
  poster_url: { type: String, default: null },
  events: {
    type: Array as () => EventItemType[],
    default: () => []
  }
});

const hasEvents = computed(() => props.events && props.events.length > 0);
</script>

<template>
  <CustomContainer :title="props.title" :poster_url="props.poster_url">
    <div class="container mx-auto py-8 px-4">
      <div v-if="hasEvents">
        <EventListItem v-for="event in props.events" :key="event.id" :event="event" />
      </div>
      <div v-else class="p-8 text-center text-gray-400 dark:text-gray-500 text-lg">
        No related events at the moment
      </div>
    </div>
  </CustomContainer>
</template>
