<template>
    <Head :title="`User: ${user.name}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="`User: ${user.name}`" :subtitle="user.email">
                            <template #actions>
                                <Link :href="route('admin.users.edit', user.id)" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    Edit User
                                </Link>
                            </template>
                        </PageHeader>

                        <!-- User Basic Information -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Mobile Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.mobile_number || 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email Verified</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        <span :class="user.email_verified_at ? 'text-green-600' : 'text-red-600'">
                                            {{ user.email_verified_at ? formatDate(user.email_verified_at) : 'Not verified' }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Commenting Blocked</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        <span :class="user.is_commenting_blocked ? 'text-red-600' : 'text-green-600'">
                                            {{ user.is_commenting_blocked ? 'Yes' : 'No' }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Member Since</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(user.created_at) }}</dd>
                                </div>
                            </div>
                        </div>

                        <!-- Current Membership -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Current Membership</h3>
                            <div v-if="currentMembership" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Level</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ getLevelName(currentMembership.level_name) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                        <dd class="mt-1">
                                            <span :class="getStatusClass(currentMembership.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ currentMembership.status }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Method</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ currentMembership.payment_method }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Started</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(currentMembership.started_at) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ currentMembership.expires_at ? formatDate(currentMembership.expires_at) : 'Never' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Auto Renew</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            <span :class="currentMembership.auto_renew ? 'text-green-600' : 'text-gray-600'">
                                                {{ currentMembership.auto_renew ? 'Yes' : 'No' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div v-if="currentMembership.stripe_subscription_id" class="md:col-span-2 lg:col-span-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stripe Subscription ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ currentMembership.stripe_subscription_id }}</dd>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">No active membership</p>
                            </div>
                        </div>

                        <!-- Membership History -->
                        <div v-if="membershipHistory.length > 0" class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Membership History</h3>
                            <AdminDataTable>
                                <template #header>
                                    <TableHead>Level</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Started</TableHead>
                                    <TableHead>Expires</TableHead>
                                    <TableHead>Payment Method</TableHead>
                                    <TableHead>Stripe Subscription</TableHead>
                                </template>
                                <template #body>
                                    <TableRow v-for="membership in membershipHistory" :key="membership.id">
                                        <TableCell class="font-medium text-gray-900 dark:text-white">
                                            {{ getLevelName(membership.level_name) }}
                                        </TableCell>
                                        <TableCell>
                                            <span :class="getStatusClass(membership.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ membership.status }}
                                            </span>
                                        </TableCell>
                                        <TableCell>{{ formatDate(membership.started_at) }}</TableCell>
                                        <TableCell>{{ membership.expires_at ? formatDate(membership.expires_at) : 'Never' }}</TableCell>
                                        <TableCell>{{ membership.payment_method }}</TableCell>
                                        <TableCell>
                                            <code v-if="membership.stripe_subscription_id" class="text-xs bg-gray-100 dark:bg-gray-600 px-2 py-1 rounded">
                                                {{ membership.stripe_subscription_id }}
                                            </code>
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">N/A</span>
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </AdminDataTable>
                        </div>

                        <!-- Organizer Information -->
                        <div v-if="organizerInfo.length > 0" class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Organizer Memberships</h3>
                            <AdminDataTable>
                                <template #header>
                                    <TableHead>Organization</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Joined</TableHead>
                                </template>
                                <template #body>
                                    <TableRow v-for="organizer in organizerInfo" :key="organizer.id">
                                        <TableCell class="font-medium text-gray-900 dark:text-white">{{ getTranslation(organizer.name) }}</TableCell>
                                        <TableCell>{{ formatRole(organizer.role) }}</TableCell>
                                        <TableCell>
                                            <span :class="organizer.is_active ? 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ organizer.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </TableCell>
                                        <TableCell>{{ formatDate(organizer.joined_at) }}</TableCell>
                                    </TableRow>
                                </template>
                            </AdminDataTable>
                        </div>

                        <!-- Wallet Information -->
                        <div v-if="walletInfo" class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Wallet Balance</h3>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Points Balance</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ walletInfo.points_balance.toLocaleString() }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Kill Points Balance</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ walletInfo.kill_points_balance.toLocaleString() }}</dd>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Transactions -->
                        <div v-if="recentTransactions.length > 0" class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Transactions</h3>
                            <AdminDataTable>
                                <template #header>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Date</TableHead>
                                </template>
                                <template #body>
                                    <TableRow v-for="transaction in recentTransactions" :key="transaction.id">
                                        <TableCell class="font-medium text-gray-900 dark:text-white">
                                            <span :class="transaction.amount >= 0 ? 'text-green-600' : 'text-red-600'">
                                                {{ transaction.amount >= 0 ? '+' : '' }}{{ formatPrice(Math.abs(transaction.amount), null) }}
                                            </span>
                                        </TableCell>
                                        <TableCell>{{ transaction.type }}</TableCell>
                                        <TableCell>{{ transaction.description || 'N/A' }}</TableCell>
                                        <TableCell>{{ formatDate(transaction.created_at) }}</TableCell>
                                    </TableRow>
                                </template>
                            </AdminDataTable>
                        </div>

                        <!-- Stripe Information -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stripe Information</h3>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Primary Customer ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ stripeInfo.customer_id || 'N/A' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Has Payment Method</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            <span :class="stripeInfo.has_payment_method ? 'text-green-600' : 'text-red-600'">
                                                {{ stripeInfo.has_payment_method ? 'Yes' : 'No' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div v-if="stripeInfo.all_customer_ids.length > 1" class="md:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">All Customer IDs</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            <div class="space-y-1">
                                                <div v-for="customerId in stripeInfo.all_customer_ids" :key="customerId" class="font-mono text-xs bg-gray-100 dark:bg-gray-600 px-2 py-1 rounded inline-block mr-2">
                                                    {{ customerId }}
                                                </div>
                                            </div>
                                        </dd>
                                    </div>
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
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { TableHead, TableRow, TableCell } from '@/components/ui/table';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import { useCurrency } from '@/composables/useCurrency';

interface User {
    id: number;
    name: string;
    email: string;
    mobile_number?: string;
    is_commenting_blocked: boolean;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
}

interface CurrentMembership {
    id: number;
    level_name: Record<string, string>;
    level_id: number;
    status: string;
    started_at: string;
    expires_at?: string;
    payment_method: string;
    auto_renew: boolean;
    stripe_subscription_id?: string;
    subscription_metadata?: Record<string, any>;
}

interface MembershipHistory {
    id: number;
    level_name: Record<string, string>;
    status: string;
    started_at: string;
    expires_at?: string;
    payment_method: string;
    auto_renew: boolean;
    stripe_subscription_id?: string;
}

interface OrganizerInfo {
    id: number;
    name: Record<string, string>;
    role: string;
    is_active: boolean;
    joined_at: string;
}

interface WalletInfo {
    points_balance: number;
    kill_points_balance: number;
}

interface Transaction {
    id: number;
    amount: number;
    type: string;
    description?: string;
    created_at: string;
}

interface StripeInfo {
    customer_id?: string;
    all_customer_ids: string[];
    has_payment_method: boolean;
}

defineProps<{
    user: User;
    currentMembership?: CurrentMembership;
    membershipHistory: MembershipHistory[];
    organizerInfo: OrganizerInfo[];
    walletInfo?: WalletInfo;
    recentTransactions: Transaction[];
    stripeInfo: StripeInfo;
}>();

const { formatPrice } = useCurrency();

const formatDate = (dateString: string): string => {
    const options: Intl.DateTimeFormatOptions = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
};

const getLevelName = (levelName: Record<string, string> | string): string => {
    if (typeof levelName === 'string') return levelName;
    return levelName.en || Object.values(levelName)[0] || 'Unknown';
};

const getTranslation = (translations: Record<string, string> | string): string => {
    if (typeof translations === 'string') return translations;
    return translations.en || Object.values(translations)[0] || 'Unknown';
};

const getStatusClass = (status: string): string => {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200';
        case 'cancelled': return 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-200';
        case 'expired': return 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200';
        case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-600 dark:text-yellow-100';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200';
    }
};

const formatRole = (role: string): string => {
    return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>