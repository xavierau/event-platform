<template>
    <AdminLayout title="Edit Tag">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Tag: {{ tag.name.en || 'N/A' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <TagForm :form="form" :is-edit="true" :submit="updateTag" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/layouts/AppLayout.vue';
import TagForm from './Partials/TagForm.vue';
import { useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';

const props = defineProps({
    tag: Object, // TagData object from controller
});

const form = useForm({
    _method: 'PUT', // Important for Laravel resource controller update method
    id: props.tag.id,
    name: {
        en: props.tag.name?.en || '',
        'zh-TW': props.tag.name?.['zh-TW'] || '',
        'zh-CN': props.tag.name?.['zh-CN'] || '',
    },
    slug: props.tag.slug || '',
});

const updateTag = () => {
    form.post(route('admin.tags.update', props.tag.id), { // .post here due to _method: 'PUT'
        onError: (errors) => {
            console.error('Error updating tag:', errors);
            // Optionally, display a notification to the user
        },
        onSuccess: () => {
            // Optionally, display a success notification
        }
    });
};
</script>
