<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { X, Mail } from 'lucide-vue-next';
import type { User } from '@/types';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const isDismissed = ref(false);
const isResending = ref(false);

const auth = computed(() => page.props.auth as { user?: User });
const user = computed(() => auth.value?.user);

// Check if user is authenticated but not verified
const shouldShowAlert = computed(() => {
  return !isDismissed.value &&
         user.value &&
         !user.value.email_verified_at;
});

const dismissAlert = () => {
  isDismissed.value = true;
};

const resendVerification = () => {
  if (isResending.value) return;

  isResending.value = true;
  router.post(route('verification.send'), {}, {
    onSuccess: () => {
      // Show success message (handled by toast system)
      isResending.value = false;
    },
    onError: () => {
      isResending.value = false;
    }
  });
};
</script>

<template>
  <div
    v-if="shouldShowAlert"
    class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-800"
  >
    <div class="container mx-auto px-4 py-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <Mail class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
          <div class="flex-1">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
              {{ t('email_verification.unverified_message', 'Please verify your email address to access all features.') }}
              {{ t('email_verification.expiration_info', 'Verification links expire after 60 minutes.') }}
              <Button
                variant="link"
                size="sm"
                class="p-0 h-auto text-yellow-700 dark:text-yellow-300 underline hover:text-yellow-900 dark:hover:text-yellow-100"
                @click="resendVerification"
                :disabled="isResending"
              >
                {{ isResending ? t('email_verification.sending', 'Sending...') : t('email_verification.resend', 'Resend verification email') }}
              </Button>
            </p>
          </div>
        </div>
        <Button
          variant="ghost"
          size="sm"
          class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-200 p-1"
          @click="dismissAlert"
        >
          <X class="h-4 w-4" />
          <span class="sr-only">{{ t('common.dismiss', 'Dismiss') }}</span>
        </Button>
      </div>
    </div>
  </div>
</template>