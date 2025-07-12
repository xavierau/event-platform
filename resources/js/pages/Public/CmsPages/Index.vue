<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import PublicHeader from '@/components/Shared/PublicHeader.vue'

interface CmsPageSummary {
  id: number
  title: Record<string, string>
  slug: string
  meta_description?: Record<string, string>
  published_at: string
  featured_image_thumb_url?: string | null
  author?: {
    name: string
  } | null
}

defineProps<{
  pages: CmsPageSummary[]
}>()

const inertiaPage = usePage()

// Get the current locale from Inertia shared props
const currentLocale = computed(() => (inertiaPage.props.current_locale as string) || 'en')

const getTranslation = (translations: Record<string, string> | null | undefined, locale: string = 'en'): string => {
  if (!translations) return ''
  if (typeof translations === 'string') return translations
  return translations[locale] || translations['en'] || Object.values(translations)[0] || ''
}

// Create computed functions for templates
const getPageTitle = (page: CmsPageSummary) => getTranslation(page.title, currentLocale.value)
const getPageDescription = (page: CmsPageSummary) => getTranslation(page.meta_description, currentLocale.value)
</script>

<template>
  <Head title="Pages" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col">
    <!-- Header Section -->
    <PublicHeader />

    <main class="flex-grow container mx-auto py-8 px-4 max-w-6xl">
      <!-- Breadcrumb -->
      <nav class="mb-6">
        <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
          <li>
            <a href="/" class="hover:text-gray-700 dark:hover:text-gray-200">Home</a>
          </li>
          <li>
            <span class="mx-2">/</span>
          </li>
          <li class="text-gray-700 dark:text-gray-200">Pages</li>
        </ol>
      </nav>

      <!-- Page Header -->
      <header class="mb-8">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-100">
          Pages
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          Browse our collection of informational pages
        </p>
      </header>

      <!-- Pages List -->
      <div v-if="pages.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="page in pages"
          :key="page.id"
          class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow"
        >
          <!-- Featured Image -->
          <div v-if="page.featured_image_thumb_url" class="aspect-video">
            <img
              :src="page.featured_image_thumb_url"
              :alt="getPageTitle(page)"
              class="w-full h-full object-cover"
            />
          </div>

          <!-- Placeholder for pages without images -->
          <div v-else class="aspect-video bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center">
            <svg class="w-12 h-12 text-gray-400 dark:text-gray-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
            </svg>
          </div>

          <!-- Card Content -->
          <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
              <a
                :href="`/pages/${page.slug}`"
                class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
              >
                {{ getPageTitle(page) }}
              </a>
            </h2>

            <p v-if="page.meta_description" class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3 mb-4">
              {{ getPageDescription(page) }}
            </p>

            <!-- Meta Information -->
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
              <span v-if="page.author" class="flex items-center">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
                {{ page.author.name }}
              </span>

              <span class="flex items-center">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                </svg>
                {{ new Date(page.published_at).toLocaleDateString() }}
              </span>
            </div>
          </div>
        </article>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8">
          <svg class="w-16 h-16 text-gray-400 dark:text-gray-500 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
            No Pages Available
          </h3>
          <p class="text-gray-600 dark:text-gray-400">
            There are currently no published pages to display.
          </p>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 dark:bg-gray-950 text-white dark:text-gray-300 p-6 text-center border-t dark:border-gray-700 mt-auto">
      <p>&copy; {{ new Date().getFullYear() }} Showeasy. All rights reserved. Made with ❤️</p>
    </footer>
  </div>
</template>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
