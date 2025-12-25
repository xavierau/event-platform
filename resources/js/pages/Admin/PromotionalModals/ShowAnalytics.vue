<template>
    <Head :title="`Analytics: ${getTranslation(promotionalModal.title, currentLocale)}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800">
                        <PageHeader
                            :title="`Analytics: ${getTranslation(promotionalModal.title, currentLocale)}`"
                            subtitle="Detailed performance metrics for this promotional modal"
                        >
                            <template #actions>
                                <Link :href="route('admin.promotional-modals.edit', promotionalModal.id)"
                                      class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    Edit Modal
                                </Link>
                                <Link :href="route('admin.promotional-modals.index')"
                                      class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Back to List
                                </Link>
                            </template>
                        </PageHeader>

                        <!-- Performance Summary -->
                        <div class="mt-8 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 p-6 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Performance Summary</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center shadow">
                                    <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ promotionalModal.impressions_count?.toLocaleString() || 0 }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total Impressions</div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center shadow">
                                    <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                                        {{ promotionalModal.clicks_count?.toLocaleString() || 0 }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total Clicks</div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center shadow">
                                    <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">
                                        {{ promotionalModal.conversion_rate || 0 }}%
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Conversion Rate</div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center shadow">
                                    <div :class="statusClass(promotionalModal.is_active)" class="text-3xl font-bold">
                                        {{ promotionalModal.is_active ? 'Active' : 'Inactive' }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Current Status</div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Details -->
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Modal Details</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white capitalize">
                                            {{ promotionalModal.type }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Display Frequency</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white capitalize">
                                            {{ promotionalModal.display_frequency }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Priority</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ promotionalModal.priority }}
                                        </dd>
                                    </div>
                                    <div v-if="promotionalModal.pages && promotionalModal.pages.length > 0">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Display Pages</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ promotionalModal.pages.join(', ') }}
                                        </dd>
                                    </div>
                                    <div v-else>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Display Pages</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">All Pages</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Schedule</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ formatDate(promotionalModal.start_at) || 'Not set' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ formatDate(promotionalModal.end_at) || 'Not set' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Coming Soon Notice -->
                        <div class="mt-8 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 p-6 rounded-lg">
                            <div class="flex items-start">
                                <div class="text-2xl mr-3">📈</div>
                                <div>
                                    <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-200">
                                        Detailed Analytics Coming Soon
                                    </h3>
                                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                        Charts, time-series data, and detailed breakdowns will be available in a future update.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { getTranslation } from '@/Utils/i18n';

interface PromotionalModal {
    id: number;
    title: Record<string, string> | string;
    type: 'modal' | 'banner';
    pages: string[] | null;
    display_frequency: 'once' | 'daily' | 'weekly' | 'always';
    priority: number;
    is_active: boolean;
    impressions_count?: number;
    clicks_count?: number;
    conversion_rate?: number;
    start_at: string | null;
    end_at: string | null;
}

const props = defineProps<{
    promotionalModal: PromotionalModal;
}>();

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

const statusClass = (isActive: boolean) => {
    return isActive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
};

const formatDate = (date: string | null) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>
