<template>
  <div class="social-share-wrapper flex items-center">
    <!-- Logo Icon -->
<!--    <div class="flex items-center">-->
<!--      <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">-->
<!--        <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"></path>-->
<!--      </svg>-->
<!--    </div>-->

    <!-- Individual buttons for 1-2 platforms -->
    <SocialShareButtons
      v-if="displayMode === 'buttons'"
      :platforms="platforms"
      :share-urls="shareUrls"
      :share-counts="shareCounts"
      :ui-config="uiConfig"
      :is-loading="isLoading"
      @share="handleShare"
    />

    <!-- Dropdown for 3+ platforms -->
    <SocialShareDropdown
      v-else-if="displayMode === 'dropdown'"
      :platforms="platforms"
      :share-urls="shareUrls"
      :share-counts="shareCounts"
      :ui-config="uiConfig"
      :is-loading="isLoading"
      @share="handleShare"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useSocialShare } from '@/composables/useSocialShare';
import SocialShareButtons from './SocialShareButtons.vue';
import SocialShareDropdown from './SocialShareDropdown.vue';

interface Props {
  shareableType: string;
  shareableId: number;
  forceMode?: 'buttons' | 'dropdown' | null;
}

const props = defineProps<Props>();

const {
  platforms,
  shareUrls,
  shareCounts,
  uiConfig,
  displayMode,
  isLoading,
  loadShareData,
  shareToPlatform,
} = useSocialShare();

const handleShare = async (platform: string) => {
  await shareToPlatform(platform, props.shareableType, props.shareableId);
};

onMounted(() => {
  loadShareData(props.shareableType, props.shareableId);
});
</script>
