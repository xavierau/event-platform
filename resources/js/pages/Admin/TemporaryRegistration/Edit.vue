<template>
    <Head :title="`Edit: ${getTranslation(page.title)}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800">
                        <PageHeader :title="`Edit: ${getTranslation(page.title)}`">
                            <template #actions>
                                <Link
                                    :href="route('admin.temporary-registration.show', page.id)"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition"
                                >
                                    View Details
                                </Link>
                                <Link
                                    :href="route('admin.temporary-registration.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition"
                                >
                                    Back to List
                                </Link>
                            </template>
                        </PageHeader>

                        <!-- Current Token (Read-only) -->
                        <div class="mt-6 mb-8 bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-indigo-900 dark:to-blue-900 p-4 rounded-lg">
                            <Label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Access Token (Read-only)
                            </Label>
                            <div class="flex items-center space-x-2">
                                <code class="flex-1 bg-white dark:bg-gray-800 px-3 py-2 rounded text-sm font-mono text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600">
                                    {{ page.token }}
                                </code>
                                <Button variant="outline" size="sm" @click="copyToken">
                                    {{ tokenCopied ? 'Copied!' : 'Copy' }}
                                </Button>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Title (Multilingual) -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Title *</h3>
                                <div class="space-y-4">
                                    <div v-for="locale in locales" :key="locale.code">
                                        <Label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            {{ locale.name }}
                                        </Label>
                                        <Input
                                            v-model="form.title[locale.code]"
                                            type="text"
                                            :placeholder="`Enter title in ${locale.name}...`"
                                            class="w-full"
                                        />
                                    </div>
                                </div>
                                <div v-if="form.errors.title" class="text-red-600 text-sm mt-1">{{ form.errors.title }}</div>
                                <div v-if="form.errors['title.en']" class="text-red-600 text-sm mt-1">{{ form.errors['title.en'] }}</div>
                            </div>

                            <!-- Description (Multilingual) -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Description</h3>
                                <div class="space-y-4">
                                    <div v-for="locale in locales" :key="locale.code">
                                        <Label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            {{ locale.name }}
                                        </Label>
                                        <Textarea
                                            v-model="form.description[locale.code]"
                                            rows="3"
                                            :placeholder="`Enter description in ${locale.name}...`"
                                            class="w-full"
                                        />
                                    </div>
                                </div>
                                <div v-if="form.errors.description" class="text-red-600 text-sm mt-1">{{ form.errors.description }}</div>
                            </div>

                            <!-- Membership Level & Settings -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Registration Settings</h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Membership Level -->
                                    <div>
                                        <Label for="membership_level_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Membership Level *
                                        </Label>
                                        <select
                                            id="membership_level_id"
                                            v-model="form.membership_level_id"
                                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        >
                                            <option :value="null">Select a membership level</option>
                                            <option v-for="level in membershipLevels" :key="level.id" :value="level.id">
                                                {{ getTranslation(level.name) }}
                                                ({{ level.duration_months }} months)
                                                - {{ level.price_formatted }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.membership_level_id" class="text-red-600 text-sm mt-1">
                                            {{ form.errors.membership_level_id }}
                                        </div>
                                    </div>

                                    <!-- Max Registrations -->
                                    <div>
                                        <Label for="max_registrations" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Max Registrations
                                        </Label>
                                        <Input
                                            id="max_registrations"
                                            v-model="form.max_registrations"
                                            type="number"
                                            min="1"
                                            placeholder="Leave empty for unlimited"
                                            class="w-full"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited registrations</p>
                                        <div v-if="form.errors.max_registrations" class="text-red-600 text-sm mt-1">
                                            {{ form.errors.max_registrations }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Expiration Settings -->
                                <div class="mt-6">
                                    <Label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Expiration Type
                                    </Label>
                                    <div class="flex flex-col space-y-3">
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="expirationType"
                                                value="none"
                                                class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">No expiration</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="expirationType"
                                                value="duration"
                                                class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Duration (days from registration)</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="expirationType"
                                                value="date"
                                                class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Specific date</span>
                                        </label>
                                    </div>

                                    <!-- Duration Days Input -->
                                    <div v-if="expirationType === 'duration'" class="mt-4">
                                        <Label for="duration_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Trial Duration (days) *
                                        </Label>
                                        <div class="flex items-center space-x-4">
                                            <Input
                                                id="duration_days"
                                                v-model="form.duration_days"
                                                type="number"
                                                min="1"
                                                max="365"
                                                placeholder="e.g., 7, 14, 30"
                                                class="w-32"
                                            />
                                            <div class="flex space-x-2">
                                                <button
                                                    type="button"
                                                    @click="form.duration_days = 7"
                                                    class="px-3 py-1 text-xs rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                    :class="{ 'bg-indigo-100 dark:bg-indigo-900 border-indigo-500': form.duration_days === 7 }"
                                                >
                                                    7 days
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="form.duration_days = 14"
                                                    class="px-3 py-1 text-xs rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                    :class="{ 'bg-indigo-100 dark:bg-indigo-900 border-indigo-500': form.duration_days === 14 }"
                                                >
                                                    14 days
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="form.duration_days = 30"
                                                    class="px-3 py-1 text-xs rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                    :class="{ 'bg-indigo-100 dark:bg-indigo-900 border-indigo-500': form.duration_days === 30 }"
                                                >
                                                    30 days
                                                </button>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Users will have this many days of premium access after registration</p>
                                        <div v-if="form.errors.duration_days" class="text-red-600 text-sm mt-1">
                                            {{ form.errors.duration_days }}
                                        </div>
                                    </div>

                                    <!-- Specific Date Input -->
                                    <div v-if="expirationType === 'date'" class="mt-4">
                                        <Label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Expiration Date *
                                        </Label>
                                        <Input
                                            id="expires_at"
                                            v-model="form.expires_at"
                                            type="datetime-local"
                                            class="w-full max-w-md"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">Registration page will expire at this specific date and time</p>
                                        <div v-if="form.errors.expires_at" class="text-red-600 text-sm mt-1">
                                            {{ form.errors.expires_at }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Is Active -->
                                <div class="mt-6">
                                    <div class="flex items-center space-x-2">
                                        <Switch id="is_active" v-model:checked="form.is_active" />
                                        <Label for="is_active">Active</Label>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Inactive pages cannot be accessed by users</p>
                                </div>
                            </div>

                            <!-- URL Settings -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">URL Settings</h3>

                                <!-- URL Type -->
                                <div class="mb-6">
                                    <Label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        URL Type
                                    </Label>
                                    <div class="flex items-center space-x-6">
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.use_slug"
                                                :value="false"
                                                class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Token-based URL (more secure)</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.use_slug"
                                                :value="true"
                                                class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Custom slug URL (readable)</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Custom Slug (shown only when use_slug is true) -->
                                <div v-if="form.use_slug">
                                    <Label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Custom Slug *
                                    </Label>
                                    <div class="flex items-center">
                                        <span class="text-gray-500 dark:text-gray-400 mr-2">/register/</span>
                                        <Input
                                            id="slug"
                                            v-model="form.slug"
                                            type="text"
                                            placeholder="my-custom-registration"
                                            class="flex-1"
                                        />
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Use lowercase letters, numbers, and hyphens only</p>
                                    <div v-if="form.errors.slug" class="text-red-600 text-sm mt-1">
                                        {{ form.errors.slug }}
                                    </div>
                                </div>
                            </div>

                            <!-- Banner Image -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Banner Image</h3>

                                <!-- Current Banner -->
                                <div v-if="page.banner_url && !bannerPreview" class="mb-4">
                                    <Label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Current Banner
                                    </Label>
                                    <img :src="page.banner_url" alt="Current banner" class="w-full max-w-md h-32 object-cover rounded" />
                                </div>

                                <div>
                                    <Label for="banner" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ page.banner_url ? 'Upload New Banner' : 'Upload Banner' }}
                                    </Label>
                                    <input
                                        id="banner"
                                        type="file"
                                        accept="image/*"
                                        @change="handleBannerUpload"
                                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    <p class="text-xs text-gray-500 mt-1">Recommended size: 1200x400px. Max file size: 2MB</p>
                                    <div v-if="form.errors.banner" class="text-red-600 text-sm mt-1">
                                        {{ form.errors.banner }}
                                    </div>
                                </div>

                                <!-- New Banner Preview -->
                                <div v-if="bannerPreview" class="mt-4">
                                    <Label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        New Banner Preview
                                    </Label>
                                    <img :src="bannerPreview" alt="Banner preview" class="w-full max-w-md h-32 object-cover rounded" />
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex items-center justify-end space-x-4 pt-6">
                                <Link
                                    :href="route('admin.temporary-registration.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-200 disabled:opacity-25 transition"
                                >
                                    Cancel
                                </Link>
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Updating...' : 'Update Registration Page' }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import Label from '@/components/ui/label/Label.vue';
import Input from '@/components/ui/input/Input.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import Button from '@/components/ui/button/Button.vue';
import { Switch } from '@/components/ui/switch';
import { getTranslation } from '@/Utils/i18n';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    price_formatted: string;
    duration_months: number;
}

