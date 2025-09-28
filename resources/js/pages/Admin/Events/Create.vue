<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue'; // Corrected casing
import RichTextEditor from '@/components/Form/RichTextEditor.vue'; // Corrected casing
import { ref, watch } from 'vue';

interface SelectOption {
    value: string | number;
    label: string;
}

interface BreadcrumbItem {
  title: string;
  url?: string;
  disabled?: boolean;
}

// FormData interface for Create.vue
interface CreateEventFormData {
    organizer_id: number | null;
    category_id: number | null;
    name: Record<string, string>;
    slug: Record<string, string>;
    description: Record<string, string>;
    short_summary: Record<string, string>;
    status: string; // 'event_status' in Edit.vue, 'status' here for now.
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
    comments_enabled: boolean;
    comments_require_approval: boolean;
    comment_config: string;
    action_type: string;
    redirect_url: string;
    visible_to_membership_levels: number[];
    [key: string]: any; // Index signature for FormDataType compatibility
}

interface CreateEventProps {
    categories?: SelectOption[];
    tags?: SelectOption[];
    organizers?: SelectOption[];
    eventStatuses?: SelectOption[];
    visibilities?: SelectOption[];
    venues?: SelectOption[]; // Simplified for now, Edit.vue has VenueSelectItem
    membershipLevels?: SelectOption[];
    pageTitle?: string;
    breadcrumbs?: BreadcrumbItem[];
}

const props = defineProps<CreateEventProps>();

console.log(props.venues);

const locales = [
    { code: 'en', name: 'English' },
    { code: 'zh-TW', name: 'Traditional Chinese' },
    { code: 'zh-CN', name: 'Simplified Chinese' }
];

const form = useForm<CreateEventFormData>({
    organizer_id: null,
    category_id: null,
    name: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    slug: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    short_summary: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    status: 'draft',
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
    comments_enabled: true,
    comments_require_approval: false,
    comment_config: 'enabled',
    action_type: 'purchase_ticket',
    redirect_url: '',
    visible_to_membership_levels: [],
});

const currentTab = ref('coreDetails'); // Default active tab

const tabs = [
    { id: 'coreDetails', label: 'Core Details' },
    { id: 'translatableContent', label: 'Translatable Content' },
    { id: 'contactLinks', label: 'Contact & Links' },
    { id: 'tags', label: 'Tags' },
    { id: 'media', label: 'Media' },
    { id: 'comments', label: 'Comments' },
    { id: 'membership', label: 'Membership & Access' },
];

// Sync comment_config based on the checkbox values
watch([() => form.comments_enabled, () => form.comments_require_approval], () => {
    if (!form.comments_enabled) {
        form.comment_config = 'disabled';
    } else if (form.comments_require_approval) {
        form.comment_config = 'moderated';
    } else {
        form.comment_config = 'enabled';
    }
});

const submit = () => {
    form.post(route('admin.events.store'), {
        // onError, onSuccess handlers
    });
};

const tFieldName = (field: string, locale: string): string => `${field}.${locale}`;

</script>

