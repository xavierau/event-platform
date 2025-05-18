<template>
    <AdminLayout title="Create Tag">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create New Tag
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <TagForm :form="form" :is-edit="false" :submit="createTag" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AppLayout.vue';
import TagForm from './Partials/TagForm.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: {
        en: '',
        'zh-TW': '',
        'zh-CN': '',
    },
    slug: '',
});

const createTag = () => {
    form.post(route('admin.tags.store'), {
        onError: (errors) => {
            console.error('Error creating tag:', errors);
            // Optionally, display a notification to the user
        },
        onSuccess: () => {
            // Optionally, display a success notification
            // form.reset(); // Reset form on successful creation
        }
    });
};
</script>
