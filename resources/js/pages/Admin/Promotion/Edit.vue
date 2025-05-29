<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onUnmounted } from 'vue';

interface Promotion {
    id: number;
    title: any; // Translatable field
    subtitle: any; // Translatable field
    url: string;
    banner?: string | null; // URL to existing banner
    is_active: boolean;
    starts_at?: string | null;
    ends_at?: string | null;
    sort_order: number;
    media?: Array<{
        id: number;
        collection_name: string;
        file_name: string;
        original_url: string;
    }>;
}

interface Props {
    promotion: Promotion;
}

const props = defineProps<Props>();

console.log(props.promotion);

const form = useForm({
    id: props.promotion.id,
    title: {
        en: props.promotion.title && typeof props.promotion.title === 'object' ? (props.promotion.title.en || '') : '',
        'zh-TW': props.promotion.title && typeof props.promotion.title === 'object' ? (props.promotion.title['zh-TW'] || '') : '',
        'zh-CN': props.promotion.title && typeof props.promotion.title === 'object' ? (props.promotion.title['zh-CN'] || '') : '',
    },
    subtitle: {
        en: props.promotion.subtitle && typeof props.promotion.subtitle === 'object' ? (props.promotion.subtitle.en || '') : '',
        'zh-TW': props.promotion.subtitle && typeof props.promotion.subtitle === 'object' ? (props.promotion.subtitle['zh-TW'] || '') : '',
        'zh-CN': props.promotion.subtitle && typeof props.promotion.subtitle === 'object' ? (props.promotion.subtitle['zh-CN'] || '') : '',
    },
    url: props.promotion.url,
    is_active: props.promotion.is_active,
    starts_at: props.promotion.starts_at || '',
    ends_at: props.promotion.ends_at || '',
    sort_order: props.promotion.sort_order,
    uploaded_banner_image: null as File | null,
    remove_banner_image: false as boolean,
});

// Computed property to get the current banner URL
const currentBannerUrl = computed(() => {
    if (form.remove_banner_image) {
        return null;
    }
    // Use the banner URL passed from the controller if available (from PromotionController)
    if (props.promotion.banner) {
        return props.promotion.banner;
    }
    // Fallback to media collection if direct banner URL is not present (less likely with current controller setup)
    if (props.promotion.media) {
        const bannerMedia = props.promotion.media.find(media => media.collection_name === 'banner');
        return bannerMedia?.original_url || null;
    }
    return null;
});

const previewUrl = ref<string | null>(null);

const handleBannerUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.uploaded_banner_image = target.files[0];
        form.remove_banner_image = false; // If a new file is uploaded, don't remove

        const file = target.files[0];
        if (file.type.startsWith('image/')) {
            previewUrl.value = URL.createObjectURL(file);
        }
    } else {
        form.uploaded_banner_image = null;
        previewUrl.value = null;
    }
};

const removeCurrentBanner = () => {
    form.remove_banner_image = true;
    form.uploaded_banner_image = null;
    previewUrl.value = null;
};

const submit = () => {
    form.transform((data) => {
        return {
            ...data,
            _method: 'PUT',
            title: {
                en: data.title.en || '',
                'zh-TW': data.title['zh-TW'] || '',
                'zh-CN': data.title['zh-CN'] || ''
            },
            subtitle: {
                en: data.subtitle.en || '',
                'zh-TW': data.subtitle['zh-TW'] || '',
                'zh-CN': data.subtitle['zh-CN'] || ''
            },
            remove_banner_image: Boolean(data.remove_banner_image)
        };
    }).post(route('admin.promotions.update', props.promotion.id));
};

