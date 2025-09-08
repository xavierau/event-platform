<template>
    <Head title="Edit Coupon" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="`Edit Coupon: ${form.name}`" subtitle="Update the details of the coupon template">
                            <template #actions>
                                <Link :href="route('admin.coupons.index')" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Back to Coupons
                                </Link>
                            </template>
                        </PageHeader>

                        <div class="mt-8 max-w-4xl">
                            <form @submit.prevent="updateCoupon" class="space-y-6">
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <!-- Basic Information -->
                                    <div class="space-y-6">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Basic Information</h3>

                                        <!-- Organizer Selection -->
                                        <div>
                                            <label for="organizer_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organizer *</label>
                                            <select
                                                v-model="form.organizer_id"
                                                id="organizer_id"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                :class="{ 'border-red-500': form.errors.organizer_id }"
                                            >
                                                <option value="">Select an organizer</option>
                                                <option v-for="organizer in organizers" :key="organizer.id" :value="organizer.id">
                                                    {{ getTranslation(organizer.name, currentLocale) }}
                                                </option>
                                            </select>
                                            <p v-if="form.errors.organizer_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.organizer_id }}</p>
                                        </div>

                                        <!-- Coupon Name -->
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coupon Name *</label>
                                            <input
                                                type="text"
                                                v-model="form.name"
                                                id="name"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                :class="{ 'border-red-500': form.errors.name }"
                                                placeholder="Enter coupon name"
                                            >
                                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                                        </div>

                                        <!-- Coupon Code -->
                                        <div>
                                            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coupon Code *</label>
                                            <div class="mt-1 flex rounded-md shadow-sm">
                                                <input
                                                    type="text"
                                                    v-model="form.code"
                                                    id="code"
                                                    class="flex-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-l-md focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                    :class="{ 'border-red-500': form.errors.code }"
                                                    placeholder="Enter unique code"
                                                >
                                                <button
                                                    type="button"
                                                    @click="generateRandomCode"
                                                    class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-300 text-sm rounded-r-md hover:bg-gray-100 dark:hover:bg-gray-500"
                                                >
                                                    Generate
                                                </button>
                                            </div>
                                            <p v-if="form.errors.code" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.code }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Must be unique across all coupons</p>
                                        </div>

                                        <!-- Description -->
                                        <div>
                                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                            <textarea
                                                v-model="form.description"
                                                id="description"
                                                rows="3"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                :class="{ 'border-red-500': form.errors.description }"
                                                placeholder="Optional description for the coupon"
                                            ></textarea>
                                            <p v-if="form.errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.description }}</p>
                                        </div>
                                    </div>

                                    <!-- Discount Configuration -->
                                    <div class="space-y-6">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Discount Configuration</h3>

                                        <!-- Coupon Type -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coupon Type *</label>
                                            <div class="mt-2 space-y-2">
                                                <label class="inline-flex items-center">
                                                    <input
                                                        type="radio"
                                                        v-model="form.type"
                                                        value="single_use"
                                                        class="form-radio text-indigo-600"
                                                    >
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Single Use - Each coupon can only be used once</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input
                                                        type="radio"
                                                        v-model="form.type"
                                                        value="multi_use"
                                                        class="form-radio text-indigo-600"
                                                    >
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Multi Use - Each coupon can be used multiple times</span>
                                                </label>
                                            </div>
                                            <p v-if="form.errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.type }}</p>
                                        </div>

                                        <!-- Discount Type -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Type *</label>
                                            <div class="mt-2 space-y-2">
                                                <label class="inline-flex items-center">
                                                    <input
                                                        type="radio"
                                                        v-model="form.discount_type"
                                                        value="percentage"
                                                        class="form-radio text-indigo-600"
                                                    >
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Percentage - Discount as a percentage</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input
                                                        type="radio"
                                                        v-model="form.discount_type"
                                                        value="fixed"
                                                        class="form-radio text-indigo-600"
                                                    >
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fixed Amount - Discount as a fixed amount</span>
                                                </label>
                                            </div>
                                            <p v-if="form.errors.discount_type" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.discount_type }}</p>
                                        </div>

                                        <!-- Discount Value -->
                                        <div>
                                            <label for="discount_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Discount Value *
                                                <span v-if="form.discount_type === 'percentage'" class="text-gray-500">(Percentage 1-100)</span>
                                                <span v-else-if="form.discount_type === 'fixed'" class="text-gray-500">(Amount in cents)</span>
                                            </label>
                                            <div class="mt-1 relative rounded-md shadow-sm">
                                                <input
                                                    type="number"
                                                    v-model.number="form.discount_value"
                                                    id="discount_value"
                                                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                    :class="{ 'border-red-500': form.errors.discount_value }"
                                                    :placeholder="form.discount_type === 'percentage' ? 'e.g., 10 for 10%' : 'e.g., 500 for $5.00'"
                                                    :min="1"
                                                    :max="form.discount_type === 'percentage' ? 100 : undefined"
                                                >
                                                <div v-if="form.discount_type === 'percentage'" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 sm:text-sm">%</span>
                                                </div>
                                                <div v-else-if="form.discount_type === 'fixed'" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 sm:text-sm">$</span>
                                                </div>
                                            </div>
                                            <p v-if="form.errors.discount_value" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.discount_value }}</p>
                                            <p v-if="form.discount_type === 'fixed'" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                Enter amount in cents (e.g., 500 = $5.00)
                                            </p>
                                        </div>

                                        <!-- Max Issuance -->
                                        <div>
                                            <label for="max_issuance" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Maximum Issuance</label>
                                            <input
                                                type="number"
                                                v-model.number="form.max_issuance"
                                                id="max_issuance"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                :class="{ 'border-red-500': form.errors.max_issuance }"
                                                placeholder="Leave empty for unlimited"
                                                min="1"
                                            >
                                            <p v-if="form.errors.max_issuance" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.max_issuance }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Maximum number of times this coupon can be issued to users</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Validity Period -->
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Validity Period</h3>
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <!-- Valid From -->
                                        <div>
                                            <label for="valid_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valid From</label>
                                            <input
                                                type="datetime-local"
                                                v-model="form.valid_from"
                                                id="valid_from"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                :class="{ 'border-red-500': form.errors.valid_from }"
                                            >
                                            <p v-if="form.errors.valid_from" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.valid_from }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leave empty if valid immediately</p>
                                        </div>

                                        <!-- Expires At -->
                                        <div>
                                            <label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expires At</label>
                                            <input
                                                type="datetime-local"
                                                v-model="form.expires_at"
                                                id="expires_at"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                :class="{ 'border-red-500': form.errors.expires_at }"
                                            >
                                            <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.expires_at }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leave empty if never expires</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                    <div class="flex justify-end space-x-3">
                                        <Link
                                            :href="route('admin.coupons.index')"
                                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-3"
                                        >
                                            Cancel
                                        </Link>
                                        <button
                                            type="submit"
                                            :disabled="form.processing"
                                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-3 disabled:opacity-50"
                                        >
                                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            {{ form.processing ? 'Updating...' : 'Update Coupon' }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
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
import { getTranslation } from '@/Utils/i18n';
import PageHeader from '@/components/Shared/PageHeader.vue';

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

interface Organizer {
    id: number;
    name: Record<string, string> | string;
}

interface CouponData {
    id: number;
    organizer_id: number;
    name: string;
    description: string | null;
    code: string;
    type: 'single_use' | 'multi_use';
    discount_value: number;
    discount_type: 'fixed' | 'percentage';
    max_issuance: number | null;
    valid_from: string | null;
    expires_at: string | null;
}

const props = defineProps<{
    coupon: CouponData;
    organizers: Organizer[];
}>();

const formatDateForInput = (dateString: string | null) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
};

const form = useForm({
    organizer_id: props.coupon.organizer_id,
    name: props.coupon.name,
    description: props.coupon.description,
    code: props.coupon.code,
    type: props.coupon.type,
    discount_value: props.coupon.discount_value,
    discount_type: props.coupon.discount_type,
    max_issuance: props.coupon.max_issuance,
    valid_from: formatDateForInput(props.coupon.valid_from),
    expires_at: formatDateForInput(props.coupon.expires_at),
});

const generateRandomCode = () => {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 8; i++) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    form.code = result;
};

const updateCoupon = () => {
    form.put(route('admin.coupons.update', props.coupon.id), {
        onSuccess: () => {
            // Redirect will be handled by the controller
        },
    });
};
</script>

<style scoped>
input[type="radio"] {
    @apply focus:ring-2 focus:ring-2 focus:ring-3 focus:ring-offset-2 ;
}
</style>
