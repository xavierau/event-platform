<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps({
  category: {
    type: Object,
    required: true,
    default: () => ({ id: 0, name: 'Unnamed Category', icon: '❓', href: '#' })
  }
});
</script>

<template>
  <Link :href="category.href || '#'">
    <div class="flex flex-col items-center justify-center p-3 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg dark:hover:shadow-gray-700/50 transition-all duration-200 ease-in-out transform hover:-translate-y-1 h-28 sm:h-32 border border-transparent hover:border-indigo-300 dark:hover:border-indigo-700">
      <div class="text-3xl sm:text-4xl mb-2 text-indigo-600 dark:text-indigo-400">{{ category.icon }}</div>
      <span class="text-xs sm:text-sm text-gray-700 dark:text-gray-300 font-medium text-center line-clamp-2">{{ category.name }}</span>
    </div>
  </Link>
</template>

<style scoped>
/* Scoped styles for CategoryLink.vue if needed */
</style>
