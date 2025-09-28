<template>
    <AppLayout>
        <Head :title="`SEO Settings - ${event.name?.en || event.name || 'Event'}`" />

        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
                    <Link :href="route('admin.events.index')" class="hover:text-gray-700">Events</Link>
                    <span>/</span>
                    <Link :href="route('admin.events.edit', event.id)" class="hover:text-gray-700">
                        {{ event.name?.en || event.name || 'Event' }}
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900">SEO Settings</span>
                </nav>

                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">SEO Settings</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Current SEO configuration for this event
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button
                            @click="previewSeo"
                            type="button"
                            class="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-100 border border-indigo-300 rounded-md hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Preview
                        </button>
                        <Link
                            :href="route('admin.events.seo.edit', event.id)"
                            class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Edit SEO
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div v-if="eventSeo" class="space-y-6">
                <!-- Status -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">SEO Status</h3>
                            <p class="text-sm text-gray-600 mt-1">Current activation status</p>
                        </div>
                        <div class="flex items-center">
                            <span
                                class="px-2 py-1 text-xs font-medium rounded-full"
                                :class="eventSeo.is_active
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-gray-100 text-gray-800'"
                            >
                                {{ eventSeo.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Meta Title -->
                <div v-if="hasAnyContent(eventSeo.meta_title)" class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Meta Title</h3>
                    <div class="space-y-3">
                        <div v-for="(locale, localeKey) in availableLocales" :key="`meta_title_${localeKey}`">
                            <div v-if="eventSeo.meta_title?.[localeKey]" class="border rounded-md p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">{{ locale }}</span>
                                    <span class="text-xs text-gray-500">
                                        {{ eventSeo.meta_title[localeKey].length }}/60 characters
                                    </span>
                                </div>
                                <p class="text-gray-900">{{ eventSeo.meta_title[localeKey] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meta Description -->
                <div v-if="hasAnyContent(eventSeo.meta_description)" class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Meta Description</h3>
                    <div class="space-y-3">
                        <div v-for="(locale, localeKey) in availableLocales" :key="`meta_description_${localeKey}`">
                            <div v-if="eventSeo.meta_description?.[localeKey]" class="border rounded-md p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">{{ locale }}</span>
                                    <span class="text-xs text-gray-500">
                                        {{ eventSeo.meta_description[localeKey].length }}/160 characters
                                    </span>
                                </div>
                                <p class="text-gray-900">{{ eventSeo.meta_description[localeKey] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Keywords -->
                <div v-if="hasAnyContent(eventSeo.keywords)" class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Keywords</h3>
                    <div class="space-y-3">
                        <div v-for="(locale, localeKey) in availableLocales" :key="`keywords_${localeKey}`">
                            <div v-if="eventSeo.keywords?.[localeKey]" class="border rounded-md p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">{{ locale }}</span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="keyword in eventSeo.keywords[localeKey].split(',')"
                                        :key="keyword.trim()"
                                        class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded"
                                    >
                                        {{ keyword.trim() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Open Graph Title -->
                <div v-if="hasAnyContent(eventSeo.og_title)" class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Open Graph Title</h3>
                    <div class="space-y-3">
                        <div v-for="(locale, localeKey) in availableLocales" :key="`og_title_${localeKey}`">
                            <div v-if="eventSeo.og_title?.[localeKey]" class="border rounded-md p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">{{ locale }}</span>
                                    <span class="text-xs text-gray-500">
                                        {{ eventSeo.og_title[localeKey].length }}/60 characters
                                    </span>
                                </div>
                                <p class="text-gray-900">{{ eventSeo.og_title[localeKey] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Open Graph Description -->
                <div v-if="hasAnyContent(eventSeo.og_description)" class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Open Graph Description</h3>
                    <div class="space-y-3">
                        <div v-for="(locale, localeKey) in availableLocales" :key="`og_description_${localeKey}`">
                            <div v-if="eventSeo.og_description?.[localeKey]" class="border rounded-md p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">{{ locale }}</span>
                                    <span class="text-xs text-gray-500">
                                        {{ eventSeo.og_description[localeKey].length }}/160 characters
                                    </span>
                                </div>
                                <p class="text-gray-900">{{ eventSeo.og_description[localeKey] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Open Graph Image -->
                <div v-if="eventSeo.og_image_url" class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Open Graph Image</h3>
                    <div class="space-y-3">
                        <div class="border rounded-md p-3">
                            <p class="text-sm text-gray-600 mb-2">Image URL:</p>
                            <a
                                :href="eventSeo.og_image_url"
                                target="_blank"
                                class="text-indigo-600 hover:text-indigo-800 break-all"
                            >
                                {{ eventSeo.og_image_url }}
                            </a>
                            <div class="mt-3">
                                <img
                                    :src="eventSeo.og_image_url"
                                    alt="Open Graph Image"
                                    class="max-w-sm h-auto border rounded"
                                    @error="$event.target.style.display='none'"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                    <div class="flex items-center space-x-3">
                        <Link
                            :href="route('admin.events.seo.edit', event.id)"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Edit SEO Settings
                        </Link>
                        <button
                            @click="confirmDelete"
                            class="px-4 py-2 text-sm font-medium text-red-700 bg-red-100 border border-red-300 rounded-md hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            Remove SEO Settings
                        </button>
                    </div>
                </div>
            </div>

            <!-- No SEO Settings -->
            <div v-else class="bg-white p-6 rounded-lg shadow text-center">
                <div class="max-w-md mx-auto">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No SEO Settings</h3>
                    <p class="text-gray-600 mb-4">
                        This event doesn't have custom SEO settings yet. Add them to improve search engine visibility.
                    </p>
                    <Link
                        :href="route('admin.events.seo.edit', event.id)"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Add SEO Settings
                    </Link>
                </div>
            </div>

            <!-- Preview Modal -->
            <div
                v-if="showPreview"
                class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
                @click="closePreview"
            >
                <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white" @click.stop>
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">SEO Preview</h3>
                            <button
                                @click="closePreview"
                                class="text-gray-400 hover:text-gray-600"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div v-if="previewData" class="space-y-4">
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <h4 class="font-medium text-sm text-gray-700 mb-2">Search Engine Result</h4>
                                <div class="bg-white p-3 rounded border">
                                    <div class="text-blue-600 hover:underline cursor-pointer text-lg">
                                        {{ previewData.metaTags.title || 'No title set' }}
                                    </div>
                                    <div class="text-green-600 text-sm">
                                        {{ $page.props.ziggy.url }}/events/{{ event.slug?.en || event.id }}
                                    </div>
                                    <div class="text-gray-600 text-sm mt-1">
                                        {{ previewData.metaTags.description || 'No description set' }}
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded-lg p-4 bg-gray-50">
                                <h4 class="font-medium text-sm text-gray-700 mb-2">Social Media Preview</h4>
                                <div class="bg-white border rounded-lg overflow-hidden max-w-md">
                                    <div v-if="previewData.metaTags['og:image']" class="h-40 bg-gray-200 flex items-center justify-center">
                                        <img
                                            :src="previewData.metaTags['og:image']"
                                            :alt="previewData.metaTags['og:title']"
                                            class="h-full w-full object-cover"
                                            @error="$event.target.style.display='none'"
                                        >
                                    </div>
                                    <div class="p-3">
                                        <div class="font-medium text-sm">
                                            {{ previewData.metaTags['og:title'] || previewData.metaTags.title || 'No title set' }}
                                        </div>
                                        <div class="text-gray-600 text-xs mt-1">
                                            {{ previewData.metaTags['og:description'] || previewData.metaTags.description || 'No description set' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
                @click="cancelDelete"
            >
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" @click.stop>
                    <div class="mt-3 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mt-2">Remove SEO Settings</h3>
                        <div class="mt-2 px-7 py-3">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to remove all SEO settings for this event? This action cannot be undone.
                            </p>
                        </div>
                        <div class="flex items-center justify-center gap-4 mt-4">
                            <button
                                @click="cancelDelete"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Cancel
                            </button>
                            <button
                                @click="deleteSeo"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { EventSeoData, EventWithSeo, SeoMetaTags } from '@/types';

interface Props {
    event: EventWithSeo;
    eventSeo?: EventSeoData | null;
    availableLocales: Record<string, string>;
}

const props = defineProps<Props>();

const showPreview = ref(false);
const showDeleteConfirm = ref(false);
const previewData = ref<{ metaTags: SeoMetaTags } | null>(null);

// Helper to check if any content exists in translatable object
const hasAnyContent = (translatableObject: Record<string, string> | null | undefined): boolean => {
    if (!translatableObject) return false;
    return Object.values(translatableObject).some(value => value && value.trim() !== '');
};

// Preview SEO
const previewSeo = async () => {
    try {
        const response = await fetch(route('admin.events.seo.preview', props.event.id), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });

        if (response.ok) {
            previewData.value = await response.json();
            showPreview.value = true;
        }
    } catch (error) {
        console.error('Failed to preview SEO:', error);
    }
};

const closePreview = () => {
    showPreview.value = false;
    previewData.value = null;
};

// Delete SEO settings
const confirmDelete = () => {
    showDeleteConfirm.value = true;
};

const cancelDelete = () => {
    showDeleteConfirm.value = false;
};

const deleteSeo = () => {
    router.delete(route('admin.events.seo.destroy', props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
        },
    });
};
</script>