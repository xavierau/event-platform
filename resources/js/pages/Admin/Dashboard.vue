<template>
    <AuthenticatedLayout title="Admin Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Admin Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        Welcome to the Admin Dashboard!
                        <!-- Admin-specific content will go here -->
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
    import AuthenticatedLayout from '@/layouts/AuthLayout.vue';

// Define props, if any, passed from the controller
// const props = defineProps({
//     // exampleProp: String,
// });

</script>
