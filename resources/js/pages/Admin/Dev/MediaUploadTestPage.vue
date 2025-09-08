<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import MediaUpload from '@/components/Form/MediaUpload.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button'; // Assuming you have a Button component

// Form for single file upload
const singleForm = useForm({
    _method: 'POST', // Can be changed to PUT for testing PUT
    singleFile: null as File | null,
    someOtherField: 'Test Single',
    metadata: { // New nested object
        author: 'Default Author',
        description: 'Default description for metadata',
        keywords: [] as string[], // e.g. ['test', 'image']
        settings: {
            isVisible: true,
            rating: 5
        }
    }
});

// Form for multiple file upload
const multipleForm = useForm({
    _method: 'POST', // Can be changed to PUT for testing PUT
    multipleFiles: [] as File[],
    anotherField: 'Test Multiple',
    // We can add nested data here too if needed, following the same pattern as singleForm
});

const existingSingleMedia = ref([
    { id: 1, name: 'Existing Image 1.jpg', file_name: 'Existing Image 1.jpg', url: 'https://via.placeholder.com/150/FF0000/FFFFFF?Text=Existing1.jpg', preview_url: 'https://via.placeholder.com/150/FF0000/FFFFFF?Text=Existing1.jpg', thumbnail_url: 'https://via.placeholder.com/100/FF0000/FFFFFF?Text=Existing1.jpg', mime_type: 'image/jpeg', size: 12345 },
]);

const existingMultipleMedia = ref([
    { id: 2, name: 'Existing Image 2.png', file_name: 'Existing Image 2.png', url: 'https://via.placeholder.com/150/00FF00/FFFFFF?Text=Existing2.png', preview_url: 'https://via.placeholder.com/150/00FF00/FFFFFF?Text=Existing2.png', thumbnail_url: 'https://via.placeholder.com/100/00FF00/FFFFFF?Text=Existing2.png', mime_type: 'image/png', size: 23456 },
    { id: 3, name: 'Existing Image 3.gif', file_name: 'Existing Image 3.gif', url: 'https://via.placeholder.com/150/0000FF/FFFFFF?Text=Existing3.gif', preview_url: 'https://via.placeholder.com/150/0000FF/FFFFFF?Text=Existing3.gif', thumbnail_url: 'https://via.placeholder.com/100/0000FF/FFFFFF?Text=Existing3.gif', mime_type: 'image/gif', size: 34567 },
]);

const handleRemoveExisting = (payload: { collection: string, id: number }) => {
    console.log('Remove existing media:', payload);
    if (payload.collection === 'singleTest') {
        existingSingleMedia.value = existingSingleMedia.value.filter(media => media.id !== payload.id);
    }
    if (payload.collection === 'multipleTest') {
        existingMultipleMedia.value = existingMultipleMedia.value.filter(media => media.id !== payload.id);
    }
    // Potentially, you might want to inform the backend here if an existing file is removed before a new form submission
    // For example, by adding a hidden field to the form or making a separate API call.
    // For this test page, we are just updating the local ref.
};

const submitSingleForm = (method: 'POST' | 'PUT') => {
    singleForm._method = method;
    const url = method === 'POST'
        ? route('admin.dev.media-upload-test.post')
        : route('admin.dev.media-upload-test.put');

    console.log('Submitting singleForm with data:', JSON.parse(JSON.stringify(singleForm)));

    // For PUT requests with files, Inertia expects a POST request with a `_method: 'PUT'` field.
    // The `useForm` helper handles this automatically when you call `form.put()` or `form.post()` with `_method` set.
    // However, since we are dynamically setting the URL and want to be explicit for testing:

    singleForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            console.log(`${method} submission successful for single file!`);
            // singleForm.reset('singleFile'); // Optionally reset the file input
        },
        onError: (errors) => {
            console.error(`${method} submission failed for single file:`, errors);
        }
    });
};

const submitMultipleForm = (method: 'POST' | 'PUT') => {
    multipleForm._method = method;
    const url = method === 'POST'
        ? route('admin.dev.media-upload-test.post')
        : route('admin.dev.media-upload-test.put');

    console.log('Submitting multipleForm with data:', JSON.parse(JSON.stringify(multipleForm)));

    multipleForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            console.log(`${method} submission successful for multiple files!`);
            // multipleForm.reset('multipleFiles'); // Optionally reset file inputs
        },
        onError: (errors) => {
            console.error(`${method} submission failed for multiple files:`, errors);
        }
    });
};

const pageTitle = "Media Upload Test";
const breadcrumbs = [
    { text: 'Admin Dashboard', href: route('admin.dashboard') },
    { text: 'Development' },
    { text: 'Media Upload Test' }
];

