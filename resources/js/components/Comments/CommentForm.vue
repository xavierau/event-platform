<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { showToast } from '@/composables/useToast';
import type { Comment, CommentFormData } from '@/types/comment';
import type { User } from '@/types';

const { t } = useI18n();

interface Props {
  commentableType: string;
  commentableId: number;
  parentId?: number | null;
  placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
  parentId: null,
  placeholder: '',
});

const emit = defineEmits<{
  commentAdded: [comment: Comment];
  cancel?: [];
}>();

const page = usePage<{ auth: { user: User | null } }>();
const content = ref('');
const isSubmitting = ref(false);

const canSubmit = computed(() => {
  return content.value.trim().length > 0 && !isSubmitting.value && !!page.props.auth.user;
});

const submitComment = async () => {
  if (!canSubmit.value) return;
  
  // Additional authentication check
  if (!page.props.auth.user) {
    window.location.href = '/login';
    return;
  }

  isSubmitting.value = true;

  try {
    const formData: CommentFormData = {
      commentable_type: props.commentableType,
      commentable_id: props.commentableId,
      content: content.value.trim(),
      content_type: 'plain',
      parent_id: props.parentId,
    };

    const response = await fetch('/api/comments', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify(formData),
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || 'Failed to submit comment');
    }

    // Reset form
    content.value = '';
    
    // Emit success
    emit('commentAdded', result.comment);
    
    showToast(result.message || 'Comment submitted successfully!', 'success');
  } catch (error) {
    console.error('Error submitting comment:', error);
    showToast('Failed to submit comment. Please try again.', 'error');
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="space-y-4">
    <div v-if="!parentId" class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
        {{ t('comments.leave_comment') }}
      </h3>
    </div>

    <form @submit.prevent="submitComment" class="space-y-4">
      <!-- Plain Text Editor -->
      <Textarea
        v-model="content"
        :placeholder="placeholder || t('comments.comment_placeholder')"
        class="w-full min-h-24 resize-none"
        :disabled="isSubmitting"
      />

      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          {{ t('comments.characters', { count: content.length }) }}
          <span v-if="!page.props.auth.user" class="ml-2 text-orange-600 dark:text-orange-400">
            {{ t('comments.please_login') }}
          </span>
        </div>

        <div class="flex items-center space-x-2">
          <Button
            v-if="parentId"
            type="button"
            variant="outline"
            @click="emit('cancel')"
            :disabled="isSubmitting"
          >
            {{ t('comments.cancel') }}
          </Button>

          <Button
            type="submit"
            :disabled="!canSubmit"
          >
            <span v-if="isSubmitting">{{ t('comments.submitting') }}</span>
            <span v-else-if="!page.props.auth.user">{{ parentId ? t('comments.login_to_reply') : t('comments.login_to_comment') }}</span>
            <span v-else>{{ parentId ? t('comments.reply') : t('comments.submit_comment') }}</span>
          </Button>
        </div>
      </div>
    </form>
  </div>
</template>
