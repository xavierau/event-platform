<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

// Define the structure for a Promotion item as passed from the controller
interface PromotionListItem {
    id: number;
    title: any; // Translatable
    subtitle: any; // Translatable
    url: string;
    banner?: string | null;
    is_active: boolean;
    starts_at?: string | null;
    ends_at?: string | null;
    sort_order: number;
}

interface Props {
    promotions: PromotionListItem[];
}

const props = defineProps<Props>();

const deletePromotion = (promotionId: number) => {
    if (confirm('Are you sure you want to delete this promotion?')) {
        router.delete(route('admin.promotions.destroy', promotionId), {
            preserveScroll: true,
            // onSuccess: () => { /* Optional: show notification */ }
        });
    }
};

// Helper to get a specific translation (consider moving to a composable if used often)
const getTranslation = (translations: any, locale: string, fallbackLocale: string = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations; // Should not happen for title/subtitle
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

const formatDate = (dateString?: string | null) => {
    if (!dateString) return '-';
    try {
        return new Date(dateString).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (_e) {
        return dateString; // Return original if parsing fails
    }
};

</script>

<template>
    <Head title="Manage Promotions" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="mb-4 flex justify-end">
                            <Link :href="route('admin.promotions.create')"
                                  class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                Create Promotion
                            </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Banner</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">URL</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Active Period</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sort</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="!props.promotions.length">
                                        <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">No promotions found.</td>
                                    </tr>
                                    <tr v-for="promotion in props.promotions" :key="promotion.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <img v-if="promotion.banner" :src="promotion.banner" :alt="getTranslation(promotion.title, 'en')" class="h-10 w-20 object-cover rounded" />
                                            <span v-else class="text-xs text-gray-400">No banner</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ getTranslation(promotion.title, 'en') }}
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ getTranslation(promotion.subtitle, 'en') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <a :href="promotion.url" target="_blank" class="hover:underline">{{ promotion.url }}</a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            {{ formatDate(promotion.starts_at) }} - {{ formatDate(promotion.ends_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span :class="promotion.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ promotion.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">{{ promotion.sort_order }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('admin.promotions.edit', promotion.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">Edit</Link>
                                            <button @click="deletePromotion(promotion.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- TODO: Implement pagination if promotions become numerous -->
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
