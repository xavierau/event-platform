<template>
    <form @submit.prevent="submit" class="space-y-6">
        <!-- Meta Title Fields -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Meta Title</h3>
            <p class="text-sm text-gray-600 mb-4">Appears in search engine results and browser tabs (recommended: 50-60 characters)</p>

            <div class="space-y-4">
                <div v-for="(locale, localeKey) in availableLocales" :key="`meta_title_${localeKey}`">
                    <label :for="`meta_title_${localeKey}`" class="block text-sm font-medium text-gray-700">
                        {{ locale }} Meta Title
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            v-model="form.meta_title[localeKey]"
                            :id="`meta_title_${localeKey}`"
                            :placeholder="event.name?.[localeKey] || 'Enter meta title...'"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': form.errors[`meta_title.${localeKey}`] }"
                            @input="updateCharacterCount('meta_title', localeKey)"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <span
                                class="text-xs"
                                :class="getCharacterCountClass(form.meta_title[localeKey], 60)"
                            >
                                {{ (form.meta_title[localeKey] || '').length }}/60
                            </span>
                        </div>
                    </div>
                    <div v-if="form.errors[`meta_title.${localeKey}`]" class="text-red-600 text-sm mt-1">
                        {{ form.errors[`meta_title.${localeKey}`] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Meta Description Fields -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Meta Description</h3>
            <p class="text-sm text-gray-600 mb-4">Brief description for search engines (recommended: 150-160 characters)</p>

            <div class="space-y-4">
                <div v-for="(locale, localeKey) in availableLocales" :key="`meta_description_${localeKey}`">
                    <label :for="`meta_description_${localeKey}`" class="block text-sm font-medium text-gray-700">
                        {{ locale }} Meta Description
                    </label>
                    <div class="relative">
                        <textarea
                            v-model="form.meta_description[localeKey]"
                            :id="`meta_description_${localeKey}`"
                            :placeholder="event.short_summary?.[localeKey] || event.description?.[localeKey] || 'Enter meta description...'"
                            rows="3"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': form.errors[`meta_description.${localeKey}`] }"
                            @input="updateCharacterCount('meta_description', localeKey)"
                        ></textarea>
                        <div class="absolute bottom-2 right-2">
                            <span
                                class="text-xs bg-white px-1"
                                :class="getCharacterCountClass(form.meta_description[localeKey], 160)"
                            >
                                {{ (form.meta_description[localeKey] || '').length }}/160
                            </span>
                        </div>
                    </div>
                    <div v-if="form.errors[`meta_description.${localeKey}`]" class="text-red-600 text-sm mt-1">
                        {{ form.errors[`meta_description.${localeKey}`] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Keywords Fields -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Keywords</h3>
            <p class="text-sm text-gray-600 mb-4">Comma-separated keywords relevant to your event</p>

            <div class="space-y-4">
                <div v-for="(locale, localeKey) in availableLocales" :key="`keywords_${localeKey}`">
                    <label :for="`keywords_${localeKey}`" class="block text-sm font-medium text-gray-700">
                        {{ locale }} Keywords
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            v-model="form.keywords[localeKey]"
                            :id="`keywords_${localeKey}`"
                            placeholder="event, concert, music, entertainment"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': form.errors[`keywords.${localeKey}`] }"
                            @input="updateCharacterCount('keywords', localeKey)"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <span
                                class="text-xs"
                                :class="getCharacterCountClass(form.keywords[localeKey], 255)"
                            >
                                {{ (form.keywords[localeKey] || '').length }}/255
                            </span>
                        </div>
                    </div>
                    <div v-if="form.errors[`keywords.${localeKey}`]" class="text-red-600 text-sm mt-1">
                        {{ form.errors[`keywords.${localeKey}`] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Graph Title Fields -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Open Graph Title</h3>
            <p class="text-sm text-gray-600 mb-4">Title for social media sharing (recommended: 50-60 characters)</p>

            <div class="space-y-4">
                <div v-for="(locale, localeKey) in availableLocales" :key="`og_title_${localeKey}`">
                    <label :for="`og_title_${localeKey}`" class="block text-sm font-medium text-gray-700">
                        {{ locale }} Open Graph Title
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            v-model="form.og_title[localeKey]"
                            :id="`og_title_${localeKey}`"
                            :placeholder="form.meta_title[localeKey] || event.name?.[localeKey] || 'Enter Open Graph title...'"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': form.errors[`og_title.${localeKey}`] }"
                            @input="updateCharacterCount('og_title', localeKey)"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <span
                                class="text-xs"
                                :class="getCharacterCountClass(form.og_title[localeKey], 60)"
                            >
                                {{ (form.og_title[localeKey] || '').length }}/60
                            </span>
                        </div>
                    </div>
                    <div v-if="form.errors[`og_title.${localeKey}`]" class="text-red-600 text-sm mt-1">
                        {{ form.errors[`og_title.${localeKey}`] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Graph Description Fields -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Open Graph Description</h3>
            <p class="text-sm text-gray-600 mb-4">Description for social media sharing (recommended: 150-160 characters)</p>

            <div class="space-y-4">
                <div v-for="(locale, localeKey) in availableLocales" :key="`og_description_${localeKey}`">
                    <label :for="`og_description_${localeKey}`" class="block text-sm font-medium text-gray-700">
                        {{ locale }} Open Graph Description
                    </label>
                    <div class="relative">
                        <textarea
                            v-model="form.og_description[localeKey]"
                            :id="`og_description_${localeKey}`"
                            :placeholder="form.meta_description[localeKey] || event.short_summary?.[localeKey] || 'Enter Open Graph description...'"
                            rows="3"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': form.errors[`og_description.${localeKey}`] }"
                            @input="updateCharacterCount('og_description', localeKey)"
                        ></textarea>
                        <div class="absolute bottom-2 right-2">
                            <span
                                class="text-xs bg-white px-1"
                                :class="getCharacterCountClass(form.og_description[localeKey], 160)"
                            >
                                {{ (form.og_description[localeKey] || '').length }}/160
                            </span>
                        </div>
                    </div>
                    <div v-if="form.errors[`og_description.${localeKey}`]" class="text-red-600 text-sm mt-1">
                        {{ form.errors[`og_description.${localeKey}`] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Graph Image -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Open Graph Image</h3>
            <p class="text-sm text-gray-600 mb-4">Image URL for social media sharing (recommended: 1200x630 pixels)</p>

            <div>
                <label for="og_image_url" class="block text-sm font-medium text-gray-700">
                    Image URL
                </label>
                <input
                    type="url"
                    v-model="form.og_image_url"
                    id="og_image_url"
                    placeholder="https://example.com/image.jpg"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': form.errors.og_image_url }"
                >
                <div v-if="form.errors.og_image_url" class="text-red-600 text-sm mt-1">
                    {{ form.errors.og_image_url }}
                </div>
            </div>
        </div>

        <!-- Active Status -->
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <input
                    type="checkbox"
                    v-model="form.is_active"
                    id="is_active"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                >
                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                    Enable SEO settings for this event
                </label>
            </div>
            <p class="text-sm text-gray-600 mt-2">When disabled, fallback to event's basic information for SEO</p>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-end space-x-4 pt-6">
            <Link
                :href="route('admin.events.edit', event.id)"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                Cancel
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span v-if="form.processing">Saving...</span>
                <span v-else>{{ isEdit ? 'Update' : 'Create' }} SEO Settings</span>
            </button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { defineProps, computed, onMounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import type { EventSeoData, EventWithSeo, Translatable } from '@/types';

interface SeoFormData {
    event_id: number;
    meta_title: Translatable;
    meta_description: Translatable;
    keywords: Translatable;
    og_title: Translatable;
    og_description: Translatable;
    og_image_url: string | null;
    is_active: boolean;
}

const props = defineProps<{
    event: EventWithSeo;
    eventSeo?: EventSeoData | null;
    availableLocales: Record<string, string>;
    form: ReturnType<typeof useForm<SeoFormData>>;
    isEdit: boolean;
    submit: () => void;
}>();

// Initialize empty multilingual objects for new forms
const initializeMultilingualObject = (): Translatable => {
    const obj: Translatable = {};
    Object.keys(props.availableLocales).forEach(locale => {
        obj[locale] = '';
    });
    return obj;
};

// Initialize form data on mount
onMounted(() => {
    if (props.eventSeo) {
        // Pre-populate with existing data
        props.form.defaults({
            event_id: props.event.id,
            meta_title: props.eventSeo.meta_title || initializeMultilingualObject(),
            meta_description: props.eventSeo.meta_description || initializeMultilingualObject(),
            keywords: props.eventSeo.keywords || initializeMultilingualObject(),
            og_title: props.eventSeo.og_title || initializeMultilingualObject(),
            og_description: props.eventSeo.og_description || initializeMultilingualObject(),
            og_image_url: props.eventSeo.og_image_url || null,
            is_active: props.eventSeo.is_active ?? true,
        });
    } else {
        // Initialize with empty multilingual objects
        props.form.defaults({
            event_id: props.event.id,
            meta_title: initializeMultilingualObject(),
            meta_description: initializeMultilingualObject(),
            keywords: initializeMultilingualObject(),
            og_title: initializeMultilingualObject(),
            og_description: initializeMultilingualObject(),
            og_image_url: null,
            is_active: true,
        });
    }
    props.form.reset();
});

// Character count validation helper
const getCharacterCountClass = (text: string | undefined, limit: number): string => {
    const length = (text || '').length;
    if (length === 0) return 'text-gray-400';
    if (length > limit) return 'text-red-600 font-medium';
    if (length > limit * 0.9) return 'text-orange-600';
    return 'text-green-600';
};

// Update character count (for reactivity)
const updateCharacterCount = (field: string, locale: string) => {
    // This method exists to trigger reactivity for character count updates
    // The actual character counting is handled by the computed getCharacterCountClass
};
</script>