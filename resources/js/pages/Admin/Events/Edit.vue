<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
// import TextInput from '@/Components/TextInput.vue';
// import SelectInput from '@/Components/SelectInput.vue';
// import FileInput from '@/Components/FileInput.vue';
// import PrimaryButton from '@/Components/PrimaryButton.vue';
import RichTextEditor from '@/components/Form/RichTextEditor.vue';
import { watchEffect, computed, ref } from 'vue';

// --- START TYPE DEFINITIONS ---
interface Locale {
    code: string;
    name: string;
}

interface SelectOption {
    value: string | number;
    label: string;
}

interface VenueSelectItem extends SelectOption {
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state_province: string;
    postal_code: string;
    country: string;
    latitude?: number | null;
    longitude?: number | null;
}

interface MediaItem {
    id: number;
    url: string;
    name?: string;
    // Add other relevant media properties if available from backend
}

interface Translatable {
    [key: string]: string | undefined; // Allow undefined for potentially missing translations
}

interface EventData {
    id: number; // For an existing event, ID should be a number
    organizer_id: number | null;
    category_id: number | null;
    name: Translatable;
    slug: Translatable;
    description: Translatable;
    short_summary: Translatable;
    status: string;
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

interface BreadcrumbItem {
  title: string;
  url?: string;
  disabled?: boolean;
}

interface EventFormData {
    id: number | null; // Can be null for a new event, but for edit, it's populated
    organizer_id: number | null;
    category_id: number | null;
    name: Record<string, string>;
    slug: Record<string, string>;
    description: Record<string, string>;
    short_summary: Record<string, string>;
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
    cancellation_policy: Record<string, string>;
    meta_title: Record<string, string>;
    meta_description: Record<string, string>;
    meta_keywords: Record<string, string>;
    published_at: string | null;
    tag_ids: number[];
    uploaded_portrait_poster: File | null;
    uploaded_landscape_poster: File | null;
    uploaded_gallery: File[];
    removed_gallery_ids: number[];
    _method: 'PUT' | 'POST';
    [key: string]: any; // Index signature for FormDataType compatibility
}
// --- END TYPE DEFINITIONS ---

const props = defineProps<{
    event?: EventData; // EventData is for an existing event, hence id is number
    categories?: SelectOption[];
    tags?: SelectOption[];
    organizers?: SelectOption[];
    eventStatuses?: SelectOption[];
    visibilities?: SelectOption[];
    venues?: VenueSelectItem[];
    pageTitle?: string;
    breadcrumbs?: BreadcrumbItem[];
    availableLocales?: Locale[]; // Add the new prop for available locales
}>();

const locales: Locale[] = props.availableLocales || [{ code: 'en', name: 'English' }];

const form = useForm<EventFormData>({
    id: null,
    organizer_id: null,
    category_id: null,
    name: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    slug: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    short_summary: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
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
    cancellation_policy: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    meta_title: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    meta_description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    meta_keywords: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    published_at: null,
    tag_ids: [],
    uploaded_portrait_poster: null,
    uploaded_landscape_poster: null,
    uploaded_gallery: [],
    removed_gallery_ids: [],
    _method: 'PUT',
});

const currentTab = ref('coreDetails'); // Default active tab for Edit page
const isAddressReadOnly = ref(false);
const currentLocaleCode = ref(locales[0].code); // For language tabs

const mainTabs = [
    { id: 'coreDetails', label: 'Core Details' },
    { id: 'translatableContent', label: 'Translatable Content' },
    { id: 'contactLinks', label: 'Contact & Links' },
    { id: 'tags', label: 'Tags' },
    { id: 'media', label: 'Media' },
];

// Populate form with event data when props.event is available
watchEffect(() => {
    if (props.event) {
        const eventData = props.event;
        form.defaults({
            ...eventData,
            id: eventData.id, // props.event.id is number, form.id can be null initially but is set here
            organizer_id: eventData.organizer_id || null,
            category_id: eventData.category_id || null,
            name: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.name?.[loc.code] || '' }), {} as Record<string, string>),
            slug: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.slug?.[loc.code] || '' }), {} as Record<string, string>),
            description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.description?.[loc.code] || '' }), {} as Record<string, string>),
            short_summary: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.short_summary?.[loc.code] || '' }), {} as Record<string, string>),
            event_status: eventData.status || 'draft',
            visibility: eventData.visibility || 'private',
            is_featured: eventData.is_featured || false,
            contact_email: eventData.contact_email || '',
            contact_phone: eventData.contact_phone || '',
            website_url: eventData.website_url || '',
            social_facebook: eventData.social_facebook || '',
            social_twitter: eventData.social_twitter || '',
            social_instagram: eventData.social_instagram || '',
            social_linkedin: eventData.social_linkedin || '',
            youtube_video_id: eventData.youtube_video_id || '',
            cancellation_policy: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.cancellation_policy?.[loc.code] || '' }), {} as Record<string, string>),
            meta_title: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.meta_title?.[loc.code] || '' }), {} as Record<string, string>),
            meta_description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.meta_description?.[loc.code] || '' }), {} as Record<string, string>),
            meta_keywords: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: eventData.meta_keywords?.[loc.code] || '' }), {} as Record<string, string>),
            published_at: eventData.published_at || null,
            tag_ids: eventData.tag_ids || [],
            uploaded_portrait_poster: null,
            uploaded_landscape_poster: null,
            uploaded_gallery: [],
            removed_gallery_ids: [],
            _method: 'PUT',
        });
        form.reset();
        isAddressReadOnly.value = false;
    }
});

