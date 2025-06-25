<template>
    <Head title="Create Coupon" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <PageHeader title="Create New Coupon" subtitle="Set up a new coupon template">
                            <template #actions>
                                <Link :href="route('admin.coupons.index')" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Back to Coupons
                                </Link>
                            </template>
                        </PageHeader>

                        <form @submit.prevent="submitForm">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Left Column -->
                                <div class="space-y-6">
                                    <!-- Basic Information -->
                                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h3>

                                        <div class="space-y-4">
                                            <!-- Coupon Name -->
                                            <div>
                                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coupon Name *</label>
                                                <input
                                                    type="text"
                                                    v-model="form.name"
                                                    id="name"
                                                    required
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                    placeholder="Enter coupon name"
                                                />
                                                <div v-if="form.errors.name" class="input-error mt-1">{{ form.errors.name }}</div>
                                            </div>

                                            <!-- Coupon Code -->
                                            <div>
                                                <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coupon Code *</label>
                                                <div class="mt-1 flex rounded-md shadow-sm">
                                                    <input
                                                        type="text"
                                                        v-model="form.code"
                                                        id="code"
                                                        required
                                                        class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-l-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        placeholder="SAVE20"
                                                    />
                                                    <button
                                                        type="button"
                                                        @click="generateCode"
                                                        class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm rounded-r-md hover:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                                    >
                                                        Generate
                                                    </button>
                                                </div>
                                                <div v-if="form.errors.code" class="input-error mt-1">{{ form.errors.code }}</div>
                                            </div>

                                            <!-- Description -->
                                            <div>
                                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                                <textarea
                                                    v-model="form.description"
                                                    id="description"
                                                    rows="3"
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                    placeholder="Describe the coupon purpose and terms"
                                                />
                                                <div v-if="form.errors.description" class="input-error mt-1">{{ form.errors.description }}</div>
                                            </div>

                                            <!-- Organizer -->
                                            <div>
                                                <label for="organizer_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organizer *</label>
                                                <select
                                                    v-model="form.organizer_id"
                                                    id="organizer_id"
                                                    required
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                >
                                                    <option value="">Select an organizer</option>
                                                    <option v-for="organizer in organizers" :key="organizer.id" :value="organizer.id">
                                                        {{ getTranslation(organizer.name, currentLocale) }}
                                                    </option>
                                                </select>
                                                <div v-if="form.errors.organizer_id" class="input-error mt-1">{{ form.errors.organizer_id }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Discount Configuration -->
                                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Discount Configuration</h3>

                                        <div class="space-y-4">
                                            <!-- Discount Type -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Type *</label>
                                                <div class="mt-2 space-y-2">
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" v-model="form.discount_type" value="fixed" class="form-radio text-indigo-600">
                                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fixed Amount ($)</span>
                                                    </label>
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" v-model="form.discount_type" value="percentage" class="form-radio text-indigo-600">
                                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Percentage (%)</span>
                                                    </label>
                                                </div>
                                                <div v-if="form.errors.discount_type" class="input-error mt-1">{{ form.errors.discount_type }}</div>
                                            </div>

                                            <!-- Discount Value -->
                                            <div>
                                                <label for="discount_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Discount Value *
                                                    <span v-if="form.discount_type === 'fixed'" class="text-gray-500">(in dollars)</span>
                                                    <span v-if="form.discount_type === 'percentage'" class="text-gray-500">(0-100)</span>
                                                </label>
                                                <div class="mt-1 relative rounded-md shadow-sm">
                                                    <div v-if="form.discount_type === 'fixed'" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">$</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        v-model.number="form.discount_value"
                                                        id="discount_value"
                                                        required
                                                        :min="form.discount_type === 'percentage' ? 0 : 0.01"
                                                        :max="form.discount_type === 'percentage' ? 100 : undefined"
                                                        :step="form.discount_type === 'percentage' ? 1 : 0.01"
                                                        :class="form.discount_type === 'fixed' ? 'pl-7' : ''"
                                                        class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        :placeholder="form.discount_type === 'percentage' ? '10' : '5.00'"
                                                    />
                                                    <div v-if="form.discount_type === 'percentage'" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">%</span>
                                                    </div>
                                                </div>
                                                <div v-if="form.errors.discount_value" class="input-error mt-1">{{ form.errors.discount_value }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="space-y-6">
                                    <!-- Usage Configuration -->
                                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Usage Configuration</h3>

                                        <div class="space-y-4">
                                            <!-- Coupon Type -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coupon Type *</label>
                                                <div class="mt-2 space-y-2">
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" v-model="form.type" value="single_use" class="form-radio text-indigo-600">
                                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Single Use (one-time per user)</span>
                                                    </label>
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" v-model="form.type" value="multi_use" class="form-radio text-indigo-600">
                                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Multi Use (reusable)</span>
                                                    </label>
                                                </div>
                                                <div v-if="form.errors.type" class="input-error mt-1">{{ form.errors.type }}</div>
                                            </div>

                                            <!-- Max Issuance -->
                                            <div>
                                                <label for="max_issuance" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Maximum Issuance</label>
                                                <input
                                                    type="number"
                                                    v-model.number="form.max_issuance"
                                                    id="max_issuance"
                                                    min="1"
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                    placeholder="Leave empty for unlimited"
                                                />
                                                <p class="mt-1 text-sm text-gray-500">Maximum number of times this coupon can be issued to users</p>
                                                <div v-if="form.errors.max_issuance" class="input-error mt-1">{{ form.errors.max_issuance }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Validity Period -->
                                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Validity Period</h3>

                                        <div class="space-y-4">
                                            <!-- Valid From -->
                                            <div>
                                                <label for="valid_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valid From</label>
                                                <input
                                                    type="datetime-local"
                                                    v-model="form.valid_from"
                                                    id="valid_from"
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                />
                                                <p class="mt-1 text-sm text-gray-500">Leave empty to make coupon valid immediately</p>
                                                <div v-if="form.errors.valid_from" class="input-error mt-1">{{ form.errors.valid_from }}</div>
                                            </div>

                                            <!-- Expires At -->
                                            <div>
                                                <label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expires At</label>
                                                <input
                                                    type="datetime-local"
                                                    v-model="form.expires_at"
                                                    id="expires_at"
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                />
                                                <p class="mt-1 text-sm text-gray-500">Leave empty for no expiration</p>
                                                <div v-if="form.errors.expires_at" class="input-error mt-1">{{ form.errors.expires_at }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Coupon Image -->
                                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Coupon Image</h3>

                                        <MediaUpload
                                            v-model="form.uploaded_image"
                                            :existingMedia="null"
                                            collectionName="coupon_image"
                                            label="Coupon Image"
                                            :multiple="false"
                                            accept="image/*"
                                            :maxFileSizeMb="5"
                                        />
                                        <p class="mt-2 text-sm text-gray-500">Upload an optional image for this coupon (max 5MB)</p>
                                        <div v-if="form.errors.uploaded_image" class="input-error mt-1">{{ form.errors.uploaded_image }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-8 flex justify-end">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition"
                                >
                                    <span v-if="form.processing">Creating...</span>
                                    <span v-else>Create Coupon</span>
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import { getTranslation } from '@/Utils/i18n';
import PageHeader from '@/components/Shared/PageHeader.vue';
import MediaUpload from '@/components/Form/MediaUpload.vue';

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

interface Organizer {
    id: number;
    name: Record<string, string> | string;
}

const props = defineProps<{
    organizers: Organizer[];
}>();

const form = useForm({
    name: '',
    code: '',
    description: '',
    organizer_id: '',
    type: 'single_use',
    discount_type: 'percentage',
    discount_value: null as number | null,
    max_issuance: null as number | null,
    valid_from: '',
    expires_at: '',
    uploaded_image: null as File | null,
});

const generateCode = () => {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 8; i++) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    form.code = result;
};

const submitForm = () => {
    // Convert discount value to cents if it's a fixed amount
    const processedForm = { ...form.data() };
    if (processedForm.discount_type === 'fixed' && processedForm.discount_value) {
        processedForm.discount_value = Math.round(processedForm.discount_value * 100);
    }

    form.post(route('admin.coupons.store'));
};
</script>

<style scoped>
.input-error {
    @apply text-red-600 text-sm;
}
</style>
