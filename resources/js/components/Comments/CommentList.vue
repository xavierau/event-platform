<script setup lang="ts">
import CommentItem from './CommentItem.vue';
import type { PropType } from 'vue';
import type { Comment } from '@/types/comment';

defineProps({
  comments: {
    type: Array as PropType<Comment[]>,
    default: () => [],
  },
});
</script>

<template>
  <div class="mt-8">
    <h3 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">Comments</h3>
    <div v-if="comments.length > 0">
      <CommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
      />
    </div>
    <div v-else class="text-center py-8 px-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
      <p class="text-gray-500 dark:text-gray-400">
        No comments yet. Be the first to share your thoughts!
      </p>
    </div>
  </div>
</template>
