<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import { computed, ref } from 'vue';

// Assuming a layout similar to MyBookings, potentially PublicLayout or AppLayout
// For now, using a generic div, this should be replaced with the correct layout component
// import PublicLayout from '@/Layouts/PublicLayout.vue';
import type { WalletBalance, WalletTransaction } from '@/types/wallet'; // Define these types later
import BottomNavbar from '../../components/Public/BottomNavbar.vue';

dayjs.extend(utc);

const props = defineProps({
    balance: {
        type: Object as () => WalletBalance,
        required: true
    },
    transactions: {
        type: Object as () => { data: WalletTransaction[]; links: any[]; meta: any }, // Basic pagination structure
        required: true
    },
    code: {
        type: String,
        required: true
    }
});

// State for managing which transactions to show
const activeFilter = ref('all'); // 'all', 'points', 'kill_points', 'earned', 'spent'

const filteredTransactions = computed(() => {
    if (!props.transactions?.data) return [];

    // Basic filtering logic, can be expanded
    switch (activeFilter.value) {
        case 'points':
            return props.transactions.data.filter((t) => t.transaction_type.includes('_points') && !t.transaction_type.includes('kill_points'));
        case 'kill_points':
            return props.transactions.data.filter((t) => t.transaction_type.includes('_kill_points'));
        case 'earned':
            return props.transactions.data.filter(
                (t) =>
                    t.transaction_type.startsWith('earn_') ||
                    t.transaction_type === 'transfer_in' ||
                    t.transaction_type === 'bonus' ||
                    t.transaction_type === 'refund'
            );
        case 'spent':
            return props.transactions.data.filter(
                (t) =>
                    t.transaction_type.startsWith('spend_') ||
                    t.transaction_type === 'transfer_out' ||
                    t.transaction_type === 'penalty' ||
                    t.transaction_type === 'membership_purchase'
            );
        case 'all':
        default:
            return props.transactions.data;
    }
});

function setFilter(filter: string) {
    activeFilter.value = filter;
    // Potentially reload data from backend with filter
    // router.get(route('my-wallet'), { filter: filter }, { preserveState: true, preserveScroll: true });
}

function formatTransactionDate(dateString?: string): string {
    if (!dateString) return 'N/A';
    return dayjs(dateString).utc().format('MMM DD, YYYY h:mm A');
}

function formatAmount(amount: number, type: string): string {
    const isCredit = type.startsWith('earn_') || type === 'transfer_in' || type === 'bonus' || type === 'refund';
    return `${isCredit ? '+' : '-'} ${Math.abs(amount)}`;
}

function getTransactionTypeLabel(type: string): string {
    // Simple mapping, can be more sophisticated
    return type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
}

console.log('Wallet Page Loaded', {
    balance: props.balance,
    transactions: props.transactions,
    code: props.code
});
</script>