interface TemporaryRegistrationPage {
    id: number;
    title: Record<string, string>;
    description: Record<string, string> | null;
    slug: string | null;
    token: string;
    membership_level_id: number;
    expires_at: string | null;
    duration_days: number | null;
    max_registrations: number | null;
    is_active: boolean;
    use_slug: boolean;
    banner_url: string | null;
}

interface Props {
    page: TemporaryRegistrationPage;
    membershipLevels: MembershipLevel[];
}

const props = defineProps<Props>();

const locales = [
    { code: 'en', name: 'English' },
    { code: 'zh-TW', name: 'Traditional Chinese' },
    { code: 'zh-CN', name: 'Simplified Chinese' },
];

const bannerPreview = ref<string | null>(null);
const tokenCopied = ref(false);
const expirationType = ref<'none' | 'duration' | 'date'>('none');

interface FormData {
    title: Record<string, string>;
    description: Record<string, string>;
    membership_level_id: number | null;
    use_slug: boolean;
    slug: string;
    expires_at: string;
    duration_days: number | null;
    max_registrations: number | null;
    is_active: boolean;
    banner: File | null;
}

const formatDateForInput = (date: string | null): string => {
    if (!date) return '';
    return new Date(date).toISOString().slice(0, 16);
};

const form = useForm<FormData>({
    title: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    description: locales.reduce((acc, loc) => ({ ...acc, [loc.code]: '' }), {} as Record<string, string>),
    membership_level_id: null,
    use_slug: false,
    slug: '',
    expires_at: '',
    duration_days: null,
    max_registrations: null,
    is_active: true,
    banner: null,
});

