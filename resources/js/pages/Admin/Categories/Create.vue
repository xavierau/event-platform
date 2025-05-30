<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AppLayout.vue';

interface Category {
    id: number;
    name: any; // Translatable field (object with locale keys)
    slug: string;
    parent_id?: number | null;
    is_active: boolean;
}

interface Props {
    categoriesForSelect: Pick<Category, 'id' | 'name'>[]; // Categories for parent dropdown
}

const props = defineProps<Props>();

const form = useForm({
    name: { en: '', 'zh-TW': '', 'zh-CN': '' },
    slug: '',
    parent_id: null as number | null,
    is_active: true,
    uploaded_icon: null as File | null,
});

const handleIconUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.uploaded_icon = target.files[0];
    }
};

const submit = () => {
    form.post(route('admin.categories.store'), {
        // onSuccess: () => form.reset(),
    });
};

// Helper to get a specific translation
// TODO: Make this a global helper or composable
const getTranslation = (translations: any, locale: string, fallbackLocale: string = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

</script>

<template>
    <Head title="Create Category" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <!-- Translatable Name Inputs -->
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
                                    <input type="file" @change="handleIconUpload" id="uploaded_icon" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300 dark:hover:file:bg-indigo-800" />
                                    <div v-if="form.errors.uploaded_icon" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.uploaded_icon }}</div>
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
                                    Create Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
