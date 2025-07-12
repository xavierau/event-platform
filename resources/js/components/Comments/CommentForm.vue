<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/components/ui/button/Button.vue';
import { Textarea } from '@/components/ui/textarea';
import { showToast } from '@/composables/useToast';

const props = defineProps({
  eventId: {
    type: [String, Number],
    required: true,
  },
});

const isSubmitting = ref(false);

const form = useForm({
  content: '',
});

const submitComment = () => {
  if (form.content.trim() === '') {
    return;
  }

  isSubmitting.value = true;

  form.post(`/events/${props.eventId}/comments`, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('content');
      showToast('Comment submitted successfully!', 'success');
      setTimeout(() => {
        window.location.reload();
      }, 1200);
    },
    onError: (errors) => {
      showToast('Failed to submit comment.', 'error');
      console.error('Error submitting comment:', errors);
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
};
</script>

<template>
  <div class="mt-6">
    <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">Leave a Comment</h3>
    <form @submit.prevent="submitComment">
      <Textarea
        v-model="form.content"
        placeholder="Share your thoughts..."
        class="w-full"
        :disabled="isSubmitting"
      />
      <div class="mt-2 flex justify-end">
        <PrimaryButton :disabled="isSubmitting || form.content.trim() === ''">
          <span v-if="isSubmitting">Submitting...</span>
          <span v-else>Submit Comment</span>
        </PrimaryButton>
      </div>
    </form>
  </div>
</template>