const submit = () => {
    // Ensure props.event and props.event.id are available for update
    if (props.event && typeof props.event.id === 'number') {
        form.post(route('admin.events.update', props.event.id), {
            // onError, onSuccess can be added here if needed
        });
    } else {
        // Handle case where event or event.id is not available, perhaps show an error
        console.error("Cannot update event: Event data or ID is missing.");
        // Optionally, you could disable the submit button or show a user-facing error.
    }
};

// Type for keys of translatable objects within EventFormData
type TranslatableFieldKey = 'name' | 'slug' | 'description' | 'short_summary' | 'cancellation_policy' | 'meta_title' | 'meta_description' | 'meta_keywords';

const tFieldName = (field: TranslatableFieldKey, localeCode: string): string => `${field}.${localeCode}`;

// Method to mark a gallery item for removal
const removeGalleryItem = (mediaId: number) => {
    if (!form.removed_gallery_ids.includes(mediaId)) {
        form.removed_gallery_ids.push(mediaId);
    }
};

// Computed property to filter out gallery items marked for removal for display purposes
const displayedGalleryItems = computed(() => {
    if (!props.event?.gallery_items) {
        return [];
    }
    return props.event.gallery_items.filter((item: MediaItem) => !form.removed_gallery_ids.includes(item.id));
});

// TODO: Add methods to display existing media and handle their removal if necessary

</script>