<template>
    <Head title="My Wallet" />

    <!-- Replace with actual Layout component -->
    <!-- <PublicLayout title="My Wallet"> -->
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Header Section -->
        <header class="sticky top-0 z-50 border-b bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="relative container mx-auto flex items-center p-4">
                <Link href="/"
                      class="absolute left-4 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                    &larr; Back
                </Link>
                <h1 class="flex-1 text-center text-xl font-semibold text-gray-900 dark:text-gray-100">My Wallet</h1>
            </div>
        </header>

        <main class="container mx-auto px-4 py-6 pb-24">
            <!-- My Membership Card with QR Code  -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">My Membership Card</h2>
                    <Link
                        :href="route('my-membership')"
                        class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        View Membership Details
                    </Link>
                </div>
                <div class="mt-4 flex items-center justify-center">
                    <img
                        :src="`https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(props.code)}&size=300x300`"
                        alt="Membership QR Code"
                        class="h-32 w-32 rounded-lg shadow-md"
                    />
                </div>
                <!--                <p class="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">-->
                <!--                    Scan the QR code to access your membership details and wallet balance.-->
                <!--                </p>-->
            </section>

            <!-- Balance Section -->
            <!--            <section class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2">-->
            <!--                <div class="rounded-lg bg-white p-6 text-center shadow dark:bg-gray-800">-->
            <!--                    <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">Points Balance</h2>-->
            <!--                    <p class="mt-1 text-3xl font-bold text-indigo-600 dark:text-indigo-400">-->
            <!--                        {{ props.balance.points_balance?.toLocaleString() || 0 }}-->
            <!--                    </p>-->
            <!--                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">-->
            <!--                        Total Earned: {{ props.balance.total_points_earned?.toLocaleString() || 0 }}-->
            <!--                    </p>-->
            <!--                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Spent: {{ props.balance.total_points_spent?.toLocaleString() || 0 }}</p>-->
            <!--                </div>-->
            <!--                <div class="rounded-lg bg-white p-6 text-center shadow dark:bg-gray-800">-->
            <!--                    <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">Kill Points Balance</h2>-->
            <!--                    <p class="mt-1 text-3xl font-bold text-red-600 dark:text-red-400">-->
            <!--                        {{ props.balance.kill_points_balance?.toLocaleString() || 0 }}-->
            <!--                    </p>-->
            <!--                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">-->
            <!--                        Total Earned: {{ props.balance.total_kill_points_earned?.toLocaleString() || 0 }}-->
            <!--                    </p>-->
            <!--                    <p class="text-xs text-gray-500 dark:text-gray-400">-->
            <!--                        Total Spent: {{ props.balance.total_kill_points_spent?.toLocaleString() || 0 }}-->
            <!--                    </p>-->
            <!--                </div>-->
            <!--            </section>-->

            <!-- Placeholder: Top-up Points Section -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Top-up Points</h2>
                <p class="text-gray-600 dark:text-gray-300">This section will allow users to purchase more points.
                    (Coming Soon)</p>
                <!-- Add top-up options and payment integration here -->
            </section>

            <!-- Transaction History Section -->
            <section>
                <div class="mb-6 flex flex-col items-start justify-between sm:flex-row sm:items-center">
                    <h2 class="mb-4 text-2xl font-semibold text-gray-800 sm:mb-0 dark:text-gray-200">Transaction
                        History</h2>
                    <div class="flex flex-wrap space-x-2 text-sm">
                        <button
                            @click="setFilter('all')"
                            :class="[
                                'rounded-full px-3 py-1 font-medium',
                                activeFilter === 'all'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-700 dark:text-indigo-200'
                                    : 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            All
                        </button>
                        <button
                            @click="setFilter('points')"
                            :class="[
                                'rounded-full px-3 py-1 font-medium',
                                activeFilter === 'points'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-700 dark:text-indigo-200'
                                    : 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            Points
                        </button>
                        <button
                            @click="setFilter('kill_points')"
                            :class="[
                                'rounded-full px-3 py-1 font-medium',
                                activeFilter === 'kill_points'
                                    ? 'bg-red-100 text-red-700 dark:bg-red-700 dark:text-red-200'
                                    : 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            Kill Points
                        </button>
                        <button
                            @click="setFilter('earned')"
                            :class="[
                                'rounded-full px-3 py-1 font-medium',
                                activeFilter === 'earned'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-700 dark:text-green-200'
                                    : 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            Earned
                        </button>
                        <button
                            @click="setFilter('spent')"
                            :class="[
                                'rounded-full px-3 py-1 font-medium',
                                activeFilter === 'spent'
                                    ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-700 dark:text-yellow-200'
                                    : 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            Spent
                        </button>
                    </div>
                </div>

                <div v-if="filteredTransactions.length > 0"
                     class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li
                            v-for="transaction in filteredTransactions"
                            :key="transaction.id"
                            class="px-4 py-4 transition-colors duration-150 hover:bg-gray-50 sm:px-6 dark:hover:bg-gray-700/50"
                        >
                            <div class="flex items-center justify-between">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                        {{ getTransactionTypeLabel(transaction.transaction_type) }}
                                    </p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ transaction.description }}
                                    </p>
                                </div>
                                <div class="ml-4 flex-shrink-0 text-right">
                                    <p
                                        class="text-sm font-semibold"
                                        :class="{
                                            'text-green-600 dark:text-green-400':
                                                transaction.transaction_type.startsWith('earn_') ||
                                                transaction.transaction_type === 'transfer_in' ||
                                                transaction.transaction_type === 'bonus' ||
                                                transaction.transaction_type === 'refund',
                                            'text-red-600 dark:text-red-400':
                                                transaction.transaction_type.startsWith('spend_') ||
                                                transaction.transaction_type === 'transfer_out' ||
                                                transaction.transaction_type === 'penalty' ||
                                                transaction.transaction_type === 'membership_purchase',
                                            'text-gray-700 dark:text-gray-300': !(
                                                transaction.transaction_type.startsWith('earn_') ||
                                                transaction.transaction_type.startsWith('spend_') ||
                                                transaction.transaction_type.includes('transfer')
                                            ),
                                        }"
                                    >
                                        {{ formatAmount(transaction.amount, transaction.transaction_type) }}
                                        <span
                                            class="text-xs">{{ transaction.transaction_type.includes('_kill_points') ? 'KP' : 'Pts'
                                            }}</span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatTransactionDate(transaction.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                <div v-else class="rounded-lg bg-white py-12 text-center shadow dark:bg-gray-800">
                    <div class="mb-4 text-6xl">&#x1F4B3;</div>
                    <!-- Credit Card Emoji -->
                    <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-gray-100">No transactions found</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        <span v-if="activeFilter !== 'all'">No transactions match the current filter.</span>
                        <span v-else>You haven't made any transactions yet.</span>
                    </p>
                </div>
                <!-- Pagination (placeholder) -->
                <!-- <Pagination :links="props.transactions.links" :meta="props.transactions.meta" class="mt-6" /> -->
            </section>

            <!-- Placeholder: Spending Charts Section -->
            <section class="mt-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Spending Overview</h2>
                <p class="text-gray-600 dark:text-gray-300">Charts and graphs visualizing spending patterns will be
                    displayed here. (Coming Soon)</p>
                <!-- Add chart components here -->
            </section>
        </main>
        <BottomNavbar />
    </div>
    <!-- </PublicLayout> -->
</template>

<style scoped>
.shadow-top-lg {
    box-shadow: 0 -4px 6px -1px rgb(0 0 0 / 0.05),
    0 -2px 4px -2px rgb(0 0 0 / 0.05);
}
</style>
