<template>
  <div>
    <!-- Modals -->
    <PromotionalModal
      v-for="modal in modals"
      :key="`modal-${modal.id}`"
      :modal="modal"
      :show="modal.type === 'modal' && visibleModals.has(modal.id)"
      @close="hideModal(modal.id)"
      @impression="recordImpression(modal, 'impression')"
      @click="recordImpression(modal, 'click')"
      @dismiss="recordImpression(modal, 'dismiss')"
    />

    <!-- Banners -->
    <PromotionalBanner
      v-for="banner in banners"
      :key="`banner-${banner.id}`"
      :modal="banner"
      :show="banner.type === 'banner' && visibleModals.has(banner.id)"
      @close="hideModal(banner.id)"
      @impression="recordImpression(banner, 'impression')"
      @click="recordImpression(banner, 'click')"
      @dismiss="recordImpression(banner, 'dismiss')"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import PromotionalModal from './PromotionalModal.vue';
import PromotionalBanner from './PromotionalBanner.vue';

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
  page: string;
  limit?: number;
  autoShow?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  limit: 3,
  autoShow: true,
});

const allPromotions = ref<PromotionalModalData[]>([]);
const visibleModals = ref<Set<number>>(new Set());
const recordedImpressions = ref<Set<string>>(new Set());
const isLoading = ref(false);

// Computed properties to separate modals and banners
const modals = computed(() => 
  allPromotions.value.filter(p => p.type === 'modal')
);

const banners = computed(() => 
  allPromotions.value.filter(p => p.type === 'banner')
);

// Fetch promotional content for current page
const fetchPromotions = async (type?: 'modal' | 'banner') => {
  if (isLoading.value) return;
  
  isLoading.value = true;
  
  try {
    const params = new URLSearchParams({
      page: props.page,
      limit: props.limit.toString(),
    });
    
    if (type) {
      params.append('type', type);
    }

    const response = await fetch(`/api/promotional-modals?${params}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (response.ok) {
      const data = await response.json();
      
      if (type) {
        // Replace promotions of this type
        allPromotions.value = allPromotions.value.filter(p => p.type !== type);
        allPromotions.value.push(...data.data);
      } else {
        allPromotions.value = data.data;
      }

      // Auto-show if enabled
      if (props.autoShow) {
        showPromotions();
      }
    }
  } catch (error) {
    console.error('Failed to fetch promotional content:', error);
  } finally {
    isLoading.value = false;
  }
};

// Show promotions based on priority
const showPromotions = () => {
  // Show banners immediately
  banners.value.forEach(banner => {
    visibleModals.value.add(banner.id);
  });

  // Show modals with a slight delay, one at a time
  const modalList = [...modals.value];
  modalList.forEach((modal, index) => {
    setTimeout(() => {
      visibleModals.value.add(modal.id);
    }, index * 1000); // 1 second delay between modals
  });
};

// Hide a specific modal/banner
const hideModal = (id: number) => {
  visibleModals.value.delete(id);
};

// Record impression/click/dismiss
const recordImpression = async (promotion: PromotionalModalData, action: string) => {
  const impressionKey = `${promotion.id}-${action}`;
  
  // Prevent duplicate recordings for the same action
  if (action === 'impression' && recordedImpressions.value.has(impressionKey)) {
    return;
  }

  try {
    const response = await fetch(`/api/promotional-modals/${promotion.id}/impression`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        action,
        page_url: window.location.href,
        metadata: {
          page: props.page,
          timestamp: Date.now(),
        },
      }),
    });

    if (response.ok) {
      recordedImpressions.value.add(impressionKey);
      
      // If it's a dismiss, also hide the modal
      if (action === 'dismiss') {
        hideModal(promotion.id);
      }
    }
  } catch (error) {
    console.error(`Failed to record ${action}:`, error);
  }
};

// Batch record impressions for performance
const batchRecordImpressions = async (impressions: Array<{
  modal_id: number;
  action: string;
  page_url?: string;
  metadata?: any;
}>) => {
  if (impressions.length === 0) return;

  try {
    await fetch('/api/promotional-modals/batch-impressions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        impressions,
      }),
    });
  } catch (error) {
    console.error('Failed to batch record impressions:', error);
  }
};

// Public methods for external control
const showModal = (id: number) => {
  visibleModals.value.add(id);
};

const hideAllModals = () => {
  visibleModals.value.clear();
};

const refreshPromotions = () => {
  fetchPromotions();
};

// Expose public methods
defineExpose({
  showModal,
  hideModal,
  hideAllModals,
  refreshPromotions,
  fetchPromotions,
});

// Lifecycle hooks
onMounted(() => {
  fetchPromotions();
});

// Watch for page changes (if using with router)
watch(() => props.page, (newPage, oldPage) => {
  if (newPage !== oldPage) {
    hideAllModals();
    fetchPromotions();
  }
});

// Handle page visibility changes to pause/resume
const handleVisibilityChange = () => {
  if (document.hidden) {
    // Page is hidden, could pause timers
  } else {
    // Page is visible, could resume
  }
};

if (typeof document !== 'undefined') {
  document.addEventListener('visibilitychange', handleVisibilityChange);
}
</script>

<style scoped>
/* Ensure proper z-index stacking */
.promotional-display {
  position: relative;
  z-index: 1000;
}
</style>