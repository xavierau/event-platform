<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

// Define props if needed, for example, if you pass specific data to the dashboard
// const props = defineProps({
//   stats: Object,
// });
</script>

<template>
    <AppLayout>
        <!-- The pageTitle and breadcrumbs are handled by AppLayout via props passed from the controller -->
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        Welcome to your Dashboard!
                        <!-- Dashboard content will go here -->
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
