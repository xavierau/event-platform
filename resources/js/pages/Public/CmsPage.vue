<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import PublicHeader from '@/components/Shared/PublicHeader.vue'
import ChatbotWidget from '@/components/chatbot/ChatbotWidget.vue'

interface CmsPage {
  id: number
  title: Record<string, string>
  slug: string
  content: Record<string, string>
  meta_description?: Record<string, string>
  meta_keywords?: Record<string, string>
  published_at: string
  featured_image_url?: string | null
  featured_image_thumb_url?: string | null
  author?: {
    name: string
  } | null
}

const props = defineProps<{
  page: CmsPage
}>()

const page = usePage()

// Get the current locale from Inertia shared props
const currentLocale = computed(() => (page.props.current_locale as string) || 'en')

const getTranslation = (translations: Record<string, string> | null | undefined, locale: string = 'en'): string => {
  if (!translations) return ''
  if (typeof translations === 'string') return translations
  return translations[locale] || translations['en'] || Object.values(translations)[0] || ''
}

const pageTitle = computed(() => getTranslation(props.page.title, currentLocale.value))
const pageContent = computed(() => getTranslation(props.page.content, currentLocale.value))
const metaDescription = computed(() => getTranslation(props.page.meta_description, currentLocale.value))
</script>

<template>
  <Head
    :title="pageTitle"
    :description="metaDescription"
  />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col">
    <!-- Header Section -->
    <PublicHeader />

    <main class="flex-grow container mx-auto py-8 px-4 max-w-4xl">
      <!-- Breadcrumb -->
      <nav class="mb-6">
        <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
          <li>
            <a href="/" class="hover:text-gray-700 dark:hover:text-gray-200">Home</a>
          </li>
          <li>
            <span class="mx-2">/</span>
          </li>
          <li class="text-gray-700 dark:text-gray-200">{{ pageTitle }}</li>
        </ol>
      </nav>

      <!-- Page Content -->
      <article class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
        <!-- Featured Image -->
        <div v-if="page.featured_image_url" class="mb-8">
          <img
            :src="page.featured_image_url"
            :alt="pageTitle"
            class="w-full h-64 object-cover rounded-lg"
          />
        </div>

        <!-- Page Header -->
        <header class="mb-8">
          <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-100 mb-4">
            {{ pageTitle }}
          </h1>

          <!-- Meta Information -->
          <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 space-x-4">
            <span v-if="page.author" class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
              </svg>
              {{ page.author.name }}
            </span>

            <span class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
              </svg>
              {{ new Date(page.published_at).toLocaleDateString() }}
            </span>
          </div>
        </header>

        <!-- Page Content -->
        <div
          class="prose prose-lg dark:prose-invert max-w-none"
          v-html="pageContent"
        />
      </article>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 dark:bg-gray-950 text-white dark:text-gray-300 p-6 text-center border-t dark:border-gray-700 mt-auto">
      <p>&copy; {{ new Date().getFullYear() }} Showeasy. All rights reserved. Made with ❤️</p>
    </footer>

    <ChatbotWidget v-if="page.props.chatbot_enabled" />
  </div>
</template>
