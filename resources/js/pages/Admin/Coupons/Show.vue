<template>
    <Head :title="`Coupon: ${coupon.name}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="`Coupon Details: ${coupon.name}`" :subtitle="coupon.code">
                            <template #actions>
                                <Link :href="route('admin.coupons.edit', coupon.id)" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    Edit Coupon
                                </Link>
                                <Link :href="route('admin.coupons.index')" class="ml-3 inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                    Back to Coupons
                                </Link>
                            </template>
                        </PageHeader>

                        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-8">
                            <!-- Left Column: Coupon Details -->
                            <div class="md:col-span-2 space-y-6">
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg shadow">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Coupon Information</h3>
                                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                                        <div class="sm:col-span-2">
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ coupon.description || 'No description provided.' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Organizer</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ getTranslation(coupon.organizer.name, currentLocale) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Coupon Type</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatType(coupon.type) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Discount</dt>
                                            <dd class="mt-1 text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ formatDiscount(coupon.discount_value, coupon.discount_type) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Maximum Issuance</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ coupon.max_issuance || 'Unlimited' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Valid From</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(coupon.valid_from) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires At</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(coupon.expires_at) }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Issued Coupons List -->
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg shadow">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Issued Coupons ({{ statistics.total_issued }})</h3>
                                    <div class="max-h-96 overflow-y-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-100 dark:bg-gray-800">
                                                <tr>
                                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unique Code</th>
                                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issued At</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                <tr v-if="coupon.user_coupons.length === 0">
                                                    <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">No coupons issued yet.</td>
                                                </tr>
                                                <tr v-for="userCoupon in coupon.user_coupons" :key="userCoupon.id">
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ userCoupon.user.name }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-indigo-600 dark:text-indigo-400">{{ userCoupon.unique_code }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                        <span :class="statusClass(userCoupon.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                            {{ userCoupon.status }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(userCoupon.created_at) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Statistics -->
                            <div class="space-y-6">
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg shadow">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Usage Statistics</h3>
                                    <dl class="space-y-4">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Issued</dt>
                                            <dd class="mt-1 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ statistics.total_issued }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Redeemed</dt>
                                            <dd class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ statistics.total_redeemed }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Redemption Rate</dt>
                                            <dd class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ statistics.redemption_rate }}%</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Remaining for Issuance</dt>
                                            <dd class="mt-1 text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ statistics.remaining_for_issuance }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { getTranslation } from '@/Utils/i18n';
import PageHeader from '@/components/Shared/PageHeader.vue';

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

interface Organizer {
    id: number;
    name: Record<string, string> | string;
}

interface User {
    id: number;
    name: string;
}

interface UserCoupon {
    id: number;
    unique_code: string;
    status: 'ACTIVE' | 'FULLY_USED' | 'EXPIRED';
    created_at: string;
    user: User;
}

interface Coupon {
    id: number;
    name: string;
    code: string;
    description: string | null;
    type: 'single_use' | 'multi_use';
    discount_value: number;
    discount_type: 'fixed' | 'percentage';
    max_issuance: number | null;
    valid_from: string | null;
    expires_at: string | null;
    organizer: Organizer;
    user_coupons: UserCoupon[];
}

interface Statistics {
    total_issued: number;
    total_redeemed: number;
    redemption_rate: string;
    remaining_for_issuance: string;
}

defineProps<{
    coupon: Coupon;
    statistics: Statistics;
}>();

const formatDate = (dateString: string | null): string => {
    if (!dateString) return 'N/A';
    const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString(currentLocale.value, options);
};

const formatType = (type: string): string => {
    return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatDiscount = (value: number, type: string): string => {
    if (type === 'percentage') {
        return `${value}% OFF`;
    }
    return `$${(value / 100).toFixed(2)} OFF`;
};

const statusClass = (status: string): string => {
    switch (status) {
        case 'ACTIVE': return 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200';
        case 'FULLY_USED': return 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-200';
        case 'EXPIRED': return 'bg-red-100 text-red-800 dark:bg-red-600 dark:text-red-100';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-500 dark:text-gray-100';
    }
};
</script>

<style scoped>
/* Scoped styles if needed */
</style>
