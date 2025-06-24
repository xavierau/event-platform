<template>
  <div v-if="links && links.length > 1" class="mt-6 flex justify-between items-center">
    <div v-if="summaryVisible" class="text-sm text-gray-700 dark:text-gray-400">
      Showing {{ from }} to {{ to }} of {{ total }} results
    </div>
    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
      <Link
        v-for="(link, index) in links"
        :key="index"
        :href="link.url || ''"
        preserve-scroll
        preserve-state
        class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
        :class="{
          'bg-indigo-500 border-indigo-500 text-white dark:bg-indigo-600 dark:border-indigo-600': link.active,
          'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700': !link.active && link.url,
          'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:border-gray-600 dark:text-gray-500': !link.url
        }"
        v-html="link.label"
        :disabled="!link.url"
      />
    </nav>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

const { links, from, to, total } = defineProps<{
  links: PaginationLink[];
  from?: number;
  to?: number;
  total?: number;
}>();

const summaryVisible = computed(() => from !== undefined && to !== undefined && total !== undefined);
</script>
