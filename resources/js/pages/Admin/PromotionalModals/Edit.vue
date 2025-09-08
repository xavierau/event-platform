<template>
    <Head :title="`Edit: ${getTranslation(promotionalModal.title, currentLocale)}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800">
                        <PageHeader 
                            :title="`Edit: ${getTranslation(promotionalModal.title, currentLocale)}`" 
                            subtitle="Update promotional modal settings and content"
                        >
                            <template #actions>
                                <Link :href="route('admin.promotional-modals.analytics', promotionalModal.id)" 
                                      class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition">
                                    View Analytics
                                </Link>
                                <Link :href="route('admin.promotional-modals.index')" 
                                      class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Back to List
                                </Link>
                            </template>
                        </PageHeader>

                        <!-- Analytics Summary -->
                        <div class="mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 p-6 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Performance Summary</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ promotionalModal.impressions_count.toLocaleString() }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Impressions</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ promotionalModal.clicks_count.toLocaleString() }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Clicks</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ promotionalModal.conversion_rate }}%</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Conversion Rate</div>
                                </div>
                                <div class="text-center">
                                    <div :class="statusClass(promotionalModal.is_active)" class="text-2xl font-bold">
                                        {{ promotionalModal.is_active ? 'Active' : 'Inactive' }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Status</div>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Basic Information -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Type -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type *</label>
                                        <select v-model="form.type" 
                                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option value="modal">Modal</option>
                                            <option value="banner">Banner</option>
                                        </select>
                                        <div v-if="form.errors.type" class="text-red-600 text-sm mt-1">{{ form.errors.type }}</div>
                                    </div>

                                    <!-- Status -->
                                    <div>
                                        <label class="flex items-center">
                                            <input v-model="form.is_active" 
                                                   type="checkbox" 
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Title (Multilingual) -->
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title *</label>
                                    <div class="space-y-3">
                                        <div v-for="locale in locales" :key="locale.code">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ locale.name }}</label>
                                            <input v-model="form.title[locale.code]" 
                                                   type="text" 
                                                   :placeholder="`Enter title in ${locale.name}...`"
                                                   class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        </div>
                                    </div>
                                    <div v-if="form.errors.title" class="text-red-600 text-sm mt-1">{{ form.errors.title }}</div>
                                </div>

                                <!-- Content (Multilingual) -->
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content *</label>
                                    <div class="space-y-4">
                                        <div v-for="locale in locales" :key="locale.code">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ locale.name }}</label>
                                            <textarea v-model="form.content[locale.code]" 
                                                      rows="4" 
                                                      :placeholder="`Enter content in ${locale.name}...`"
                                                      class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"></textarea>
                                        </div>
                                    </div>
                                    <div v-if="form.errors.content" class="text-red-600 text-sm mt-1">{{ form.errors.content }}</div>
                                </div>
                            </div>

                            <!-- Call to Action -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Call to Action</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button Text</label>
                                        <input v-model="form.button_text" 
                                               type="text" 
                                               placeholder="Learn More"
                                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <div v-if="form.errors.button_text" class="text-red-600 text-sm mt-1">{{ form.errors.button_text }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button URL</label>
                                        <input v-model="form.button_url" 
                                               type="url" 
                                               placeholder="https://example.com"
                                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <div v-if="form.errors.button_url" class="text-red-600 text-sm mt-1">{{ form.errors.button_url }}</div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="flex items-center">
                                        <input v-model="form.is_dismissible" 
                                               type="checkbox" 
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Allow users to dismiss this modal</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Display Rules -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Display Rules</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Pages -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Display on Pages</label>
                                        <select v-model="form.pages" 
                                                multiple 
                                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                size="5">
                                            <option value="home">Home Page</option>
                                            <option value="events">Events List</option>
                                            <option value="event-detail">Event Detail</option>
                                            <option value="my-bookings">My Bookings</option>
                                            <option value="my-wallet">My Wallet</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Leave empty to show on all pages</p>
                                    </div>

                                    <!-- Display Frequency -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Display Frequency</label>
                                        <select v-model="form.display_frequency" 
                                                class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <option value="once">Once per user</option>
                                            <option value="daily">Once per day</option>
                                            <option value="weekly">Once per week</option>
                                            <option value="always">Every page visit</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Priority -->
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Priority (0-100)</label>
                                    <input v-model.number="form.priority" 
                                           type="number" 
                                           min="0" 
                                           max="100" 
                                           placeholder="50"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <p class="text-xs text-gray-500 mt-1">Higher priority modals are shown first</p>
                                </div>
                            </div>

                            <!-- Schedule -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Schedule (Optional)</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                                        <input v-model="form.start_at" 
                                               type="datetime-local" 
                                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                                        <input v-model="form.end_at" 
                                               type="datetime-local" 
                                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </div>
                                </div>
                            </div>

                            <!-- Current Images Display -->
                            <div v-if="promotionalModal.banner_image_url || promotionalModal.background_image_url" class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Current Images</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div v-if="promotionalModal.banner_image_url">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Banner Image</label>
                                        <img :src="promotionalModal.banner_image_url" alt="Banner" class="w-full h-32 object-cover rounded">
                                    </div>
                                    <div v-if="promotionalModal.background_image_url">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Background Image</label>
                                        <img :src="promotionalModal.background_image_url" alt="Background" class="w-full h-32 object-cover rounded">
                                    </div>
                                </div>
                            </div>

                            <!-- Image Upload -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Update Images (Optional)</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Banner Image</label>
                                        <input @change="handleBannerImageUpload" 
                                               type="file" 
                                               accept="image/*"
                                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <p class="text-xs text-gray-500 mt-1">Recommended size: 1200x300px</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Image</label>
                                        <input @change="handleBackgroundImageUpload" 
                                               type="file" 
                                               accept="image/*"
                                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <p class="text-xs text-gray-500 mt-1">Recommended size: 1920x1080px</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex items-center justify-end space-x-4 pt-6">
                                <Link :href="route('admin.promotional-modals.index')" 
                                      class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Cancel
                                </Link>
                                <button type="submit" 
                                        :disabled="form.processing"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    <span v-if="form.processing">Updating...</span>
                                    <span v-else>Update Modal</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { getTranslation } from '@/Utils/i18n';

interface PromotionalModal {
    id: number;
    title: Record<string, string>;
    content: Record<string, string>;
    type: 'modal' | 'banner';
    button_text: string | null;
    button_url: string | null;
    is_dismissible: boolean;
    pages: string[] | null;
    display_frequency: 'once' | 'daily' | 'weekly' | 'always';
    priority: number;
    start_at: string | null;
    end_at: string | null;
    is_active: boolean;
    impressions_count: number;
    clicks_count: number;
    conversion_rate: number;
    banner_image_url: string | null;
    background_image_url: string | null;
}

interface PromotionalModalForm {
    type: 'modal' | 'banner';
    title: Record<string, string>;
    content: Record<string, string>;
    button_text: string;
    button_url: string;
    is_dismissible: boolean;
    pages: string[];
    display_frequency: 'once' | 'daily' | 'weekly' | 'always';
    priority: number;
    start_at: string;
    end_at: string;
    is_active: boolean;
    uploaded_banner_image: File | null;
    uploaded_background_image: File | null;
}

const props = defineProps<{
    promotionalModal: PromotionalModal;
    availableLocales?: { code: string; name: string; }[];
}>();

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

const locales = props.availableLocales || [
    { code: 'en', name: 'English' },
    { code: 'zh-TW', name: 'Traditional Chinese' },
    { code: 'zh-CN', name: 'Simplified Chinese' }
];

const formatDateForInput = (date: string | null): string => {
    if (!date) return '';
    return new Date(date).toISOString().slice(0, 16);
};

const form = useForm<PromotionalModalForm>({
    type: 'modal',
    title: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    content: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {}),
    button_text: '',
    button_url: '',
    is_dismissible: true,
    pages: [],
    display_frequency: 'once',
    priority: 50,
    start_at: '',
    end_at: '',
    is_active: true,
    uploaded_banner_image: null,
    uploaded_background_image: null,
});

// Use watchEffect to populate data when props change (following Events pattern)
watchEffect(() => {
    if (props.promotionalModal) {
        form.defaults({
            ...form.data,
            ...props.promotionalModal,
            start_at: formatDateForInput(props.promotionalModal.start_at),
            end_at: formatDateForInput(props.promotionalModal.end_at),
        });
        form.reset();
    }
});

const statusClass = (isActive: boolean) => {
    return isActive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
};

const handleBannerImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.uploaded_banner_image = target.files[0];
    }
};

const handleBackgroundImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.uploaded_background_image = target.files[0];
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        // Ensure title and content maintain their multilingual array structure
        title: typeof data.title === 'object' ? data.title : {
            en: data.title || '',
            'zh-TW': '',
            'zh-CN': ''
        },
        content: typeof data.content === 'object' ? data.content : {
            en: data.content || '',
            'zh-TW': '',
            'zh-CN': ''
        },
    })).put(route('admin.promotional-modals.update', props.promotionalModal.id), {
        onSuccess: () => {
            // Redirect will be handled by the controller
        }
    });
};
</script>