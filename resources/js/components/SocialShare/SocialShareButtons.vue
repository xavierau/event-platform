<template>
  <div class="flex space-x-1 sm:space-x-2">
    <button
      v-for="platform in platforms"
      :key="platform.key"
      @click="$emit('share', platform.key)"
      :disabled="isLoading"
      :class="[
        'inline-flex items-center rounded-full transition-all text-xs font-semibold',
        'hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2',
        'dark:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed',
        getPlatformClasses(platform.key),
        // Adjust padding and gap based on whether labels are shown
        props.uiConfig.show_labels !== false ? 'gap-2 px-3 py-1.5' : 'p-1.5 justify-center'
      ]"
      :aria-label="`Share on ${platform.name}`"
    >
      <img :src="getPlatformLogoUrl(platform.key)" :alt="`${platform.name} logo`" class="w-4 h-4 flex-shrink-0 dark:brightness-0 dark:invert" />
      <span v-if="props.uiConfig.show_labels !== false">{{ platform.name }}</span>
      <span v-if="props.uiConfig.show_count !== false && shareCounts[platform.key] && shareCounts[platform.key] > 0" class="text-xs opacity-90">
        ({{ shareCounts[platform.key] }})
      </span>
    </button>
  </div>
</template>

<script setup lang="ts">
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
defineEmits<{
  share: [platform: string];
}>();

const getPlatformClasses = (platform: string) => {
  const classes = {
    facebook: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
    x: 'bg-black text-white hover:bg-gray-800 focus:ring-gray-500',
    twitter: 'bg-black text-white hover:bg-gray-800 focus:ring-gray-500',
    linkedin: 'bg-blue-500 text-white hover:bg-blue-600 focus:ring-blue-400',
    whatsapp: 'bg-green-500 text-white hover:bg-green-600 focus:ring-green-400',
    telegram: 'bg-blue-400 text-white hover:bg-blue-500 focus:ring-blue-300',
    wechat: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
    weibo: 'bg-red-500 text-white hover:bg-red-600 focus:ring-red-400',
    email: 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
    xiaohongshu: 'bg-red-400 text-white hover:bg-red-500 focus:ring-red-300',
    copy_url: 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
    instagram: 'bg-gradient-to-r from-purple-500 to-pink-500 text-white hover:from-purple-600 hover:to-pink-600 focus:ring-purple-400',
    threads: 'bg-black text-white hover:bg-gray-800 focus:ring-gray-500',
  };

  return classes[platform as keyof typeof classes] || 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500';
};

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