<template>

    <Head :title="props.pageTitle || 'Create New Event'" />

    <AppLayout :page-title="props.pageTitle" :breadcrumbs="props.breadcrumbs">
        <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <!-- Tab Navigation -->
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
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
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm rounded-md">
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
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm rounded-md">
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
                                <label for="status" class="block text-sm font-medium text-gray-700">Event
                                    Status</label>
                                <select id="status" v-model="form.status"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option v-for="s_option in props.eventStatuses" :key="s_option.value"
                                        :value="s_option.value">
                                        {{ s_option.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.status" class="text-sm text-red-600 mt-1">{{
                                    form.errors.status }}</div>
                            </div>

                            <!-- Visibility -->
                            <div>
                                <label for="visibility"
                                    class="block text-sm font-medium text-gray-700">Visibility</label>
                                <select id="visibility" v-model="form.visibility"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm rounded-md">
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
                                        class="focus:ring-2 focus:ring-3 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_featured" class="font-medium text-gray-700">Featured Event</label>
                                    <p class="text-gray-500">Highlight this event on public pages.</p>
                                </div>
                                <div v-if="form.errors.is_featured"
                                    class="absolute bottom-[-20px] text-sm text-red-600">{{ form.errors.is_featured }}
                                </div>
                            </div>

                            <!-- Action Type -->
                            <div>
                                <label for="action_type" class="block text-sm font-medium text-gray-700">Action Type</label>
                                <select id="action_type" v-model="form.action_type"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option value="purchase_ticket">Purchase Ticket</option>
                                    <option value="show_member_qr">Show Member QR Code</option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500">
                                    Choose what action button appears on the event page for eligible users.
                                </p>
                                <div v-if="form.errors.action_type" class="text-sm text-red-600 mt-1">{{ form.errors.action_type }}</div>
                            </div>

                            <!-- Redirect URL -->
                            <div v-if="form.action_type === 'purchase_ticket'">
                                <label for="redirect_url" class="block text-sm font-medium text-gray-700">External Redirect URL (Optional)</label>
                                <input
                                    id="redirect_url"
                                    v-model="form.redirect_url"
                                    type="url"
                                    placeholder="https://external-ticketing.example.com/event/123"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                <p class="mt-1 text-sm text-gray-500">
                                    When set, clicking the purchase button will redirect users to this external URL instead of opening the ticket purchase modal.
                                    Leave empty to use the built-in ticket purchase system.
                                </p>
                                <div v-if="form.errors.redirect_url" class="text-sm text-red-600 mt-1">{{ form.errors.redirect_url }}</div>
                            </div>
                        </div>

                        <!-- Section: Membership & Access -->
                        <div v-if="currentTab === 'membership'" class="space-y-6">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Membership & Access Control</h3>
                                <p class="mt-1 text-sm text-gray-500">Control who can see and access this event.</p>
                            </div>

                            <!-- Membership Level Restriction -->
                            <div>
                                <label for="visible_to_membership_levels" class="block text-sm font-medium text-gray-700">Visible to Membership Levels</label>
                                <select id="visible_to_membership_levels" v-model="form.visible_to_membership_levels" multiple
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm rounded-md h-32">
                                    <option v-for="level in props.membershipLevels" :key="level.value" :value="level.value">
                                        {{ level.label }}
                                    </option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500">
                                    Leave empty to make the event visible to all users (public). 
                                    Select specific membership levels to restrict access to those members only.
                                </p>
                                <div v-if="form.errors.visible_to_membership_levels" class="text-sm text-red-600 mt-1">
                                    {{ form.errors.visible_to_membership_levels }}
                                </div>
                            </div>

                            <!-- Membership Access Preview -->
                            <div v-if="form.visible_to_membership_levels && form.visible_to_membership_levels.length > 0" 
                                 class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-blue-900 mb-2">Access Summary</h4>
                                <p class="text-sm text-blue-700">
                                    This event will be visible only to users with the following membership levels:
                                </p>
                                <ul class="mt-2 text-sm text-blue-700 list-disc list-inside">
                                    <li v-for="levelId in form.visible_to_membership_levels" :key="levelId">
                                        {{ props.membershipLevels?.find(l => l.value === levelId)?.label || 'Unknown Level' }}
                                    </li>
                                </ul>
                            </div>

                            <div v-else class="bg-green-50 border border-green-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-green-900 mb-2">Public Event</h4>
                                <p class="text-sm text-green-700">
                                    This event will be visible to all users, including non-members.
                                </p>
                            </div>
                        </div>

                        <!-- Section: Translatable Content -->
                        <div v-if="currentTab === 'translatableContent'" class="space-y-6">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Translatable Content</h3>
                                <p class="mt-1 text-sm text-gray-500">Provide details in all supported languages.</p>
                            </div>

                            <!-- Translatable Name -->
                            <div class="border border-gray-200 rounded-md p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Event Name</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div v-for="locale in locales" :key="locale.code">
                                        <label :for="tFieldName('name', locale.code)" class="block text-xs font-medium text-gray-500">
                                            {{ locale.name }} <span v-if="locale.code === 'en'" class="text-red-500">*</span>
                                        </label>
                                        <input type="text" :id="tFieldName('name', locale.code)"
                                            v-model="form.name[locale.code]"
                                            class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500" />
                                        <div v-if="form.errors[tFieldName('name', locale.code)]"
                                            class="text-sm text-red-600 mt-1">{{ form.errors[tFieldName('name',
                                            locale.code)] }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Translatable Slug -->
                            <div class="border border-gray-200 rounded-md p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Event Slug <span
                                        class="text-xs text-gray-500">(auto-generated if blank for default locale)</span></label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div v-for="locale in locales" :key="locale.code">
                                        <label :for="tFieldName('slug', locale.code)" class="block text-xs font-medium text-gray-500">
                                            {{ locale.name }} <span v-if="locale.code === 'en'" class="text-red-500">*</span>
                                        </label>
                                        <input type="text" :id="tFieldName('slug', locale.code)"
                                            v-model="form.slug[locale.code]"
                                            class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500" />
                                        <div v-if="form.errors[tFieldName('slug', locale.code)]"
                                            class="text-sm text-red-600 mt-1">{{ form.errors[tFieldName('slug',
                                            locale.code)] }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Translatable Description -->
                            <div class="border border-gray-200 rounded-md p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Event Description</label>
                                <div v-for="locale in locales" :key="locale.code" class="mb-4">
                                    <label :for="tFieldName('description', locale.code)" class="block text-sm font-medium text-gray-500 mb-1">
                                        {{ locale.name }} <span v-if="locale.code === 'en'" class="text-red-500">*</span>
                                    </label>
                                    <RichTextEditor
                                        :id="tFieldName('description', locale.code)"
                                        v-model="form.description[locale.code]"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500"
                                    />
                                    <div v-if="form.errors[tFieldName('description', locale.code)]"
                                        class="text-sm text-red-600 mt-1">{{ form.errors[tFieldName('description',
                                        locale.code)] }}</div>
                                </div>
                            </div>

                            <!-- Translatable Short Summary -->
                            <div class="border border-gray-200 rounded-md p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Short Summary</label>
                                <div v-for="locale in locales" :key="locale.code" class="mb-4">
                                    <label :for="tFieldName('short_summary', locale.code)" class="block text-sm font-medium text-gray-500 mb-1">
                                        {{ locale.name }} <span v-if="locale.code === 'en'" class="text-red-500">*</span>
                                    </label>
                                    <textarea :id="tFieldName('short_summary', locale.code)"
                                        v-model="form.short_summary[locale.code]" rows="3"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500"></textarea>
                                    <div v-if="form.errors[tFieldName('short_summary', locale.code)]"
                                        class="text-sm text-red-600 mt-1">{{ form.errors[tFieldName('short_summary',
                                        locale.code)] }}</div>
                                </div>
                            </div>

                            <!-- Translatable Cancellation Policy -->
                            <div class="border border-gray-200 rounded-md p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cancellation Policy</label>
                                <div v-for="locale in locales" :key="locale.code" class="mb-4">
                                    <label :for="tFieldName('cancellation_policy', locale.code)"
                                        class="block text-sm font-medium text-gray-500 mb-1">{{ locale.name }}</label>
                                    <RichTextEditor
                                        :id="tFieldName('cancellation_policy', locale.code)"
                                        v-model="form.cancellation_policy[locale.code]"
                                        class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500"
                                    />
                                    <div v-if="form.errors[tFieldName('cancellation_policy', locale.code)]"
                                        class="text-sm text-red-600 mt-1">{{ form.errors[tFieldName('cancellation_policy',
                                        locale.code)] }}</div>
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
                                <input type="email" v-model="form.contact_email" id="contact_email" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500" />
                                <div v-if="form.errors.contact_email" class="text-sm text-red-600 mt-1">{{ form.errors.contact_email }}</div>
                            </div>

                            <div>
                                <label for="contact_phone" class="block text-sm font-medium text-gray-700">Contact Phone</label>
                                <input type="text" v-model="form.contact_phone" id="contact_phone" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500" />
                                <div v-if="form.errors.contact_phone" class="text-sm text-red-600 mt-1">{{ form.errors.contact_phone }}</div>
                            </div>

                            <div>
                                <label for="website_url" class="block text-sm font-medium text-gray-700">Website URL</label>
                                <input type="url" v-model="form.website_url" id="website_url" placeholder="https://example.com" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500" />
                                <div v-if="form.errors.website_url" class="text-sm text-red-600 mt-1">{{ form.errors.website_url }}</div>
                            </div>

                            <div>
                                <label for="youtube_video_id" class="block text-sm font-medium text-gray-700">YouTube Video ID</label>
                                <input type="text" v-model="form.youtube_video_id" id="youtube_video_id" placeholder="dQw4w9WgXcQ" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-2 focus:ring-3 focus:border-indigo-500" />
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
                            <!-- Tags (Multi-select) -->
                            <div>
                                <label for="tag_ids" class="block text-sm font-medium text-gray-700">Assign Tags</label>
                                <!-- Consider a more user-friendly multi-select component (e.g., Vue Multiselect, Tom Select) -->
                                <select id="tag_ids" v-model="form.tag_ids" multiple class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm rounded-md h-40">
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
                                <p class="mt-1 text-sm text-gray-500">Upload posters and gallery images for the event.</p>
                            </div>

                            <!-- Portrait Poster -->
                            <div>
                                <label for="uploaded_portrait_poster" class="block text-sm font-medium text-gray-700">Portrait Poster</label>
                                <input type="file" @input="form.uploaded_portrait_poster = ($event.target as HTMLInputElement).files?.[0] || null" id="uploaded_portrait_poster" class="mt-1 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <p class="mt-1 text-sm text-gray-500">Recommended dimensions: e.g., 800x1200px.</p>
                                <div v-if="form.errors.uploaded_portrait_poster" class="text-sm text-red-600 mt-1">{{ form.errors.uploaded_portrait_poster }}</div>
                            </div>

                            <!-- Landscape Poster -->
                            <div>
                                <label for="uploaded_landscape_poster" class="block text-sm font-medium text-gray-700">Landscape Poster</label>
                                <input type="file" @input="form.uploaded_landscape_poster = ($event.target as HTMLInputElement).files?.[0] || null" id="uploaded_landscape_poster" class="mt-1 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <p class="mt-1 text-sm text-gray-500">Recommended dimensions: 1200x675px.</p>
                                <div v-if="form.errors.uploaded_landscape_poster" class="text-sm text-red-600 mt-1">{{ form.errors.uploaded_landscape_poster }}</div>
                            </div>

                            <!-- Gallery -->
                            <div>
                                <label for="uploaded_gallery" class="block text-sm font-medium text-gray-700">Gallery Images</label>
                                <input type="file" @input="form.uploaded_gallery = Array.from(($event.target as HTMLInputElement).files || [])" multiple id="uploaded_gallery" class="mt-1 block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <p class="mt-1 text-sm text-gray-500">Upload multiple images for the event gallery.</p>
                                <div v-if="form.errors.uploaded_gallery" class="text-sm text-red-600 mt-1">
                                    <span v-if="typeof form.errors.uploaded_gallery === 'string'">{{ form.errors.uploaded_gallery }}</span>
                                    <ul v-else class="list-disc list-inside">
                                        <li v-for="(error, index) in form.errors.uploaded_gallery" :key="index">{{ error }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Comment Settings -->
                        <div v-if="currentTab === 'comments'" class="space-y-6">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Comment Settings</h3>
                                <p class="mt-1 text-sm text-gray-500">Configure how comments work for this event.</p>
                            </div>

                            <!-- Comments Enabled -->
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="comments_enabled" v-model="form.comments_enabled" type="checkbox"
                                        class="focus:ring-2 focus:ring-3 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="comments_enabled" class="font-medium text-gray-700">Enable Comments</label>
                                    <p class="text-gray-500">Allow users to comment on this event.</p>
                                </div>
                                <div v-if="form.errors.comments_enabled"
                                    class="absolute bottom-[-20px] text-sm text-red-600">{{ form.errors.comments_enabled }}
                                </div>
                            </div>

                            <!-- Comments Require Approval (only show if comments are enabled) -->
                            <div v-if="form.comments_enabled" class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="comments_require_approval" v-model="form.comments_require_approval" type="checkbox"
                                        class="focus:ring-2 focus:ring-3 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="comments_require_approval" class="font-medium text-gray-700">Require Moderation</label>
                                    <p class="text-gray-500">Comments must be approved before they are visible to other users.</p>
                                </div>
                                <div v-if="form.errors.comments_require_approval"
                                    class="absolute bottom-[-20px] text-sm text-red-600">{{ form.errors.comments_require_approval }}
                                </div>
                            </div>

                            <!-- Comment Config (Legacy - kept for backward compatibility but hidden) -->
                            <input type="hidden" v-model="form.comment_config" />
                        </div>

                        <div class="pt-5">
                            <div class="flex justify-end">
                                <Link
                                    :href="route('admin.events.index')"
                                    as="button"
                                    class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-3"
                                >
                                    Cancel
                                </Link>
                                <button type="submit" :disabled="form.processing"
                                    class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-3">
                                    Create Event
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
