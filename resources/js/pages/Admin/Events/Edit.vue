<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { watchEffect, ref } from 'vue';
// import CommentModeration from '@/components/CommentModeration.vue';

// Type definitions are complex and long, assuming they are correct for now.
// If there are type errors, they will need to be addressed separately.
interface Locale { code: string; name: string; }
interface SelectOption { value: string | number; label: string; }
interface MediaItem { id: number; url: string; name?: string; }
interface Translatable { [key: string]: string | undefined; }
interface EventData {
    id: number;
    organizer_id: number | null;
    category_id: number | null;
    name: Translatable;
    slug: Translatable;
    description: Translatable;
    short_summary: Translatable;
    event_status: string;
    visibility: string;
    is_featured: boolean;
    contact_email?: string;
    contact_phone?: string;
    website_url?: string;
    social_facebook?: string;
    social_twitter?: string;
    social_instagram?: string;
    social_linkedin?: string;
    youtube_video_id?: string;
    cancellation_policy: Translatable;
    meta_title: Translatable;
    meta_description: Translatable;
    meta_keywords: Translatable;
    published_at?: string | null;
    tag_ids?: number[];
    portrait_poster_url?: string | null;
    landscape_poster_url?: string | null;
    gallery_items?: MediaItem[];
}
interface BreadcrumbItem { title: string; url?: string; disabled?: boolean; }
interface EventFormData {
    id: number | null;
    organizer_id: number | null;
    category_id: number | null;
    name: Record<string, string | undefined>;
    slug: Record<string, string | undefined>;
    description: Record<string, string | undefined>;
    short_summary: Record<string, string | undefined>;
    event_status: string;
    visibility: string;
    is_featured: boolean;
    contact_email: string;
    contact_phone: string;
    website_url: string;
    social_facebook: string;
    social_twitter: string;
    social_instagram: string;
    social_linkedin: string;
    youtube_video_id: string;
    cancellation_policy: Record<string, string | undefined>;
    meta_title: Record<string, string | undefined>;
    meta_description: Record<string, string | undefined>;
    meta_keywords: Record<string, string | undefined>;
    published_at: string | null;
    tag_ids: number[];
    uploaded_portrait_poster: File | null;
    uploaded_landscape_poster: File | null;
    uploaded_gallery: File[];
    removed_gallery_ids: number[];
    _method: 'PUT' | 'POST';
    [key: string]: any;
}

const props = defineProps<{
    event?: EventData;
    categories?: SelectOption[];
    tags?: SelectOption[];
    organizers?: SelectOption[];
    eventStatuses?: SelectOption[];
    visibilities?: SelectOption[];
    availableLocales?: Locale[];
    pageTitle?: string;
    breadcrumbs?: BreadcrumbItem[];
    commentConfigOptions: { value: string; label: string }[];
}>();

const locales: Locale[] = props.availableLocales || [{ code: 'en', name: 'English' }];

const form = useForm<EventFormData>({
    id: null,
    organizer_id: null,
    category_id: null,
    name: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    slug: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    short_summary: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    event_status: 'draft',
    visibility: 'private',
    is_featured: false,
    contact_email: '',
    contact_phone: '',
    website_url: '',
    social_facebook: '',
    social_twitter: '',
    social_instagram: '',
    social_linkedin: '',
    youtube_video_id: '',
    cancellation_policy: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    meta_title: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    meta_description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    meta_keywords: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    published_at: null,
    tag_ids: [],
    uploaded_portrait_poster: null,
    uploaded_landscape_poster: null,
    uploaded_gallery: [],
    removed_gallery_ids: [],
    _method: 'PUT',
});

const currentTab = ref('coreDetails');

const mainTabs = [
    { id: 'coreDetails', label: 'Core Details' },
    { id: 'translatableContent', label: 'Translatable Content' },
    { id: 'contactLinks', label: 'Contact & Links' },
    { id: 'tags', label: 'Tags' },
    { id: 'media', label: 'Media' },
    { id: 'comments', label: 'Comments' },
];

watchEffect(() => {
    if (props.event) {
        form.defaults({
            ...form.data,
            ...props.event,
        });
        form.reset();
    }
});

const submit = () => {
    if (props.event) {
        form.post(route('admin.events.update', props.event.id));
    }
};

</script>

<template>
    <Head :title="props.pageTitle || 'Edit Event'" />

    <AppLayout :breadcrumbs="props.breadcrumbs" :page-title="props.pageTitle">
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button v-for="tab in mainTabs" :key="tab.id" @click="currentTab = tab.id"
                            :class="[currentTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                <form @submit.prevent="submit">
                    <div v-show="currentTab === 'coreDetails'">
                        <h3 class="text-lg font-medium">Core Details</h3>
                        <p class="text-sm text-gray-500">Form fields for core details would go here.</p>
                        <div class="mt-4">
                            <label for="comment_config" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Comment Configuration</label>
                            <select id="comment_config" v-model="form.comment_config" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option v-for="option in props.commentConfigOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div v-show="currentTab === 'translatableContent'">
                        <h3 class="text-lg font-medium">Translatable Content</h3>
                         <p class="text-sm text-gray-500">Form fields for translatable content would go here.</p>
                            </div>
                    <div v-show="currentTab === 'contactLinks'">
                        <h3 class="text-lg font-medium">Contact & Links</h3>
                        <p class="text-sm text-gray-500">Form fields for contact and links would go here.</p>
                            </div>
                    <div v-show="currentTab === 'tags'">
                        <h3 class="text-lg font-medium">Tags</h3>
                        <p class="text-sm text-gray-500">Form fields for tags would go here.</p>
                            </div>
                    <div v-show="currentTab === 'media'">
                        <h3 class="text-lg font-medium">Media</h3>
                        <p class="text-sm text-gray-500">Form fields for media would go here.</p>
                            </div>
                    <div v-show="currentTab === 'comments'">
                        <CommentModeration v-if="props.event" :event="props.event" />
                        </div>

                    <div class="mt-8 pt-5 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-end">
                            <Link :href="route('admin.events.index')"
                                class="bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </Link>
                            <button type="submit" :disabled="form.processing"
                                class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
                        </div>
                    </form>
            </div>
        </div>
    </AppLayout>
</template>
