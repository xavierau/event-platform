<template>
    <Head title="Promotional Modals Analytics" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800">
                        <PageHeader
                            title="Promotional Modals Analytics"
                            subtitle="Overview of all promotional modal performance"
                        >
                            <template #actions>
                                <Link :href="route('admin.promotional-modals.index')"
                                      class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Back to List
                                </Link>
                            </template>
                        </PageHeader>

                        <div class="mt-8">
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 p-8 rounded-lg text-center">
                                <div class="text-6xl mb-4">📊</div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                                    Analytics Dashboard Coming Soon
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">
                                    System-wide promotional modal analytics will be available here.
                                    In the meantime, you can view individual modal analytics from the list page.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
</script>