</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout :page-title="pageTitle" :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 space-y-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Media Upload Component Test Page</h1>

            <!-- Single File Upload Form -->
            <form @submit.prevent="submitSingleForm('POST')" class="p-6 bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <h2 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-4">Single File Upload Test</h2>
                <MediaUpload
                    v-model="singleForm.singleFile"
                    :existing-media="existingSingleMedia"
                    collection-name="singleTest"
                    label="Upload Single Image"
                    accept="image/*"
                    :max-file-size-mb="5"
                    @remove-existing="handleRemoveExisting"
                />
                <div class="mt-3">
                    <label for="someOtherField" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Other Field (Single):</label>
                    <input type="text" v-model="singleForm.someOtherField" id="someOtherField" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-3 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300" />
                </div>

                <fieldset class="mt-4 border dark:border-gray-700 p-3 rounded-md">
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Nested Metadata</legend>
                    <div class="mt-2 space-y-2">
                        <div>
                            <label for="metadata_author" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Author:</label>
                            <input type="text" v-model="singleForm.metadata.author" id="metadata_author" class="mt-1 block w-full input-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300" />
                        </div>
                        <div>
                            <label for="metadata_description" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Description:</label>
                            <textarea v-model="singleForm.metadata.description" id="metadata_description" rows="2" class="mt-1 block w-full input-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"></textarea>
                        </div>
                        <div>
                            <label for="metadata_keywords" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Keywords (comma-separated):</label>
                            <input type="text" :value="singleForm.metadata.keywords.join(', ')" @input="e => singleForm.metadata.keywords = (e.target as HTMLInputElement).value.split(',').map(t => t.trim()).filter(t => t)" id="metadata_keywords" class="mt-1 block w-full input-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300" />
                        </div>
                        <div class="flex items-center mt-2">
                            <input type="checkbox" v-model="singleForm.metadata.settings.isVisible" id="metadata_isVisible" class="h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded" />
                            <label for="metadata_isVisible" class="ml-2 text-xs text-gray-700 dark:text-gray-400">Is Visible</label>
                        </div>
                        <div>
                             <label for="metadata_rating" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Rating (1-5):</label>
                            <input type="number" v-model.number="singleForm.metadata.settings.rating" id="metadata_rating" min="1" max="5" class="mt-1 block w-full input-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300" />
                        </div>
                    </div>
                </fieldset>

                <div v-if="singleForm.errors.singleFile" class="text-red-500 text-sm mt-1">{{ singleForm.errors.singleFile }}</div>
                <div v-if="singleForm.errors.someOtherField" class="text-red-500 text-sm mt-1">{{ singleForm.errors.someOtherField }}</div>
                <div v-if="singleForm.errors.metadata" class="text-red-500 text-sm mt-1">{{ singleForm.errors.metadata }}</div>

                <div class="mt-6 flex space-x-3">
                    <Button type="button" @click="submitSingleForm('POST')" :disabled="singleForm.processing">Submit as POST</Button>
                    <Button type="button" @click="submitSingleForm('PUT')" variant="outline" :disabled="singleForm.processing">Submit as PUT</Button>
                </div>
                <div v-if="singleForm.progress" class="mt-2">
                    <progress :value="singleForm.progress.percentage" max="100">{{ singleForm.progress.percentage }}%</progress>
                </div>
                 <div v-if="$page.props.flash?.success_message && singleForm.recentlySuccessful" class="mt-2 text-green-600 dark:text-green-400 text-sm">
                    {{ $page.props.flash.success_message }}
                </div>
            </form>

            <!-- Multiple File Upload Form -->
            <form @submit.prevent="submitMultipleForm('POST')" class="p-6 bg-white dark:bg-gray-800 shadow-md rounded-lg">
                <h2 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-4">Multiple File Upload Test</h2>
                <MediaUpload
                    v-model="multipleForm.multipleFiles"
                    :existing-media="existingMultipleMedia"
                    collection-name="multipleTest"
                    label="Upload Multiple Images (Max 3)"
                    multiple
                    accept="image/*"
                    :max-files="3"
                    :max-file-size-mb="2"
                    @remove-existing="handleRemoveExisting"
                />
                 <div class="mt-3">
                    <label for="anotherField" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Other Field (Multiple):</label>
                    <input type="text" v-model="multipleForm.anotherField" id="anotherField" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-3 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300" />
                </div>
                <div v-if="multipleForm.errors.multipleFiles" class="text-red-500 text-sm mt-1">{{ multipleForm.errors.multipleFiles }}</div>
                <div v-if="multipleForm.errors.anotherField" class="text-red-500 text-sm mt-1">{{ multipleForm.errors.anotherField }}</div>

                <div class="mt-6 flex space-x-3">
                    <Button type="button" @click="submitMultipleForm('POST')" :disabled="multipleForm.processing">Submit as POST</Button>
                    <Button type="button" @click="submitMultipleForm('PUT')" variant="outline" :disabled="multipleForm.processing">Submit as PUT</Button>
                </div>
                <div v-if="multipleForm.progress" class="mt-2">
                    <progress :value="multipleForm.progress.percentage" max="100">{{ multipleForm.progress.percentage }}%</progress>
                </div>
                <div v-if="$page.props.flash?.success_message && multipleForm.recentlySuccessful" class="mt-2 text-green-600 dark:text-green-400 text-sm">
                    {{ $page.props.flash.success_message }}
                </div>
            </form>

        </div>
    </AppLayout>
</template>