// Helper to get a specific translation
const getTranslation = (translations: any, locale: string, fallbackLocale: string = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

onUnmounted(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

</script>

<template>
    <Head :title="`Edit Promotion: ${getTranslation(props.promotion.title, 'en')}`" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <!-- Translatable Title Inputs -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Promotion Title (Translatable)</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="title_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.title.en" id="title_en" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="title_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.title['zh-TW']" id="title_zh_TW" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="title_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.title['zh-CN']" id="title_zh_CN" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.title" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.title }}</div>
                                    <div v-if="(form.errors as any)['title.en']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['title.en'] }}</div>
                                    <div v-if="(form.errors as any)['title.zh-TW']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['title.zh-TW'] }}</div>
                                    <div v-if="(form.errors as any)['title.zh-CN']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['title.zh-CN'] }}</div>
                                </fieldset>

                                <!-- Translatable Subtitle Inputs -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Promotion Subtitle (Translatable)</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="subtitle_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.subtitle.en" id="subtitle_en" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="subtitle_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.subtitle['zh-TW']" id="subtitle_zh_TW" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="subtitle_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.subtitle['zh-CN']" id="subtitle_zh_CN" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.subtitle" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.subtitle }}</div>
                                    <div v-if="(form.errors as any)['subtitle.en']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['subtitle.en'] }}</div>
                                    <div v-if="(form.errors as any)['subtitle.zh-TW']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['subtitle.zh-TW'] }}</div>
                                    <div v-if="(form.errors as any)['subtitle.zh-CN']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['subtitle.zh-CN'] }}</div>
                                </fieldset>

                                <!-- URL -->
                                <div>
                                    <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL *</label>
                                    <input type="url" v-model="form.url" id="url" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required />
                                    <div v-if="form.errors.url" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.url }}</div>
                                </div>

                                <!-- Banner Image Upload -->
                                <div>
                                    <label for="uploaded_banner_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Banner Image</label>

                                    <!-- Current Banner Display -->
                                    <div v-if="currentBannerUrl && !previewUrl" class="mt-2 mb-4">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Current Banner:</p>
                                        <div class="flex items-center space-x-4">
                                            <div class="relative group">
                                                <img :src="currentBannerUrl" alt="Current promotion banner" class="w-48 h-auto object-cover rounded-lg border border-gray-300 dark:border-gray-600" />
                                                <button
                                                    type="button"
                                                    @click="removeCurrentBanner"
                                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 text-xs leading-none opacity-75 group-hover:opacity-100 transition-opacity"
                                                    aria-label="Remove banner"
                                                    title="Remove current banner"
                                                >
                                                    &times;
                                                </button>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <p>Current banner will be replaced if you upload a new one.</p>
                                                <button
                                                    type="button"
                                                    @click="removeCurrentBanner"
                                                    class="text-red-600 hover:text-red-800 text-xs mt-1 underline"
                                                >
                                                    Remove current banner
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Banner Removal Notice -->
                                    <div v-if="form.remove_banner_image && !previewUrl && !currentBannerUrl" class="mt-2 mb-4">
                                         <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-3">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                                        Current banner will be removed when you save. Upload a new one below if needed.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Preview of New Banner -->
                                    <div v-if="previewUrl" class="mt-2 mb-4">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">New Banner Preview:</p>
                                        <img :src="previewUrl" alt="New promotion banner preview" class="w-48 h-auto object-cover rounded-lg border border-gray-300 dark:border-gray-600" />
                                    </div>

                                    <input type="file" @change="handleBannerUpload" id="uploaded_banner_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300 dark:hover:file:bg-indigo-800" />
                                    <div v-if="form.errors.uploaded_banner_image" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.uploaded_banner_image }}</div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span v-if="currentBannerUrl && !form.remove_banner_image">Upload a new banner to replace the existing one.</span>
                                        <span v-else>Upload a banner image (JPEG, PNG, WebP - max 10MB).</span>
                                    </p>
                                </div>

                                <!-- Date Range -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="starts_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                                        <input type="datetime-local" v-model="form.starts_at" id="starts_at" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        <div v-if="form.errors.starts_at" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.starts_at }}</div>
                                    </div>
                                    <div>
                                        <label for="ends_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                                        <input type="datetime-local" v-model="form.ends_at" id="ends_at" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        <div v-if="form.errors.ends_at" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.ends_at }}</div>
                                    </div>
                                </div>

                                <!-- Sort Order -->
                                <div>
                                    <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sort Order</label>
                                    <input type="number" v-model="form.sort_order" id="sort_order" min="0" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                    <div v-if="form.errors.sort_order" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.sort_order }}</div>
                                </div>

                                <!-- Active Status -->
                                <div class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" id="is_active" class="h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded" />
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Active</label>
                                </div>
                                <div v-if="form.errors.is_active" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.is_active }}</div>
                            </div>

                            <div class="mt-8 flex justify-end">
                                <Link :href="route('admin.promotions.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 mr-4 py-2 px-4 rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">Cancel</Link>
                                <button type="submit" :disabled="form.processing"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                        :class="{ 'opacity-25': form.processing }">
                                    Update Promotion
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
