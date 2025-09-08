<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const form = useForm({
    title: { en: '', 'zh-TW': '', 'zh-CN': '' },
    subtitle: { en: '', 'zh-TW': '', 'zh-CN': '' },
    url: '',
    is_active: false,
    starts_at: '',
    ends_at: '',
    sort_order: 0,
    uploaded_banner_image: null as File | null,
});

const handleBannerUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.uploaded_banner_image = target.files[0];
    }
};

const submit = () => {
    form.transform((data) => {
        return {
            ...data,
            title: {
                en: data.title.en || '',
                'zh-TW': data.title['zh-TW'] || '',
                'zh-CN': data.title['zh-CN'] || ''
            },
            subtitle: {
                en: data.subtitle.en || '',
                'zh-TW': data.subtitle['zh-TW'] || '',
                'zh-CN': data.subtitle['zh-CN'] || ''
            }
        };
    }).post(route('admin.promotions.store'));
};
</script>

<template>
    <Head title="Create Promotion" />

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
                                            <input type="text" v-model="form.title.en" id="title_en" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="title_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.title['zh-TW']" id="title_zh_TW" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="title_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.title['zh-CN']" id="title_zh_CN" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
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
                                            <input type="text" v-model="form.subtitle.en" id="subtitle_en" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="subtitle_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.subtitle['zh-TW']" id="subtitle_zh_TW" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        </div>
                                        <div>
                                            <label for="subtitle_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.subtitle['zh-CN']" id="subtitle_zh_CN" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
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
                                    <input type="url" v-model="form.url" id="url" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" required />
                                    <div v-if="form.errors.url" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.url }}</div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The URL where users will be redirected when clicking the promotion</p>
                                </div>

                                <!-- Banner Image Upload -->
                                <div>
                                    <label for="uploaded_banner_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Banner Image</label>
                                    <input type="file" @change="handleBannerUpload" id="uploaded_banner_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300 dark:hover:file:bg-indigo-800" />
                                    <div v-if="form.errors.uploaded_banner_image" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.uploaded_banner_image }}</div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Upload a banner image for the promotion (JPEG, PNG, WebP - max 10MB)</p>
                                </div>

                                <!-- Date Range -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="starts_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                                        <input type="datetime-local" v-model="form.starts_at" id="starts_at" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        <div v-if="form.errors.starts_at" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.starts_at }}</div>
                                    </div>
                                    <div>
                                        <label for="ends_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                                        <input type="datetime-local" v-model="form.ends_at" id="ends_at" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                        <div v-if="form.errors.ends_at" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.ends_at }}</div>
                                    </div>
                                </div>

                                <!-- Sort Order -->
                                <div>
                                    <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sort Order</label>
                                    <input type="number" v-model="form.sort_order" id="sort_order" min="0" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                                    <div v-if="form.errors.sort_order" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.sort_order }}</div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lower numbers appear first in the carousel</p>
                                </div>

                                <!-- Active Status -->
                                <div class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" id="is_active" class="h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-2 focus:ring-3 dark:focus:ring-indigo-600 rounded" />
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Active</label>
                                </div>
                                <div v-if="form.errors.is_active" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.is_active }}</div>
                            </div>

                            <div class="mt-8 flex justify-end">
                                <Link :href="route('admin.promotions.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 mr-4 py-2 px-4 rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">Cancel</Link>
                                <button type="submit" :disabled="form.processing"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-2 focus:ring-3 focus:ring-offset-2  transition ease-in-out duration-150"
                                        :class="{ 'opacity-25': form.processing }">
                                    Create Promotion
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
