<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useWishlist } from '@/composables/useWishlist';

interface Props {
  eventId: number;
  variant?: 'button' | 'icon' | 'text';
  size?: 'sm' | 'md' | 'lg';
  showText?: boolean;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'button',
  size: 'md',
  showText: true,
  disabled: false,
});

const emit = defineEmits<{
  wishlistChanged: [inWishlist: boolean];
  error: [message: string];
}>();

const {
  isLoading,
  error,
  toggleWishlist,
  checkWishlistStatus,
  isInWishlist,
  clearError
} = useWishlist();

const isInitialized = ref(false);
const page = usePage();

// Check if user is authenticated
const isAuthenticated = computed(() => {
  return !!(page.props.auth as any)?.user;
});

// Check initial wishlist status
onMounted(async () => {
  // Only check wishlist status if user is authenticated

  console.log('isAuthenticated.value', isAuthenticated.value);
  if (isAuthenticated.value) {
    await checkWishlistStatus(props.eventId);
  }
  isInitialized.value = true;

});

const handleToggle = async () => {
  if (props.disabled || isLoading.value) return;

  // Check if user is authenticated
  if (!isAuthenticated.value) {
    // Redirect to login if not authenticated
    const { router } = await import('@inertiajs/vue3');
    router.visit(route('login'));
    return;
  }

  clearError();
  const result = await toggleWishlist(props.eventId);

  if (result) {
    emit('wishlistChanged', result.inWishlist);
  } else if (error.value) {
    emit('error', error.value);
  }
};

// Computed properties for styling
const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2';

  const variants = {
    button: 'border rounded-full font-semibold',
    icon: 'rounded-full',
    text: 'underline-offset-4 hover:underline'
  };

  const sizes = {
    sm: props.variant === 'button' ? 'px-3 py-1.5 text-xs' : props.variant === 'icon' ? 'p-1.5 text-sm' : 'text-xs',
    md: props.variant === 'button' ? 'px-4 py-2 text-sm' : props.variant === 'icon' ? 'p-2 text-base' : 'text-sm',
    lg: props.variant === 'button' ? 'px-6 py-3 text-base' : props.variant === 'icon' ? 'p-3 text-lg' : 'text-base'
  };

  const colors = isInWishlist(props.eventId)
    ? {
        button: 'border-red-500 bg-red-500 text-white hover:bg-red-600 focus:ring-red-500',
        icon: 'text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 focus:ring-red-500',
        text: 'text-red-500 hover:text-red-600 focus:ring-red-500'
      }
    : {
        button: 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500',
        icon: 'text-gray-400 hover:text-red-500 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500',
        text: 'text-gray-600 dark:text-gray-400 hover:text-red-500 focus:ring-gray-500'
      };

  const disabled = (props.disabled || isLoading.value)
    ? 'opacity-50 cursor-not-allowed'
    : 'cursor-pointer';

  return `${base} ${variants[props.variant]} ${sizes[props.size]} ${colors[props.variant]} ${disabled}`;
});

const iconClasses = computed(() => {
  const sizes = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6'
  };

  return `${sizes[props.size]} ${props.showText && props.variant === 'button' ? 'mr-2' : ''}`;
});

const buttonText = computed(() => {
  if (!props.showText) return '';

  if (isLoading.value) return 'Loading...';

  if (!isAuthenticated.value) return 'Login to Save';

  return isInWishlist(props.eventId) ? 'In Wishlist' : 'Add to Wishlist';
});

// Heart icon SVG paths
const heartIcon = computed(() => {
  return isInWishlist(props.eventId)
    ? 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z' // filled heart
    : 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z'; // outline heart
});

const heartFill = computed(() => {
  return isInWishlist(props.eventId) ? 'currentColor' : 'none';
});
</script>

<template>
  <button
    :class="buttonClasses"
    :disabled="disabled || isLoading"
    @click="handleToggle"
    :title="!isAuthenticated ? 'Login to save to wishlist' : (isInWishlist(eventId) ? 'Remove from wishlist' : 'Add to wishlist')"
  >
    <!-- Loading spinner -->
    <svg
      v-if="isLoading"
      :class="iconClasses"
      class="animate-spin"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      />
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      />
    </svg>

    <!-- Heart icon -->
    <svg
      v-else
      :class="iconClasses"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      stroke-width="2"
    >
      <path
        :fill="heartFill"
        stroke-linecap="round"
        stroke-linejoin="round"
        :d="heartIcon"
      />
    </svg>

    <!-- Text -->
    <span v-if="showText && variant === 'button'">
      {{ buttonText }}
    </span>
  </button>
</template>
