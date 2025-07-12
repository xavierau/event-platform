<script setup lang="ts">
import { computed } from 'vue';
import { format, parseISO } from 'date-fns';
import type { PropType } from 'vue';
import type { Comment } from '@/types/comment';

const props = defineProps({
  comment: {
    type: Object as PropType<Comment>,
    required: true,
  },
});

const formattedDate = computed(() => {
  if (props.comment.created_at) {
    return format(parseISO(props.comment.created_at), 'PPP p');
  }
  return '';
});
</script>

<template>
  <div class="p-4 mb-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
    <div class="flex items-center mb-2">
      <img
        :src="comment.user?.avatar_url || '/images/default-avatar.png'"
        alt="User Avatar"
        class="w-8 h-8 rounded-full mr-3"
      >
      <div>
        <p class="font-semibold text-gray-900 dark:text-white">
          {{ comment.user?.name || 'Anonymous' }}
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ formattedDate }}
        </p>
      </div>
    </div>
    <p class="text-gray-700 dark:text-gray-300">
      {{ comment.content }}
    </p>
  </div>
</template>
