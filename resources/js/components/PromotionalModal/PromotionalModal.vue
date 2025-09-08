<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      @click.self="handleDismiss"
    >
      <div
        class="relative max-w-lg w-full bg-white rounded-lg shadow-xl transform transition-all"
        :style="backgroundStyle"
      >
        <!-- Close button (if dismissible) -->
        <button
          v-if="modal.is_dismissible"
          @click="handleDismiss"
          class="absolute top-4 right-4 z-10 p-2 text-gray-400 hover:text-gray-600 transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- Modal content -->
        <div class="p-6">
          <!-- Banner image -->
          <div v-if="modal.banner_image_url" class="mb-4 -mt-6 -mx-6">
            <img
              :src="modal.banner_image_url"
              :alt="modal.title"
              class="w-full h-48 object-cover rounded-t-lg"
            />
          </div>

          <!-- Title -->
          <h2 
            class="text-xl font-bold text-gray-900 mb-4"
            v-html="modal.title"
          />

          <!-- Content -->
          <div 
            class="prose prose-sm max-w-none mb-6 text-gray-600"
            v-html="modal.content"
          />

          <!-- Action button -->
          <div v-if="modal.button_text && modal.button_url" class="flex justify-center">
            <a
              :href="modal.button_url"
              @click="handleClick"
              class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-3"
            >
              {{ modal.button_text }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';

interface PromotionalModalData {
  id: number;
  title: string;
  content: string;
  type: 'modal' | 'banner';
  button_text?: string;
  button_url?: string;
  is_dismissible: boolean;
  banner_image_url?: string;
  background_image_url?: string;
  display_conditions?: Record<string, any>;
}

interface Props {
  modal: PromotionalModalData;
  show: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  close: [];
  impression: [];
  click: [];
  dismiss: [];
}>();

// Computed background style
const backgroundStyle = computed(() => {
  if (props.modal.background_image_url) {
    return {
      backgroundImage: `url(${props.modal.background_image_url})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    };
  }
  return {};
});

// Handle impression tracking on mount
onMounted(() => {
  if (props.show) {
    emit('impression');
  }
});

// Handle button click
const handleClick = () => {
  emit('click');
  // Let the link navigate naturally
};

// Handle dismiss
const handleDismiss = () => {
  emit('dismiss');
  emit('close');
};

// Handle escape key
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && props.modal.is_dismissible) {
    handleDismiss();
  }
};

// Add/remove event listeners
if (typeof window !== 'undefined') {
  window.addEventListener('keydown', handleKeydown);
}

// Cleanup on unmount
import { onUnmounted } from 'vue';
onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeydown);
  }
});
</script>

<style scoped>
/* Ensure modal is above other content */
.modal-overlay {
  backdrop-filter: blur(4px);
}

/* Animation classes */
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: scale(0.9);
}
</style>