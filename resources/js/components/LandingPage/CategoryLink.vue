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
    <div class="flex flex-col items-center justify-center p-3 bg-white rounded-lg shadow hover:shadow-lg transition-all duration-200 ease-in-out transform hover:-translate-y-1 h-24 sm:h-28">
      <div class="text-3xl sm:text-4xl mb-2 text-indigo-600">{{ category.icon }}</div>
      <span class="text-xs sm:text-sm text-gray-700 font-medium text-center line-clamp-2">{{ category.name }}</span>
    </div>
  </Link>
</template>

<style scoped>
/* Scoped styles for CategoryLink.vue if needed */
</style>
