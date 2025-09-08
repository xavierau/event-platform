<template>
    <Head title="Manage Tags" />

    <AppLayout>
        <!-- pageTitle and breadcrumbs are handled by AppLayout via props from controller -->

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="mb-4 flex justify-end">
                            <Link :href="route('admin.tags.create')"
                                  class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-2 focus:ring-3 focus:ring-offset-2  transition ease-in-out duration-150">
                                Create Tag
                            </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Slug</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="!props.tags.data.length">
                                        <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">No tags found.</td>
                                    </tr>
                                    <tr v-for="tag in props.tags.data" :key="tag.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ getTranslation(tag.name, 'en') }}
                                            <div v-if="getOtherTranslations(tag.name)" class="text-xs text-gray-500 dark:text-gray-400">{{ getOtherTranslations(tag.name) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ tag.slug }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('admin.tags.edit', tag.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">Edit</Link>
                                            <button @click="confirmDeleteTag(tag.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="props.tags.links && props.tags.links.length > 3" class="mt-4 flex justify-center">
                            <div class="flex flex-wrap -mb-1">
                                <template v-for="(link, key) in props.tags.links" :key="key">
                                    <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded dark:text-gray-500 dark:border-gray-600" v-html="link.label" />
                                    <Link v-else class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-gray-100 dark:hover:bg-gray-700 focus:border-indigo-500 focus:text-indigo-500 dark:border-gray-600 dark:text-gray-300"
                                          :class="{ 'bg-indigo-600 text-white dark:bg-indigo-500 dark:text-white': link.active }"
                                          :href="link.url"
                                          v-html="link.label" />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue'; // Changed from AdminLayout to AppLayout
import { defineProps } from 'vue';

// Assuming PaginatedResponse and Tag types are defined or will be defined
// For example, in @/types/index.d.ts or similar
// interface Tag {
//     id: number;
//     name: { [key: string]: string };
//     slug: string;
//     // other properties...
// }
// interface PaginatedResponse<T> {
//     data: T[];
//     links: Array<{ url: string | null; label: string; active: boolean }>;
//     // other pagination properties...
// }

interface Props {
    tags: any; // Replace 'any' with PaginatedResponse<Tag> once types are set up
    // filters: Record<string, string>; // For potential filtering
}

const props = defineProps<Props>();

const getOtherTranslations = (nameObject) => {
    if (!nameObject || typeof nameObject !== 'object') return '';
    const translations = [];
    for (const lang in nameObject) {
        if (lang !== 'en' && nameObject[lang]) {
            translations.push(`${lang.toUpperCase()}: ${nameObject[lang]}`);
        }
    }
    return translations.join(', ');
};

const confirmDeleteTag = (tagId) => {
    if (confirm('Are you sure you want to delete this tag?')) {
        router.delete(route('admin.tags.destroy', tagId), {
            preserveScroll: true,
            // onSuccess: () => { /* Optional: show notification */ },
            // onError: () => { /* Optional: show error notification */ },
        });
    }
};

// Helper to get a specific translation (consistent with Venues/Index.vue)
const getTranslation = (translations, locale = 'en', fallbackLocale = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations; // Not a translatable field
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

</script>
