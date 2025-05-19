<script setup lang="ts">
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import EventListItem from '@/components/Shared/EventListItem.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import dayjs from 'dayjs';

const props = defineProps({
  title: String,
  poster_url: { type: String, default: null },
  events: {
    type: Array,
    default: () => []
  }
});

const hasEvents = computed(() => props.events && props.events.length > 0);

const page = usePage();
const query = computed(() => page.url.split('?')[1] ? Object.fromEntries(new URLSearchParams(page.url.split('?')[1])) : {});

const subtitle = computed(() => {
  const { start, end } = query.value;
  if (start && end) {
    return `日期区间：${dayjs(start).format('YYYY-MM-DD')} 至 ${dayjs(end).format('YYYY-MM-DD')}`;
  } else if (start) {
    return `起始日期：${dayjs(start).format('YYYY-MM-DD')}`;
  } else if (end) {
    return `结束日期：${dayjs(end).format('YYYY-MM-DD')}`;
  }
  return undefined;
});
</script>

<template>
  <CustomContainer :title="title" :poster_url="poster_url" :subtitle="subtitle">
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
