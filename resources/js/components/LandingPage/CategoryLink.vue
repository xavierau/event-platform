<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Category {
  id: number | string;
  name: string;
  slug?: string;
  href: string;
  icon?: string; // Legacy icon field (emoji/text)
  icon_url?: string | null; // Media library icon URL
}

const props = defineProps<{
  category: Category;
}>();

// Computed property to determine which icon to display
const iconDisplay = computed(() => {
  // If we have a media icon URL, use it
  if (props.category.icon_url) {
    return {
      type: 'image',
      src: props.category.icon_url,
      alt: `${props.category.name} icon`
    };
  }

  // If we have a legacy icon (emoji/text), use it
  if (props.category.icon) {
    return {
      type: 'emoji',
      content: props.category.icon
    };
  }

  // Default fallback icon
  return {
    type: 'emoji',
    content: '📂' // Folder icon as default
  };
});
</script>

<template>
  <Link :href="category.href || '#'">
    <div class="flex flex-col items-center justify-center p-1 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg dark:hover:shadow-gray-700/50 transition-all duration-200 ease-in-out transform hover:-translate-y-1 h-20 sm:h-32 border border-transparent hover:border-indigo-300 dark:hover:border-indigo-700">
      <!-- Icon Display -->
      <div class="mb-2 flex items-center justify-center">
        <!-- Image Icon (from media library) -->
        <img
          v-if="iconDisplay.type === 'image'"
          :src="iconDisplay.src"
          :alt="iconDisplay.alt"
          class="w-12 h-12 sm:w-12 sm:h-12 object-cover rounded-md"
        />
        <!-- Emoji/Text Icon (legacy or fallback) -->
        <div
          v-else
          class="text-4xl sm:text-4xl text-indigo-600 dark:text-indigo-400"
        >
          {{ iconDisplay.content }}
        </div>
      </div>
      <span class="text-xs sm:text-sm text-gray-700 dark:text-gray-300 font-medium text-center line-clamp-2">{{ category.name }}</span>
    </div>
  </Link>
</template>

<style scoped>
/* Scoped styles for CategoryLink.vue if needed */
</style>
