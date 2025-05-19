<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

// If you have a specific AdminLayout, import and use it.
// Example: import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    initialSettings: Object,
    locales: Array,
    settingGroups: Object,
    errors: Object,
});

const form = useForm({
    ...props.initialSettings,
});

const submit = () => {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Optionally, add a toast notification for success
        },
    });
};

const activeTab = ref(Object.keys(props.settingGroups)[0] || 'Site');

const getFieldLabel = (key: string): string => {
    return key.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getFieldType = (key: string): string => {
    if (typeof props.initialSettings[key] === 'boolean') return 'checkbox';
    if (key.includes('description') || key.includes('credits') || key.includes('slogan')) return 'textarea';
    // Add more specific types like 'email', 'number', 'password' if needed
    return 'text';
};

</script>

<template>
    <Head title="Application Settings" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-3xl mx-auto">
                <div v-if="$page.props.flash && $page.props.flash.success"
                     class="mb-6 p-4 bg-green-50 border border-green-300 text-green-700 rounded-md shadow-sm">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="Object.keys(form.errors).length > 0" class="mb-6 p-4 bg-red-50 border border-red-300 text-red-700 rounded-md shadow-sm">
                    Please correct the errors below.
                </div>

                <div class="mb-6">
                    <div class="sm:hidden">
                        <label for="tabs" class="sr-only">Select a tab</label>
                        <select id="tabs" name="tabs" @change="activeTab = ($event.target as HTMLSelectElement).value" class="block w-full rounded-md border-gray-300 focus:border-purple-500 focus:ring-purple-500">
                            <option v-for="groupName in Object.keys(settingGroups)" :key="groupName" :value="groupName" :selected="activeTab === groupName">
                                {{ groupName }}
                            </option>
                        </select>
                    </div>
                    <div class="hidden sm:block">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                                <button
                                    v-for="groupName in Object.keys(settingGroups)"
                                    :key="groupName"
                                    @click="activeTab = groupName"
                                    :class="[
                                        activeTab === groupName
                                            ? 'border-purple-600 text-purple-700'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm transition-colors duration-150 ease-in-out'
                                    ]"
                                >
                                    {{ groupName }}
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submit">
                    <div v-for="(keys, groupName) in settingGroups" :key="groupName" v-show="activeTab === groupName">
                        <div class="bg-white shadow-lg sm:rounded-lg overflow-hidden mb-8">
                            <div class="px-6 py-5 border-b border-gray-200">
                                <h2 class="text-xl font-semibold text-gray-800">{{ groupName }} Settings</h2>
                            </div>
                            <div class="p-6 space-y-6">
                                <div v-for="key in keys" :key="key" class="">
                                    <label :for="key" class="block text-sm font-medium text-gray-700 mb-1.5">{{ getFieldLabel(key) }}</label>

                                    <!-- Translatable fields -->
                                    <div v-if="typeof form[key] === 'object' && form[key] !== null && !Array.isArray(form[key]) && locales" class="space-y-3">
                                        <div v-for="locale in locales" :key="locale" class="relative">
                                            <label :for="`${key}-${locale}`" class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500 uppercase">{{ locale }}</label>
                                            <component
                                                :is="getFieldType(key) === 'textarea' ? 'textarea' : 'input'"
                                                :type="getFieldType(key) === 'textarea' ? null : getFieldType(key)"
                                                :rows="getFieldType(key) === 'textarea' ? 3 : null"
                                                :id="`${key}-${locale}`"
                                                v-model="form[key][locale]"
                                                class="mt-1 block w-full px-3 py-2 shadow-sm sm:text-sm border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500"
                                                :placeholder="getFieldLabel(key) + ' (' + locale.toUpperCase() + ')'"
                                            />
                                            <div v-if="form.errors[`${key}.${locale}`]" class="text-red-600 text-xs mt-1">{{ form.errors[`${key}.${locale}`] }}</div>
                                        </div>
                                    </div>

                                    <!-- Boolean fields (checkbox/toggle) -->
                                    <div v-else-if="typeof form[key] === 'boolean'" class="flex items-center">
                                        <input
                                            type="checkbox"
                                            :id="key"
                                            v-model="form[key]"
                                            :checked="form[key]"
                                            class="h-5 w-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                                        />
                                        <label :for="key" class="ml-2 block text-sm text-gray-900">Enable {{ getFieldLabel(key).toLowerCase() }}</label>
                                        <div v-if="form.errors[key]" class="text-red-600 text-xs mt-1 ml-2">{{ form.errors[key] }}</div>
                                    </div>

                                    <!-- Single value text/other fields -->
                                    <div v-else>
                                        <component
                                            :is="getFieldType(key) === 'textarea' ? 'textarea' : 'input'"
                                            :type="getFieldType(key) === 'textarea' ? null : getFieldType(key)"
                                            :rows="getFieldType(key) === 'textarea' ? 3 : null"
                                            :id="key"
                                            v-model="form[key]"
                                            class="mt-1 block w-full px-3 py-2 shadow-sm sm:text-sm border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500"
                                            :placeholder="getFieldLabel(key)"
                                        />
                                        <div v-if="form.errors[key]" class="text-red-600 text-xs mt-1">{{ form.errors[key] }}</div>
                                    </div>

                                    <!-- Add hint/description for field if available -->
                                    <!-- <p class="mt-1 text-xs text-gray-500">Hint for {{ key }}</p> -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end sticky bottom-0 bg-gray-100 py-4 px-6 rounded-b-lg_ (if inside scrollable container)">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center py-2.5 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-60 transition ease-in-out duration-150"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
