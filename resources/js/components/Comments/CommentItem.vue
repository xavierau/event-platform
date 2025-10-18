<script setup lang="ts">
import { ref, computed } from 'vue';
import { format, parseISO } from 'date-fns';
import { usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import CommentForm from './CommentForm.vue';
import { showToast } from '@/composables/useToast';
import type { Comment, CommentVote } from '@/types/comment';
import type { User } from '@/types';

interface Props {
  comment: Comment;
  commentableType: string;
  commentableId: number;
  canReply?: boolean;
  canVote?: boolean;
  canModerate?: boolean;
  showReplies?: boolean;
  level?: number;
}

const props = withDefaults(defineProps<Props>(), {
  canReply: true,
  canVote: true,
  canModerate: false,
  showReplies: true,
  level: 0,
});

const emit = defineEmits<{
  replyAdded: [comment: Comment];
  commentVoted: [commentId: number, voteType: 'up' | 'down', newCounts: { upCount: number; downCount: number }];
  commentModerated: [commentId: number, status: string];
}>();

const page = usePage<{ auth: { user: User | null } }>();
const showReplyForm = ref(false);
const showReplies = ref(false);
const isVoting = ref(false);
const isModerating = ref(false);

const formattedDate = computed(() => {
  if (props.comment.created_at) {
    return format(parseISO(props.comment.created_at), 'PPP p');
  }
  return '';
});

const isOwnComment = computed(() => {
  return page.props.auth.user?.id === props.comment.user_id;
});

const canShowActions = computed(() => {
  return props.canReply || (props.canVote && props.comment.votes_enabled) || props.canModerate;
});

const statusBadgeVariant = computed(() => {
  switch (props.comment.status) {
    case 'approved': return 'default';
    case 'pending': return 'secondary';
    case 'rejected': return 'destructive';
    case 'flagged': return 'warning';
    default: return 'secondary';
  }
});

const voteComment = async (voteType: 'up' | 'down') => {
  if (isVoting.value || !page.props.auth.user) return;
  
  isVoting.value = true;
  
  try {
    const response = await fetch(`/api/comments/${props.comment.id}/vote`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ vote_type: voteType }),
    });
    
    const result = await response.json();
    
    if (!response.ok) {
      throw new Error(result.message || 'Failed to vote on comment');
    }
    
    // Update local vote counts
    emit('commentVoted', props.comment.id, voteType, {
      upCount: result.votes_up_count,
      downCount: result.votes_down_count,
    });
    
    // Update user vote state
    props.comment.user_vote = result.user_vote;
    props.comment.votes_up_count = result.votes_up_count;
    props.comment.votes_down_count = result.votes_down_count;
    
    showToast(result.message || 'Vote recorded successfully', 'success');
  } catch (error) {
    console.error('Error voting on comment:', error);
    showToast('Failed to vote on comment. Please try again.', 'error');
  } finally {
    isVoting.value = false;
  }
};

const moderateComment = async (status: 'approved' | 'rejected' | 'flagged') => {
  if (isModerating.value) return;
  
  isModerating.value = true;
  
  try {
    const response = await fetch(`/api/comments/${props.comment.id}/moderate`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ status }),
    });
    
    const result = await response.json();
    
    if (!response.ok) {
      throw new Error(result.message || 'Failed to moderate comment');
    }
    
    // Update local status
    props.comment.status = status;
    emit('commentModerated', props.comment.id, status);
    
    showToast(result.message || `Comment ${status} successfully`, 'success');
  } catch (error) {
    console.error('Error moderating comment:', error);
    showToast('Failed to moderate comment. Please try again.', 'error');
  } finally {
    isModerating.value = false;
  }
};

const onReplyAdded = (newReply: Comment) => {
  if (!props.comment.replies) {
    props.comment.replies = [];
  }
  props.comment.replies.unshift(newReply);
  showReplyForm.value = false;
  emit('replyAdded', newReply);
};

const toggleReplyForm = () => {
  // Check authentication before showing reply form
  if (!page.props.auth.user) {
    // Redirect to login or show auth modal
    window.location.href = '/login';
    return;
  }
  showReplyForm.value = !showReplyForm.value;
};

const toggleReplies = () => {
  showReplies.value = !showReplies.value;
};
</script>

