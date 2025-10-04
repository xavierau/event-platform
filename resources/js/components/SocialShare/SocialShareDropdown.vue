<template>
  <div class="relative z-[10000]" ref="dropdownRef">
    <!-- Trigger Button -->
    <button
      @click="toggleDropdown"
      :disabled="isLoading"
      class="inline-flex items-center justify-center px-3 py-1.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
      aria-label="Share this event"
      :aria-expanded="isOpen"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
      </svg>
<!--      <span class="text-sm font-medium">Share</span>-->
<!--      <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">-->
<!--        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>-->
<!--      </svg>-->
    </button>

    <!-- Dropdown Menu -->
    <div
      v-show="isOpen"
      :class="dropdownClasses"
      :style="dropdownStyle"
      role="menu"
      aria-orientation="vertical"
    >
      <button
        v-for="platform in platforms"
        :key="platform.key"
        @click="handleShare(platform.key)"
        :disabled="isLoading"
        :class="[
          'w-full flex items-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors h-9',
          props.uiConfig.show_labels !== false ? 'gap-3 px-4' : 'justify-center px-3'
        ]"
        role="menuitem"
      >
        <img :src="getPlatformLogoUrl(platform.key)" :alt="`${platform.name} logo`" class="w-4 h-4 flex-shrink-0 dark:brightness-0 dark:invert" />
        <span v-if="props.uiConfig.show_labels !== false" class="flex-1 text-left">{{ platform.name }}</span>
        <span v-if="props.uiConfig.show_count !== false && shareCounts[platform.key] && shareCounts[platform.key] > 0" class="text-xs text-gray-500 dark:text-gray-400">
          {{ shareCounts[platform.key] }}
        </span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';

interface Platform {
  key: string;
  name: string;
  icon: string;
  color: string;
}

interface Props {
  platforms: Platform[];
  shareUrls: Record<string, string>;
  shareCounts: Record<string, number>;
  uiConfig: {
    show_count?: boolean;
    show_labels?: boolean;
    button_style?: string;
    max_buttons_before_dropdown?: number;
    button_size?: string;
    border_radius?: string;
    spacing?: string;
  };
  isLoading: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  share: [platform: string];
}>();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement>();
const showAbove = ref(false);

// Detect mobile Safari
const isMobileSafari = () => {
  const ua = navigator.userAgent;
  const iOS = /iPad|iPhone|iPod/.test(ua);
  const webkit = /WebKit/.test(ua);
  return iOS && webkit && !/CriOS|FxiOS|OPiOS|mercury/.test(ua);
};

const dropdownClasses = computed(() => [
  'fixed bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5',
  'z-[99999]', // Much higher z-index to appear above navigation and other elements
  'max-h-[80vh] overflow-y-auto', // Prevent dropdown from being taller than viewport
  // Narrower width for icon-only mode
  props.uiConfig.show_labels !== false ? 'w-48 py-1' : 'w-16 py-1',
]);

const dropdownStyle = ref({});

const checkDropdownPosition = async () => {
  if (!dropdownRef.value || !isOpen.value) return;

  await nextTick();

  const button = dropdownRef.value.querySelector('button');
  const dropdown = dropdownRef.value.querySelector('[role="menu"]') as HTMLElement;

  if (!button || !dropdown) return;

  const buttonRect = button.getBoundingClientRect();
  const viewportHeight = window.innerHeight;

  // Calculate actual dropdown height with fixed item height
  const platformCount = props.platforms.length;
  const rowHeight = 36; // Fixed height (h-9 = 36px)
  const padding = 8; // py-1 = 4px top + 4px bottom
  const actualDropdownHeight = (platformCount * rowHeight) + padding;

  // Calculate position coordinates - align dropdown with button
  const dropdownWidth = props.uiConfig.show_labels !== false ? 192 : 64; // w-48 = 192px, w-16 = 64px

  // Center the dropdown relative to the button
  const buttonCenterX = buttonRect.left + (buttonRect.width / 2);
  const dropdownLeft = buttonCenterX - (dropdownWidth / 2);

  // Ensure dropdown stays within viewport bounds
  const leftPosition = Math.max(8, Math.min(dropdownLeft, window.innerWidth - dropdownWidth - 8));

  // Mobile Safari specific positioning
  if (isMobileSafari()) {
    // Get fixed footer height (approx 80px based on EventDetail.vue footer)
    const fixedFooterHeight = 80;
    const safeAreaBottom = 20; // Account for iOS home indicator
    const gap = 8; // Gap between dropdown and button

    // Calculate available space above the button (excluding fixed footer)
    const availableSpaceAbove = buttonRect.top - gap;

    // Position dropdown to show above button with gap
    // Use bottom positioning relative to button for mobile Safari stability
    const bottomPosition = viewportHeight - buttonRect.top + gap;

    // If dropdown doesn't fit above, position it to fit in available space
    let finalTopPosition: number;
    if (actualDropdownHeight <= availableSpaceAbove) {
      // Dropdown fits above button - position with gap
      finalTopPosition = buttonRect.top - actualDropdownHeight - gap;
    } else {
      // Dropdown doesn't fit - position at top with safe margin
      finalTopPosition = 8;
    }

    dropdownStyle.value = {
      left: `${leftPosition}px`,
      top: `${finalTopPosition}px`,
      maxHeight: `${Math.min(actualDropdownHeight, availableSpaceAbove - 16)}px`, // Ensure it doesn't overflow
    };
  } else {
    // Desktop/non-Safari mobile positioning (original logic)
    const fixedBottomOffset = -8; // Position dropdown above the button with gap
    const dropdownBottom = buttonRect.top + fixedBottomOffset;

    // Calculate top position to grow upward from fixed bottom
    const topPosition = dropdownBottom - actualDropdownHeight;

    // Ensure dropdown doesn't go above viewport
    const finalTopPosition = Math.max(8, topPosition);

    dropdownStyle.value = {
      left: `${leftPosition}px`,
      top: `${finalTopPosition}px`,
    };
  }
};

const toggleDropdown = async () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    await checkDropdownPosition();
  }
};

const closeDropdown = () => {
  isOpen.value = false;
  dropdownStyle.value = {};
};

const handleShare = (platform: string) => {
  emit('share', platform);
  closeDropdown();
};

const handleClickOutside = (event: Event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    closeDropdown();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('resize', checkDropdownPosition);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('resize', checkDropdownPosition);
});

const getPlatformLogoUrl = (platform: string) => {
  const logoUrls = {
    facebook: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/facebook.svg',
    x: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/x.svg',
    twitter: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/x.svg',
    linkedin: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/linkedin.svg',
    whatsapp: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/whatsapp.svg',
    telegram: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/telegram.svg',
    wechat: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/wechat.svg',
    weibo: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/sinaweibo.svg',
    email: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/maildotru.svg',
    xiaohongshu: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/xiaohongshu.svg',
    copy_url: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/clipboard.svg',
    instagram: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/instagram.svg',
    threads: 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/threads.svg',
  };

  return logoUrls[platform as keyof typeof logoUrls] || 'https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/share.svg';
};
</script>
