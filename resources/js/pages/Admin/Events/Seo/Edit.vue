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
                            Optimize your event for search engines and social media sharing
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <Link
                            :href="route('admin.events.seo.show', event.id)"
                            class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            View SEO
                        </Link>
                        <button
                            @click="previewSeo"
                            type="button"
                            class="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-100 border border-indigo-300 rounded-md hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Preview
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <SeoForm
                :event="event"
                :event-seo="eventSeo"
                :available-locales="availableLocales"
                :form="form"
                :is-edit="!!eventSeo"
                :submit="submit"
            />

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
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SeoForm from './SeoForm.vue';
import type { EventSeoData, EventWithSeo, SeoMetaTags } from '@/types';

interface Props {
    event: EventWithSeo;
    eventSeo?: EventSeoData | null;
    availableLocales: Record<string, string>;
}

const props = defineProps<Props>();

const showPreview = ref(false);
const previewData = ref<{ metaTags: SeoMetaTags } | null>(null);

// Form setup
const form = useForm({
    event_id: props.event.id,
    meta_title: {} as Record<string, string>,
    meta_description: {} as Record<string, string>,
    keywords: {} as Record<string, string>,
    og_title: {} as Record<string, string>,
    og_description: {} as Record<string, string>,
    og_image_url: null as string | null,
    is_active: true,
});

// Submit form
const submit = () => {
    const url = props.eventSeo
        ? route('admin.events.seo.update', props.event.id)
        : route('admin.events.seo.store', props.event.id);

    const method = props.eventSeo ? 'put' : 'post';

    form[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            // Redirect to show page on success
            router.visit(route('admin.events.seo.show', props.event.id));
        },
    });
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
</script>