<template>
  <div 
    class="border-l-2 border-transparent"
    :class="{
      'ml-4': level > 0,
      'border-l-blue-200 dark:border-l-blue-800': level > 0,
    }"
  >
    <div class="p-4 mb-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
      <!-- Comment Header -->
      <div class="flex items-start justify-between mb-3">
        <div class="flex items-center space-x-3">
          <img
            :src="comment.user?.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(comment.user?.name || 'User')}&background=random`"
            alt="User Avatar"
            class="w-8 h-8 rounded-full flex-shrink-0"
          >
          <div>
            <div class="flex items-center space-x-2">
              <p class="font-semibold text-gray-900 dark:text-white">
                {{ comment.user?.name || 'Anonymous' }}
              </p>
              <Badge v-if="comment.status !== 'approved'" :variant="statusBadgeVariant">
                {{ comment.status }}
              </Badge>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ formattedDate }}
            </p>
          </div>
        </div>
        
        <!-- Moderation Actions -->
        <div v-if="canModerate && comment.status !== 'approved'" class="flex items-center space-x-2">
          <Button
            size="sm"
            variant="outline"
            @click="moderateComment('approved')"
            :disabled="isModerating"
          >
            Approve
          </Button>
          <Button
            size="sm"
            variant="outline"
            @click="moderateComment('rejected')"
            :disabled="isModerating"
          >
            Reject
          </Button>
        </div>
      </div>

      <!-- Comment Content -->
      <div class="mb-3">
        <div 
          v-if="comment.content_type === 'rich'"
          class="prose prose-sm max-w-none dark:prose-invert text-gray-700 dark:text-gray-300"
          v-html="comment.content"
        />
        <p 
          v-else
          class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap"
        >
          {{ comment.content }}
        </p>
      </div>

      <!-- Comment Actions -->
      <div v-if="canShowActions" class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <!-- Voting -->
          <div v-if="canVote && comment.votes_enabled" class="flex items-center space-x-2">
            <Button
              size="sm"
              variant="ghost"
              @click="voteComment('up')"
              :disabled="isVoting || !page.props.auth.user"
              :class="{
                'text-green-600 dark:text-green-400': comment.user_vote?.vote_type === 'up',
                'hover:text-green-600 dark:hover:text-green-400': comment.user_vote?.vote_type !== 'up'
              }"
            >
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
              </svg>
              {{ comment.votes_up_count }}
            </Button>
            
            <Button
              size="sm"
              variant="ghost"
              @click="voteComment('down')"
              :disabled="isVoting || !page.props.auth.user"
              :class="{
                'text-red-600 dark:text-red-400': comment.user_vote?.vote_type === 'down',
                'hover:text-red-600 dark:hover:text-red-400': comment.user_vote?.vote_type !== 'down'
              }"
            >
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
              {{ comment.votes_down_count }}
            </Button>
          </div>

          <!-- Reply Button -->
          <Button
            v-if="canReply && level < 3"
            size="sm"
            variant="ghost"
            @click="toggleReplyForm"
            :disabled="false"
          >
            Reply
          </Button>
        </div>
        
        <!-- Reply Count and Toggle -->
        <div v-if="comment.replies && comment.replies.length > 0">
          <Button
            size="sm"
            variant="ghost"
            @click="toggleReplies"
            class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
          >
            {{ showReplies ? 'Hide' : 'Show' }} {{ comment.replies.length }} {{ comment.replies.length === 1 ? 'reply' : 'replies' }}
          </Button>
        </div>
      </div>

      <!-- Reply Form -->
      <div v-if="showReplyForm" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <CommentForm
          :commentable-type="commentableType"
          :commentable-id="commentableId"
          :parent-id="comment.id"
          placeholder="Write a reply..."
          @comment-added="onReplyAdded"
          @cancel="showReplyForm = false"
        />
      </div>
    </div>

    <!-- Replies -->
    <div v-if="showReplies && comment.replies && comment.replies.length > 0" class="space-y-2">
      <CommentItem
        v-for="reply in comment.replies"
        :key="reply.id"
        :comment="reply"
        :commentable-type="commentableType"
        :commentable-id="commentableId"
        :can-reply="canReply"
        :can-vote="canVote"
        :can-moderate="canModerate"
        :show-replies="showReplies"
        :level="level + 1"
        @reply-added="emit('replyAdded', $event)"
        @comment-voted="emit('commentVoted', $event)"
        @comment-moderated="emit('commentModerated', $event)"
      />
    </div>
  </div>
</template>
