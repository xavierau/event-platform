<template>
    <form @submit.prevent="submit">
        <div class="mb-4">
            <label for="name_en" class="block text-sm font-medium text-gray-700">Name (English)</label>
            <input type="text" v-model="form.name.en" id="name_en" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm">
            <div v-if="form.errors?.has && form.errors.has('name.en')" class="text-red-600 text-sm mt-1">{{ form.errors.get('name.en') }}</div>
        </div>

        <div class="mb-4">
            <label for="name_zh-TW" class="block text-sm font-medium text-gray-700">Name (Traditional Chinese)</label>
            <input type="text" v-model="form.name['zh-TW']" id="name_zh-TW" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm">
            <div v-if="form.errors?.has && form.errors.has('name.zh-TW')" class="text-red-600 text-sm mt-1">{{ form.errors.get('name.zh-TW') }}</div>
        </div>

        <div class="mb-4">
            <label for="name_zh-CN" class="block text-sm font-medium text-gray-700">Name (Simplified Chinese)</label>
            <input type="text" v-model="form.name['zh-CN']" id="name_zh-CN" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm">
             <div v-if="form.errors?.has && form.errors.has('name.zh-CN')" class="text-red-600 text-sm mt-1">{{ form.errors.get('name.zh-CN') }}</div>
        </div>

        <div class="mb-4">
            <label for="slug" class="block text-sm font-medium text-gray-700">Slug</label>
            <input type="text" v-model="form.slug" id="slug" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-3 focus:border-indigo-500 sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">If left empty, the slug will be auto-generated from the English name.</p>
            <div v-if="form.errors?.has && form.errors.has('slug')" class="text-red-600 text-sm mt-1">{{ form.errors.get('slug') }}</div>
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" :disabled="form.processing" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <span v-if="form.processing">Processing...</span>
                <span v-else>{{ isEdit ? 'Update' : 'Create' }} Tag</span>
            </button>
            <Link :href="route('admin.tags.index')" class="ml-4 text-gray-600 hover:text-gray-900">Cancel</Link>
        </div>
    </form>
</template>

<script setup lang="ts">
import { defineProps, toRefs } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3'; // Import useForm to define the type

// Define a more specific type for the form prop
interface TagFormData {
    name: {
        en: string;
        'zh-TW': string;
        'zh-CN': string;
    };
    slug: string;
    id?: number;
    _method?: 'PUT' | 'POST';
}

const props = defineProps({
    form: Object as () => ReturnType<typeof useForm<TagFormData>>,
    isEdit: Boolean,
    submit: Function as () => void, // More specific function type
});

const { form, isEdit, submit } = toRefs(props);

</script>
