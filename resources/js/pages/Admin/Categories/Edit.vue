<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Category, CategoryData } from '@/types'; // Assuming CategoryData DTO and Category model type

interface Props {
    category: CategoryData; // CategoryData DTO passed from controller
    categoriesForSelect: Pick<Category, 'id' | 'name'>[]; // Categories for parent dropdown
}

const props = defineProps<Props>();

const form = useForm<CategoryData>({
    id: props.category.id,
    name: JSON.parse(JSON.stringify(props.category.name)), // Deep copy for objects/arrays
    slug: props.category.slug,
    parent_id: props.category.parent_id,
    is_active: props.category.is_active,
});

const submit = () => {
    if (props.category.id) {
        form.put(route('admin.categories.update', props.category.id), {
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

</script>

<template>
    <Head :title="`Edit Category: ${getTranslation(props.category.name, 'en')}`" />

    <AuthenticatedLayout>
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
    </AuthenticatedLayout>
</template>