<template>

    <Head :title="props.pageTitle || (props.event ? 'Edit Event: ' + (props.event.name?.[currentLocaleCode] || props.event.id) : 'Edit Event')" />

    <AppLayout :page-title="props.pageTitle || (props.event ? 'Edit Event: ' + (props.event.name?.[currentLocaleCode] || props.event.id) : 'Edit Event')" :breadcrumbs="props.breadcrumbs">
        <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div v-if="!props.event" class="text-center py-12">
                <p class="text-lg text-gray-600">Loading event data or event not found...</p>
                <!-- Optional: Add a link to go back or to the events index -->
                 <Link :href="route('admin.events.index')" class="mt-4 inline-block text-indigo-600 hover:text-indigo-800">
                    Go to Events List
                </Link>
            </div>
            <div v-else class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <!-- Main Tab Navigation -->
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button
                                v-for="tab in mainTabs"
                                :key="tab.id"
                                @click="currentTab = tab.id"
                                :class="[
                                    currentTab === tab.id
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm focus:outline-none'
                                ]"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <form @submit.prevent="submit" class="space-y-8">
                        <!-- Section: Core Details -->
                        <div v-if="currentTab === 'coreDetails'" class="space-y-6">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Core Event Details</h3>
                                <p class="mt-1 text-sm text-gray-500">Basic information defining the event.</p>
                            </div>

                            <!-- Organizer -->
                            <div>
                                <label for="organizer_id"
                                    class="block text-sm font-medium text-gray-700">Organizer</label>
                                <select id="organizer_id" v-model="form.organizer_id"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option :value="null" disabled>Select an organizer</option>
                                    <option v-for="organizer in props.organizers" :key="organizer.value"
                                        :value="organizer.value">
                                        {{ organizer.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.organizer_id" class="text-sm text-red-600 mt-1">{{
                                    form.errors.organizer_id }}</div>
                            </div>

                            <!-- Category -->
                            <div>
                                <label for="category_id"
                                    class="block text-sm font-medium text-gray-700">Category</label>
                                <select id="category_id" v-model="form.category_id"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option :value="null" disabled>Select a category</option>
                                    <option v-for="category in props.categories" :key="category.value"
                                        :value="category.value">
                                        {{ category.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.category_id" class="text-sm text-red-600 mt-1">{{
                                    form.errors.category_id }}</div>
                            </div>

                            <!-- Event Status -->
                            <div>
                                <label for="event_status" class="block text-sm font-medium text-gray-700">Event
                                    Status</label>
                                <select id="event_status" v-model="form.event_status"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option v-for="s_option in props.eventStatuses" :key="s_option.value"
                                        :value="s_option.value">
                                        {{ s_option.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.event_status" class="text-sm text-red-600 mt-1">{{
                                    form.errors.event_status }}</div>
                            </div>

                            <!-- Visibility -->
                            <div>
                                <label for="visibility"
                                    class="block text-sm font-medium text-gray-700">Visibility</label>
                                <select id="visibility" v-model="form.visibility"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option v-for="option in props.visibilities" :key="option.value"
                                        :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.visibility" class="text-sm text-red-600 mt-1">{{
                                    form.errors.visibility }}</div>
                            </div>

                            <!-- Is Featured -->
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_featured" v-model="form.is_featured" type="checkbox"
                                        class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_featured" class="font-medium text-gray-700">Featured Event</label>
                                    <p class="text-gray-500">Highlight this event on public pages.</p>
                                </div>
                                <div v-if="form.errors.is_featured"
                                    class="absolute bottom-[-20px] text-sm text-red-600 mt-1">{{ form.errors.is_featured }}
                                </div>
                            </div>
                        </div>

                        <!-- Section: Translatable Content -->
                        <div v-if="currentTab === 'translatableContent'" class="space-y-6">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Translatable Content</h3>
                                <p class="mt-1 text-sm text-gray-500">Provide details in all supported languages.</p>
                            </div>

                            <!-- Language Tabs -->
                            <div class="mb-4 border-b border-gray-200">
                                <nav class="-mb-px flex space-x-4" aria-label="Language Tabs">
                                    <button
                                        type="button"
                                        v-for="locale in locales"
                                        :key="locale.code"
                                        @click="currentLocaleCode = locale.code"
                                        :class="[
                                            currentLocaleCode === locale.code
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                            'whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm focus:outline-none'
                                        ]"
                                    >
                                        {{ locale.name }}
                                    </button>
                                </nav>
                            </div>

                            <!-- Content for selected language tab -->
                            <div class="space-y-6">
                                <!-- Event Name -->
                                <div>
                                    <label :for="tFieldName('name', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Event Name ({{ locales.find(l => l.code === currentLocaleCode)?.name }})</label>
                                    <input type="text" :id="tFieldName('name', currentLocaleCode)" v-model="form.name[currentLocaleCode]"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" />
                                    <div v-if="form.errors[tFieldName('name', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('name', currentLocaleCode)] }}
                                    </div>
                                </div>

                                <!-- Event Slug -->
                                <div>
                                    <label :for="tFieldName('slug', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Event Slug ({{ locales.find(l => l.code === currentLocaleCode)?.name }})</label>
                                    <input type="text" :id="tFieldName('slug', currentLocaleCode)" v-model="form.slug[currentLocaleCode]"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" />
                                    <div v-if="form.errors[tFieldName('slug', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('slug', currentLocaleCode)] }}
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label :for="tFieldName('description', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Description ({{ locales.find(l => l.code === currentLocaleCode)?.name }})</label>
                                    <RichTextEditor :id="tFieldName('description', currentLocaleCode)" v-model="form.description[currentLocaleCode]" class="mt-1" />
                                    <div v-if="form.errors[tFieldName('description', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('description', currentLocaleCode)] }}
                                    </div>
                                </div>

                                <!-- Short Summary -->
                                <div>
                                    <label :for="tFieldName('short_summary', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Short Summary ({{ locales.find(l => l.code === currentLocaleCode)?.name }})</label>
                                    <textarea :id="tFieldName('short_summary', currentLocaleCode)" v-model="form.short_summary[currentLocaleCode]" rows="3"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                                    <div v-if="form.errors[tFieldName('short_summary', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('short_summary', currentLocaleCode)] }}
                                    </div>
                                </div>

                                <!-- Cancellation Policy -->
                                <div>
                                    <label :for="tFieldName('cancellation_policy', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Cancellation Policy ({{ locales.find(l => l.code === currentLocaleCode)?.name }})</label>
                                    <RichTextEditor :id="tFieldName('cancellation_policy', currentLocaleCode)" v-model="form.cancellation_policy[currentLocaleCode]" class="mt-1" />
                                    <div v-if="form.errors[tFieldName('cancellation_policy', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('cancellation_policy', currentLocaleCode)] }}
                                    </div>
                                </div>

                                <!-- Meta Title -->
                                <div>
                                    <label :for="tFieldName('meta_title', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Meta Title ({{ locales.find(l => l.code === currentLocaleCode)?.name }})</label>
                                    <input type="text" :id="tFieldName('meta_title', currentLocaleCode)" v-model="form.meta_title[currentLocaleCode]"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" />
                                    <div v-if="form.errors[tFieldName('meta_title', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('meta_title', currentLocaleCode)] }}
                                    </div>
                                </div>

                                <!-- Meta Description -->
                                <div>
                                    <label :for="tFieldName('meta_description', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Meta Description ({{ locales.find(l => l.code === currentLocaleCode)?.name }})</label>
                                    <textarea :id="tFieldName('meta_description', currentLocaleCode)" v-model="form.meta_description[currentLocaleCode]" rows="3"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                                    <div v-if="form.errors[tFieldName('meta_description', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('meta_description', currentLocaleCode)] }}
                                    </div>
                                </div>

                                <!-- Meta Keywords -->
                                <div>
                                    <label :for="tFieldName('meta_keywords', currentLocaleCode)" class="block text-sm font-medium text-gray-700">Meta Keywords ({{ locales.find(l => l.code === currentLocaleCode)?.name }}) <span class="text-xs text-gray-500">(comma-separated)</span></label>
                                    <input type="text" :id="tFieldName('meta_keywords', currentLocaleCode)" v-model="form.meta_keywords[currentLocaleCode]"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" />
                                    <div v-if="form.errors[tFieldName('meta_keywords', currentLocaleCode)]" class="text-sm text-red-600 mt-1">
                                        {{ form.errors[tFieldName('meta_keywords', currentLocaleCode)] }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Contact & Links -->
                        <div v-if="currentTab === 'contactLinks'" class="space-y-6">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Contact Information & Links</h3>
                                <p class="mt-1 text-sm text-gray-500">How attendees can get more information or contact organizers.</p>
                            </div>

                            <div>
                                <label for="contact_email" class="block text-sm font-medium text-gray-700">Contact Email</label>
                                <input type="email" v-model="form.contact_email" id="contact_email" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                                <div v-if="form.errors.contact_email" class="text-sm text-red-600 mt-1">{{ form.errors.contact_email }}</div>
                            </div>

                            <div>
                                <label for="contact_phone" class="block text-sm font-medium text-gray-700">Contact Phone</label>
                                <input type="text" v-model="form.contact_phone" id="contact_phone" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                                <div v-if="form.errors.contact_phone" class="text-sm text-red-600 mt-1">{{ form.errors.contact_phone }}</div>
                            </div>

                            <div>
                                <label for="website_url" class="block text-sm font-medium text-gray-700">Website URL</label>
                                <input type="url" v-model="form.website_url" id="website_url" placeholder="https://example.com" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                                <div v-if="form.errors.website_url" class="text-sm text-red-600 mt-1">{{ form.errors.website_url }}</div>
                            </div>

                            <div>
                                <label for="youtube_video_id" class="block text-sm font-medium text-gray-700">YouTube Video ID</label>
                                <input type="text" v-model="form.youtube_video_id" id="youtube_video_id" placeholder="dQw4w9WgXcQ" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                                <div v-if="form.errors.youtube_video_id" class="text-sm text-red-600 mt-1">{{ form.errors.youtube_video_id }}</div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="social_facebook" class="block text-sm font-medium text-gray-700">Facebook URL</label>
                                    <input type="url" v-model="form.social_facebook" id="social_facebook" placeholder="https://facebook.com/username" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"/>
                                    <div v-if="form.errors.social_facebook" class="text-sm text-red-600 mt-1">{{ form.errors.social_facebook }}</div>
                                </div>
                                <div>
                                    <label for="social_twitter" class="block text-sm font-medium text-gray-700">Twitter URL</label>
                                    <input type="url" v-model="form.social_twitter" id="social_twitter" placeholder="https://twitter.com/username" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"/>
                                    <div v-if="form.errors.social_twitter" class="text-sm text-red-600 mt-1">{{ form.errors.social_twitter }}</div>
                                </div>
                                <div>
                                    <label for="social_instagram" class="block text-sm font-medium text-gray-700">Instagram URL</label>
                                    <input type="url" v-model="form.social_instagram" id="social_instagram" placeholder="https://instagram.com/username" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"/>
                                    <div v-if="form.errors.social_instagram" class="text-sm text-red-600 mt-1">{{ form.errors.social_instagram }}</div>
                                </div>
                                <div>
                                    <label for="social_linkedin" class="block text-sm font-medium text-gray-700">LinkedIn URL</label>
                                    <input type="url" v-model="form.social_linkedin" id="social_linkedin" placeholder="https://linkedin.com/in/username" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"/>
                                    <div v-if="form.errors.social_linkedin" class="text-sm text-red-600 mt-1">{{ form.errors.social_linkedin }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Tags -->
                        <div v-if="currentTab === 'tags'" class="space-y-6">
                             <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Tags</h3>
                            </div>
                            <div>
                                <label for="tag_ids" class="block text-sm font-medium text-gray-700">Assign Tags</label>
                                <select id="tag_ids" v-model="form.tag_ids" multiple class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md h-40">
                                    <option v-for="tag in props.tags" :key="tag.value" :value="tag.value">
                                        {{ tag.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.tag_ids" class="text-sm text-red-600 mt-1">{{ form.errors.tag_ids }}</div>
                            </div>
                        </div>

                        <!-- Section: Media Uploads -->
                        <div v-if="currentTab === 'media'" class="space-y-6">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Media Uploads</h3>
                                <p class="mt-1 text-sm text-gray-500">Upload posters and gallery images for the event. Uploading new files will replace existing ones if applicable.</p>
                            </div>

                            <div v-if="props.event.portrait_poster_url" class="mb-4 pb-4 border-b border-gray-200">
                                <h4 class="text-md font-medium text-gray-700">Current Portrait Poster</h4>
                                <img :src="props.event.portrait_poster_url" alt="Current Portrait Poster" class="mt-2 max-h-60 w-auto border rounded shadow-sm">
                            </div>

                            <div v-if="props.event.landscape_poster_url" class="mb-4 pb-4 border-b border-gray-200">
                                <h4 class="text-md font-medium text-gray-700">Current Landscape Poster</h4>
                                <img :src="props.event.landscape_poster_url" alt="Current Landscape Poster" class="mt-2 max-h-60 w-auto border rounded shadow-sm">
                            </div>

                            <div v-if="displayedGalleryItems.length > 0" class="mb-4 pb-4 border-b border-gray-200">
                                <h4 class="text-md font-medium text-gray-700">Current Gallery Images</h4>
                                <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                    <div v-for="item in displayedGalleryItems" :key="item.id" class="relative group">
                                        <img :src="item.url" :alt="item.name || 'Gallery Image'" class="rounded-md border shadow-sm object-cover h-32 w-full">
                                        <button
                                            type="button"
                                            @click="removeGalleryItem(item.id)"
                                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 text-xs leading-none opacity-75 group-hover:opacity-100 transition-opacity"
                                            aria-label="Remove image"
                                        >
                                            &times;
                                        </button>
                                        <p class="text-xs text-gray-600 truncate mt-1" :title="item.name">{{ item.name || 'Image' }}</p>
                                    </div>
                                </div>
                            </div>
                             <div v-if="props.event.gallery_items && props.event.gallery_items.length > 0 && displayedGalleryItems.length === 0" class="mb-4 text-sm text-gray-500">
                                All gallery images have been marked for removal.
                            </div>


                            <div>
                                <label for="uploaded_portrait_poster" class="block text-sm font-medium text-gray-700">
                                    {{ props.event?.portrait_poster_url ? 'Replace' : 'Upload' }} Portrait Poster
                                </label>
                                <input type="file" @input="form.uploaded_portrait_poster = ($event.target as HTMLInputElement).files?.[0] || null" id="uploaded_portrait_poster" class="mt-1 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <p class="mt-1 text-sm text-gray-500">Recommended dimensions: e.g., 800x1200px.</p>
                                <div v-if="form.errors.uploaded_portrait_poster" class="text-sm text-red-600 mt-1">{{ form.errors.uploaded_portrait_poster }}</div>
                            </div>

                            <div>
                                <label for="uploaded_landscape_poster" class="block text-sm font-medium text-gray-700">
                                    {{ props.event?.landscape_poster_url ? 'Replace' : 'Upload' }} Landscape Poster
                                </label>
                                <input type="file" @input="form.uploaded_landscape_poster = ($event.target as HTMLInputElement).files?.[0] || null" id="uploaded_landscape_poster" class="mt-1 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <p class="mt-1 text-sm text-gray-500">Recommended dimensions: 1200x675px.</p>
                                <div v-if="form.errors.uploaded_landscape_poster" class="text-sm text-red-600 mt-1">{{ form.errors.uploaded_landscape_poster }}</div>
                            </div>

                            <div>
                                <label for="uploaded_gallery" class="block text-sm font-medium text-gray-700">Add New Gallery Images</label>
                                <input type="file" @input="form.uploaded_gallery = Array.from(($event.target as HTMLInputElement).files || [])" multiple id="uploaded_gallery" class="mt-1 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <p class="mt-1 text-sm text-gray-500">Upload multiple images for the event gallery.</p>
                                <div v-if="form.errors.uploaded_gallery" class="text-sm text-red-600 mt-1">
                                    <span v-if="typeof form.errors.uploaded_gallery === 'string'">{{ form.errors.uploaded_gallery }}</span>
                                    <ul v-else-if="Array.isArray(form.errors.uploaded_gallery)" class="list-disc list-inside">
                                        <li v-for="(error, index) in form.errors.uploaded_gallery" :key="index">{{ error }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="pt-5">
                            <div class="flex justify-end">
                                <Link :href="route('admin.events.index')" type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</Link>
                                <button type="submit" :disabled="form.processing" :class="{ 'opacity-25': form.processing }" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any specific styles if needed */
</style>
