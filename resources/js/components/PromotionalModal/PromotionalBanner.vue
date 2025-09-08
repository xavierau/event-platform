<template>
  <div
    v-if="show"
    class="relative bg-gradient-to-r from-indigo-600 to-purple-600 text-white"
    :style="backgroundStyle"
  >
    <!-- Banner content -->
    <div class="max-w-7xl mx-auto px-4 py-3 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between flex-wrap">
        <div class="w-0 flex-1 flex items-center">
          <!-- Banner image (if provided) -->
          <div v-if="modal.banner_image_url" class="flex-shrink-0 mr-4">
            <img
              :src="modal.banner_image_url"
              :alt="modal.title"
              class="h-12 w-12 object-cover rounded-lg"
            />
          </div>

          <div class="ml-3 font-medium">
            <!-- Title -->
            <h3 
              class="text-white font-semibold"
              v-html="modal.title"
            />
            <!-- Content -->
            <div 
              v-if="modal.content"
              class="text-indigo-100 text-sm mt-1"
              v-html="modal.content"
            />
          </div>
        </div>

        <div class="flex items-center space-x-4">
          <!-- Action button -->
          <a
            v-if="modal.button_text && modal.button_url"
            :href="modal.button_url"
            @click="handleClick"
            class="inline-flex items-center px-4 py-2 border border-white/20 text-sm font-medium rounded-md text-white bg-white/10 hover:bg-white/20 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
          >
            {{ modal.button_text }}
          </a>

          <!-- Close button (if dismissible) -->
          <button
            v-if="modal.is_dismissible"
            @click="handleDismiss"
            class="p-2 text-indigo-100 hover:text-white transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
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
      backgroundImage: `linear-gradient(rgba(79, 70, 229, 0.8), rgba(147, 51, 234, 0.8)), url(${props.modal.background_image_url})`,
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
</script>

<style scoped>
/* Banner animation */
.banner-enter-active,
.banner-leave-active {
  transition: all 0.5s ease;
}

.banner-enter-from {
  transform: translateY(-100%);
  opacity: 0;
}

.banner-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .flex-wrap > div {
    width: 100%;
    justify-content: space-between;
  }
  
  .flex-wrap > div:first-child {
    margin-bottom: 0.5rem;
  }
}
</style>