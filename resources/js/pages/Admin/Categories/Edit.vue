<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, onUnmounted } from 'vue';

interface Category {
    id: number;
    name: any; // Translatable field (object with locale keys)
    slug: string;
    parent_id?: number | null;
    is_active: boolean;
}

interface CategoryData {
    id?: number | null;
    name: any; // Translatable field (object with locale keys)
    slug: string;
    parent_id?: number | null;
    is_active: boolean;
    uploaded_icon?: File | null;
    // Add media information for existing icons
    media?: Array<{
        id: number;
        collection_name: string;
        file_name: string;
        mime_type: string;
        size: number;
        original_url: string;
        preview_url?: string;
    }>;
}

interface Props {
    category: CategoryData; // CategoryData DTO passed from controller
    categoriesForSelect: Pick<Category, 'id' | 'name'>[]; // Categories for parent dropdown
}

const props = defineProps<Props>();

// Debug: Log the category data received from backend
console.log('Category data from backend:', props.category);

const form = useForm({
    id: props.category.id,
    name: JSON.parse(JSON.stringify(props.category.name)), // Deep copy for objects/arrays
    slug: props.category.slug,
    parent_id: props.category.parent_id,
    is_active: props.category.is_active,
    uploaded_icon: null as File | null,
    remove_icon: false as boolean, // Flag to indicate if current icon should be removed
});

// Computed property to get the current icon URL
const currentIconUrl = computed(() => {
    // Don't show current icon if it's marked for removal
    if (form.remove_icon) {
        return null;
    }

    if (props.category.media) {
        const iconMedia = props.category.media.find(media => media.collection_name === 'icon');
        return iconMedia?.original_url || null;
    }
    return null;
});

// Function to remove the current icon
const removeCurrentIcon = () => {
    form.remove_icon = true;
    // Also clear any uploaded icon since we're removing the current one
    form.uploaded_icon = null;
    previewUrl.value = null;
};

// Preview URL for newly selected file
const previewUrl = ref<string | null>(null);

const handleIconUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.uploaded_icon = target.files[0];

        // Reset remove flag since we're uploading a new icon
        form.remove_icon = false;

        // Create preview URL
        const file = target.files[0];
        if (file.type.startsWith('image/')) {
            previewUrl.value = URL.createObjectURL(file);
        }
    } else {
        form.uploaded_icon = null;
        previewUrl.value = null;
    }
};

const submit = () => {
    if (props.category.id) {
        // Debug: Log the form data being sent
        console.log('Form data being submitted:', form.data());

                // Transform the data to ensure proper structure for Laravel validation
        form.transform((data) => {
            const transformedData = {
                ...data,
                _method: 'PUT',
                // Ensure name object is properly structured
                name: {
                    en: data.name.en || '',
                    'zh-TW': data.name['zh-TW'] || '',
                    'zh-CN': data.name['zh-CN'] || ''
                },
                // Ensure remove_icon is properly cast to boolean
                remove_icon: Boolean(data.remove_icon)
            };

            console.log('Transformed data:', transformedData);
            return transformedData;
        }).post(route('admin.categories.update', props.category.id), {
            // onSuccess: () => { /* Notification or redirect */ },
        });
    }
};

// Helper to get a specific translation
// TODO: Make this a global helper or composable
const getTranslation = (translations: any, locale: string, fallbackLocale: string = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

// Cleanup preview URL on unmount to prevent memory leaks
onUnmounted(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

</script>

<template>
    <Head :title="`Edit Category: ${getTranslation(props.category.name, 'en')}`" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Category Name (Translatable)</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="name_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.name.en" id="name_en" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="name_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.name['zh-TW']" id="name_zh_TW" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="name_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.name['zh-CN']" id="name_zh_CN" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.name" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.name }}</div>
                                    <div v-if="(form.errors as any)['name.en']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['name.en'] }}</div>
                                    <div v-if="(form.errors as any)['name.zh-TW']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['name.zh-TW'] }}</div>
                                    <div v-if="(form.errors as any)['name.zh-CN']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ (form.errors as any)['name.zh-CN'] }}</div>
                                </fieldset>

                                <div>
                                    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Slug</label>
                                    <input type="text" v-model="form.slug" id="slug" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                    <div v-if="form.errors.slug" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.slug }}</div>
                                </div>

                                <div>
                                    <label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Parent Category</label>
                                    <select v-model="form.parent_id" id="parent_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        <option :value="null">-- No Parent --</option>
                                        <option v-for="cat in props.categoriesForSelect" :key="cat.id" :value="cat.id">
                                            {{ getTranslation(cat.name, 'en') }} <!-- Adjust locale -->
                                        </option>
                                    </select>
                                    <div v-if="form.errors.parent_id" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.parent_id }}</div>
                                </div>

                                <div>
                                    <label for="uploaded_icon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category Icon</label>

                                                        <!-- Current Icon Display -->
                    <div v-if="currentIconUrl && !previewUrl" class="mt-2 mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Current Icon:</p>
                        <div class="flex items-center space-x-4">
                            <div class="relative group">
                                <img :src="currentIconUrl" alt="Current category icon" class="w-16 h-16 object-cover rounded-lg border border-gray-300 dark:border-gray-600" />
                                <button
                                    type="button"
                                    @click="removeCurrentIcon"
                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 text-xs leading-none opacity-75 group-hover:opacity-100 transition-opacity"
                                    aria-label="Remove icon"
                                    title="Remove current icon"
                                >
                                    &times;
                                </button>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <p>Current icon will be replaced when you upload a new one</p>
                                <button
                                    type="button"
                                    @click="removeCurrentIcon"
                                    class="text-red-600 hover:text-red-800 text-xs mt-1 underline"
                                >
                                    Remove current icon
                                </button>
                            </div>
                        </div>
                    </div>

                                                        <!-- Icon Removal Notice -->
                    <div v-if="form.remove_icon && !previewUrl && !currentIconUrl" class="mt-2 mb-4">
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-3">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                        Current icon will be removed when you save. Upload a new icon below if needed.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview of New Icon -->
                    <div v-if="previewUrl" class="mt-2 mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">New Icon Preview:</p>
                        <div class="flex items-center space-x-4">
                            <img :src="previewUrl" alt="New category icon preview" class="w-16 h-16 object-cover rounded-lg border border-gray-300 dark:border-gray-600" />
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <p>This will replace the current icon when you save</p>
                            </div>
                        </div>
                    </div>

                                    <!-- File Upload -->
                                    <input type="file" @change="handleIconUpload" id="uploaded_icon" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300 dark:hover:file:bg-indigo-800" />
                                    <div v-if="form.errors.uploaded_icon" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.uploaded_icon }}</div>
                                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="currentIconUrl && !form.remove_icon">Upload a new icon to replace the existing one, or use the remove button above</span>
                        <span v-else-if="form.remove_icon">Upload a new icon for this category (JPEG, PNG, WebP, GIF, SVG - max 2MB)</span>
                        <span v-else>Upload an icon for this category (JPEG, PNG, WebP, GIF, SVG - max 2MB)</span>
                    </p>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" id="is_active" class="h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded" />
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Active</label>
                                </div>
                                <div v-if="form.errors.is_active" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.is_active }}</div>

                            </div>

                            <div class="mt-8 flex justify-end">
                                <Link :href="route('admin.categories.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 mr-4 py-2 px-4 rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">Cancel</Link>
                                <button type="submit" :disabled="form.processing"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                        :class="{ 'opacity-25': form.processing }">
                                    Update Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
