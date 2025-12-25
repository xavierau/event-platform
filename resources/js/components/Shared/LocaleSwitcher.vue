<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDownIcon, GlobeAltIcon } from '@heroicons/vue/24/outline';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

interface Props {
  variant?: 'dropdown' | 'button' | 'minimal';
  showIcon?: boolean;
  showLabel?: boolean;
}

withDefaults(defineProps<Props>(), {
  variant: 'dropdown',
  showIcon: true,
  showLabel: true,
});

const page = usePage();
const isOpen = ref(false);
const { locale: i18nLocale } = useI18n();

// Get available locales from Laravel config
const availableLocales = computed(() => {
  const locales = (page.props.availableLocales as Record<string, string>) || {
    'en': 'Eng',
    'zh-TW': '繁',
    'zh-CN': '简'
  };
  return locales;
});

// Get current locale from Laravel
const currentLocale = computed(() => {
  return (page.props.locale as string) || 'en';
});

const currentLocaleName = computed(() => {
  return availableLocales.value[currentLocale.value] || 'Eng';
});

const switchLocale = async (locale: string) => {
  if (locale === currentLocale.value) {
    isOpen.value = false;
    return;
  }

  try {
    // Fetch translations for the new locale from the API
    const response = await fetch(`/api/translations?locale=${locale}`);
    const data = await response.json();

    // Get the global i18n instance and set the new locale messages
    const i18n = (window as any).__VUE_I18N__;
    if (i18n && data.translations) {
      i18n.global.setLocaleMessage(locale, data.translations);
    }

    // Now update the locale value
    i18nLocale.value = locale;

    // Update backend session via Inertia
    router.post('/locale/switch', { locale }, {
      preserveScroll: true,
      onSuccess: () => {
        isOpen.value = false;
      }
    });
  } catch (error) {
    console.error('Failed to load translations:', error);
    // Fall back to just switching locale without new translations
    i18nLocale.value = locale;
    router.post('/locale/switch', { locale }, {
      preserveScroll: true,
      onSuccess: () => {
        isOpen.value = false;
      }
    });
  }
};

// Close dropdown when clicking outside
const closeDropdown = (event: Event) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.locale-switcher')) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', closeDropdown);
});
</script>

<template>
  <div class="locale-switcher relative">
    <!-- Dropdown Variant -->
    <div v-if="variant === 'dropdown'" class="relative">
      <button
        @click="isOpen = !isOpen"
        class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-md transition-colors duration-200"
        :class="{ 'bg-gray-100 dark:bg-gray-800': isOpen }"
      >
        <GlobeAltIcon v-if="showIcon" class="h-4 w-4" />
        <span v-if="showLabel">{{ currentLocaleName }}</span>
        <ChevronDownIcon
          class="h-4 w-4 transition-transform duration-200"
          :class="{ 'rotate-180': isOpen }"
        />
      </button>

      <!-- Dropdown Menu -->
      <div
        v-show="isOpen"
        class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50"
      >
        <div class="py-1">
          <button
            v-for="(name, locale) in availableLocales"
            :key="locale"
            @click="switchLocale(locale)"
            class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
            :class="{
              'bg-gray-100 dark:bg-gray-700 font-medium': locale === currentLocale,
              'text-gray-900 dark:text-gray-100': locale === currentLocale
            }"
          >
            <span class="flex-1 text-left">{{ name }}</span>
            <span v-if="locale === currentLocale" class="text-blue-600 dark:text-blue-400">✓</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Button Variant -->
    <div v-else-if="variant === 'button'" class="flex space-x-1">
      <button
        v-for="(name, locale) in availableLocales"
        :key="locale"
        @click="switchLocale(locale)"
        class="px-3 py-1 text-sm font-medium rounded-md transition-colors duration-200"
        :class="locale === currentLocale
          ? 'bg-blue-600 text-white'
          : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'"
      >
        {{ name }}
      </button>
    </div>

    <!-- Minimal Variant -->
    <div v-else-if="variant === 'minimal'" class="flex items-center space-x-1">
      <GlobeAltIcon v-if="showIcon" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
      <select
        :value="currentLocale"
        @change="switchLocale(($event.target as HTMLSelectElement).value)"
        class="text-sm bg-transparent border-none focus:ring-0 text-gray-700 dark:text-gray-300 cursor-pointer"
      >
        <option
          v-for="(name, locale) in availableLocales"
          :key="locale"
          :value="locale"
        >
          {{ name }}
        </option>
      </select>
    </div>
  </div>
</template>

<style scoped>
/* Custom styles for the select in minimal variant */
.locale-switcher select {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2rem;
}
</style>