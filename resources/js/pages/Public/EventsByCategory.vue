<script setup lang="ts">
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import EventListItem from '@/components/Shared/EventListItem.vue';
import { computed } from 'vue';

const props = defineProps({
  title: String,
  poster_url: { type: String, default: null },
  events: {
    type: Array,
    default: () => []
  }
});

const hasEvents = computed(() => props.events && props.events.length > 0);
</script>

<template>
  <CustomContainer :title="title" :poster_url="poster_url">
    <div class="container mx-auto py-8 px-4">
      <div v-if="hasEvents">
        <EventListItem v-for="event in events" :key="event.id" :event="event as any" />
      </div>
      <div v-else class="p-8 text-center text-gray-400 text-lg">
        暂无相关活动
      </div>
    </div>
  </CustomContainer>
</template>
