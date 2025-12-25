<template>
    <Head title="Link Unavailable" />
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <div class="flex items-center justify-center min-h-screen py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md w-full text-center">
                <div class="mx-auto h-24 w-24 text-yellow-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">
                    Link Unavailable
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ message }}
                </p>
                <div v-if="link" class="mt-4 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium">Link:</span> {{ link.name || link.code }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium">Status:</span>
                        <span :class="statusClass">{{ link.status_label }}</span>
                    </p>
                </div>
                <div class="mt-6">
                    <Link :href="route('home')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Go to Homepage
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface LinkInfo {
    code: string;
    name: string | null;
    status: string;
    status_label: string;
}

const props = defineProps<{
    message: string;
    link?: LinkInfo;
}>();

const statusClass = computed(() => {
    switch (props.link?.status) {
        case 'expired':
            return 'text-red-600 dark:text-red-400';
        case 'revoked':
            return 'text-gray-600 dark:text-gray-400';
        case 'exhausted':
            return 'text-orange-600 dark:text-orange-400';
        default:
            return 'text-gray-600 dark:text-gray-400';
    }
});
</script>