// Use watchEffect to populate form data when props change
watchEffect(() => {
    if (props.page) {
        const titleData = typeof props.page.title === 'object' ? props.page.title : {};
        const descriptionData = typeof props.page.description === 'object' ? props.page.description : {};

        // Determine expiration type based on existing data
        if (props.page.duration_days) {
            expirationType.value = 'duration';
        } else if (props.page.expires_at) {
            expirationType.value = 'date';
        } else {
            expirationType.value = 'none';
        }

        form.defaults({
            title: locales.reduce((acc, loc) => ({
                ...acc,
                [loc.code]: titleData[loc.code] || '',
            }), {} as Record<string, string>),
            description: locales.reduce((acc, loc) => ({
                ...acc,
                [loc.code]: descriptionData[loc.code] || '',
            }), {} as Record<string, string>),
            membership_level_id: props.page.membership_level_id,
            use_slug: props.page.use_slug,
            slug: props.page.slug || '',
            expires_at: formatDateForInput(props.page.expires_at),
            duration_days: props.page.duration_days,
            max_registrations: props.page.max_registrations,
            is_active: props.page.is_active,
            banner: null,
        });
        form.reset();
    }
});

const handleBannerUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.banner = target.files[0];

        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            bannerPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(target.files[0]);
    }
};

const copyToken = async () => {
    try {
        await navigator.clipboard.writeText(props.page.token);
        tokenCopied.value = true;
        setTimeout(() => {
            tokenCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy token:', err);
    }
};

const submit = () => {
    // Clear unused expiration fields based on type
    if (expirationType.value === 'none') {
        form.expires_at = '';
        form.duration_days = null;
    } else if (expirationType.value === 'duration') {
        form.expires_at = '';
    } else if (expirationType.value === 'date') {
        form.duration_days = null;
    }

    form.post(route('admin.temporary-registration.update', props.page.id), {
        onSuccess: () => {
            // Redirect will be handled by the controller
        },
    });
};
</script>
