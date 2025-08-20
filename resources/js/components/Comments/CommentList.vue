<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import CommentItem from './CommentItem.vue';
import CommentForm from './CommentForm.vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { PropType } from 'vue';
import type { Comment } from '@/types/comment';

interface Props {
  commentableType: string;
  commentableId: number;
  initialComments?: Comment[];
  canComment?: boolean;
  showForm?: boolean;
  perPage?: number;
}

const props = withDefaults(defineProps<Props>(), {
  initialComments: () => [],
  canComment: false,
  showForm: true,
  perPage: 15,
});

const comments = ref<Comment[]>(props.initialComments);
const loading = ref(false);
const hasMore = ref(true);
const currentPage = ref(1);

const totalComments = computed(() => comments.value.length);

const loadComments = async (page = 1) => {
  if (loading.value) return;

  loading.value = true;

  try {
    const response = await fetch(`/api/comments?commentable_type=${encodeURIComponent(props.commentableType)}&commentable_id=${props.commentableId}&per_page=${props.perPage}&page=${page}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    const data = await response.json();

    if (page === 1) {
      comments.value = data.data || [];
    } else {
      comments.value.push(...(data.data || []));
    }

    hasMore.value = data.next_page_url !== null;
    currentPage.value = page;
  } catch (error) {
    console.error('Error loading comments:', error);
  } finally {
    loading.value = false;
  }
};

const loadMore = () => {
  if (hasMore.value && !loading.value) {
    loadComments(currentPage.value + 1);
  }
};

const onCommentAdded = (newComment: Comment) => {
  comments.value.unshift(newComment);
};

onMounted(() => {
  if (props.initialComments.length === 0) {
    loadComments();
  }
});
</script>

<template>
  <div class="mt-8">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
        Comments
        <span v-if="totalComments > 0" class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
          ({{ totalComments }})
        </span>
      </h3>
    </div>

    <!-- Comment Form -->
<!--    <CommentForm-->
<!--      v-if="showForm && canComment"-->
<!--      :commentable-type="commentableType"-->
<!--      :commentable-id="commentableId"-->
<!--      @comment-added="onCommentAdded"-->
<!--      class="mb-6"-->
<!--    />-->

    <!-- Comments List -->
    <div v-if="comments.length > 0" class="space-y-4">
      <CommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :commentable-type="commentableType"
        :commentable-id="commentableId"
      />

      <!-- Load More Button -->
      <div v-if="hasMore" class="text-center pt-4">
        <button
          @click="loadMore"
          :disabled="loading"
          class="px-4 py-2 text-sm text-blue-600 hover:text-blue-700 disabled:opacity-50"
        >
          <span v-if="loading">Loading...</span>
          <span v-else>Load more comments</span>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading" class="text-center py-8 px-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
      <p class="text-gray-500 dark:text-gray-400">
        No comments yet. Be the first to share your thoughts!
      </p>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading && comments.length === 0" class="space-y-4">
      <div v-for="i in 3" :key="i" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <div class="flex items-center space-x-3 mb-3">
          <Skeleton class="h-8 w-8 rounded-full" />
          <div class="space-y-2">
            <Skeleton class="h-4 w-24" />
            <Skeleton class="h-3 w-16" />
          </div>
        </div>
        <Skeleton class="h-16 w-full" />
      </div>
    </div>
  </div>
</template>
