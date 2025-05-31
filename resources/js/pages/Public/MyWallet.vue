<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';

// Assuming a layout similar to MyBookings, potentially PublicLayout or AppLayout
// For now, using a generic div, this should be replaced with the correct layout component
// import PublicLayout from '@/Layouts/PublicLayout.vue';

import type { WalletBalance, WalletTransaction } from '@/types/wallet'; // Define these types later

dayjs.extend(utc);

const props = defineProps({
  balance: {
    type: Object as () => WalletBalance,
    required: true,
  },
  transactions: {
    type: Object as () => ({ data: WalletTransaction[], links: any[], meta: any }), // Basic pagination structure
    required: true,
  },
});

// State for managing which transactions to show
const activeFilter = ref('all'); // 'all', 'points', 'kill_points', 'earned', 'spent'

const filteredTransactions = computed(() => {
  if (!props.transactions?.data) return [];

  // Basic filtering logic, can be expanded
  switch (activeFilter.value) {
    case 'points':
      return props.transactions.data.filter(t => t.transaction_type.includes('_points') && !t.transaction_type.includes('kill_points'));
    case 'kill_points':
      return props.transactions.data.filter(t => t.transaction_type.includes('_kill_points'));
    case 'earned':
      return props.transactions.data.filter(t => t.transaction_type.startsWith('earn_') || t.transaction_type === 'transfer_in' || t.transaction_type === 'bonus' || t.transaction_type === 'refund');
    case 'spent':
      return props.transactions.data.filter(t => t.transaction_type.startsWith('spend_') || t.transaction_type === 'transfer_out' || t.transaction_type === 'penalty' || t.transaction_type === 'membership_purchase');
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
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Fetch initial data if needed (though Inertia props should handle it)
// onMounted(() => {
//   if (!props.balance || !props.transactions) {
//     router.reload({ only: ['balance', 'transactions'] });
//   }
// });

</script>

<template>
  <Head title="My Wallet" />

  <!-- Replace with actual Layout component -->
  <!-- <PublicLayout title="My Wallet"> -->
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <!-- Header Section -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50 border-b dark:border-gray-700">
      <div class="container mx-auto flex items-center p-4 relative">
        <Link href="/" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 absolute left-4">
          &larr; Back
        </Link>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 flex-1 text-center">My Wallet</h1>
      </div>
    </header>

    <main class="container mx-auto py-6 px-4 pb-24">
      <!-- Balance Section -->
      <section class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
          <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">Points Balance</h2>
          <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ props.balance.points_balance?.toLocaleString() || 0 }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Earned: {{ props.balance.total_points_earned?.toLocaleString() || 0 }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">Total Spent: {{ props.balance.total_points_spent?.toLocaleString() || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
          <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">Kill Points Balance</h2>
          <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-1">{{ props.balance.kill_points_balance?.toLocaleString() || 0 }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Earned: {{ props.balance.total_kill_points_earned?.toLocaleString() || 0 }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">Total Spent: {{ props.balance.total_kill_points_spent?.toLocaleString() || 0 }}</p>
        </div>
      </section>

      <!-- Placeholder: Top-up Points Section -->
      <section class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Top-up Points</h2>
        <p class="text-gray-600 dark:text-gray-300">This section will allow users to purchase more points. (Coming Soon)</p>
        <!-- Add top-up options and payment integration here -->
      </section>

      <!-- Transaction History Section -->
      <section>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4 sm:mb-0">Transaction History</h2>
          <div class="flex space-x-2 text-sm flex-wrap">
            <button @click="setFilter('all')" :class="['px-3 py-1 rounded-full font-medium', activeFilter === 'all' ? 'bg-indigo-100 dark:bg-indigo-700 text-indigo-700 dark:text-indigo-200' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
              All
            </button>
            <button @click="setFilter('points')" :class="['px-3 py-1 rounded-full font-medium', activeFilter === 'points' ? 'bg-indigo-100 dark:bg-indigo-700 text-indigo-700 dark:text-indigo-200' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
              Points
            </button>
            <button @click="setFilter('kill_points')" :class="['px-3 py-1 rounded-full font-medium', activeFilter === 'kill_points' ? 'bg-red-100 dark:bg-red-700 text-red-700 dark:text-red-200' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
              Kill Points
            </button>
             <button @click="setFilter('earned')" :class="['px-3 py-1 rounded-full font-medium', activeFilter === 'earned' ? 'bg-green-100 dark:bg-green-700 text-green-700 dark:text-green-200' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
              Earned
            </button>
            <button @click="setFilter('spent')" :class="['px-3 py-1 rounded-full font-medium', activeFilter === 'spent' ? 'bg-yellow-100 dark:bg-yellow-700 text-yellow-700 dark:text-yellow-200' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
              Spent
            </button>
          </div>
        </div>

        <div v-if="filteredTransactions.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
          <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <li v-for="transaction in filteredTransactions" :key="transaction.id" class="px-4 py-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
              <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400 truncate">{{ getTransactionTypeLabel(transaction.transaction_type) }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ transaction.description }}</p>
                </div>
                <div class="ml-4 text-right flex-shrink-0">
                  <p class="text-sm font-semibold"
                     :class="{
                       'text-green-600 dark:text-green-400': transaction.transaction_type.startsWith('earn_') || transaction.transaction_type === 'transfer_in' || transaction.transaction_type === 'bonus' || transaction.transaction_type === 'refund',
                       'text-red-600 dark:text-red-400': transaction.transaction_type.startsWith('spend_') || transaction.transaction_type === 'transfer_out' || transaction.transaction_type === 'penalty' || transaction.transaction_type === 'membership_purchase',
                       'text-gray-700 dark:text-gray-300': !(transaction.transaction_type.startsWith('earn_') || transaction.transaction_type.startsWith('spend_') || transaction.transaction_type.includes('transfer'))
                     }"
                  >
                    {{ formatAmount(transaction.amount, transaction.transaction_type) }}
                    <span class="text-xs">{{ transaction.transaction_type.includes('_kill_points') ? 'KP' : 'Pts' }}</span>
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatTransactionDate(transaction.created_at) }}</p>
                </div>
              </div>
            </li>
          </ul>
        </div>
        <div v-else class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="text-6xl mb-4">&#x1F4B3;</div> <!-- Credit Card Emoji -->
          <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">No transactions found</h3>
          <p class="text-gray-600 dark:text-gray-300">
            <span v-if="activeFilter !== 'all'">No transactions match the current filter.</span>
            <span v-else>You haven't made any transactions yet.</span>
          </p>
        </div>
        <!-- Pagination (placeholder) -->
        <!-- <Pagination :links="props.transactions.links" :meta="props.transactions.meta" class="mt-6" /> -->
      </section>

      <!-- Placeholder: Spending Charts Section -->
      <section class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Spending Overview</h2>
        <p class="text-gray-600 dark:text-gray-300">Charts and graphs visualizing spending patterns will be displayed here. (Coming Soon)</p>
        <!-- Add chart components here -->
      </section>

    </main>

    <!-- Fixed Footer/Bottom Bar (Example from MyBookings, adapt as needed) -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-3 shadow-top-lg z-50">
      <div class="container mx-auto flex justify-around items-center">
        <Link href="/" class="text-center text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300">
          <span class="block text-xl">&#x1F3E0;</span> <!-- Home Icon -->
          <span>Home</span>
        </Link>
        <Link :href="route('my-bookings')" class="text-center text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300">
            <span class="block text-xl">&#x1F39F;&#xFE0F;</span> <!-- Ticket Icon -->
            <span>My Bookings</span>
        </Link>
        <Link :href="route('my-wishlist')" class="text-center text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300">
            <span class="block text-xl">&#x2764;&#xFE0F;</span> <!-- Heart Icon -->
            <span>My Wishlist</span>
        </Link>
        <!-- Add other relevant links if needed -->
      </div>
    </footer>

  </div>
  <!-- </PublicLayout> -->
</template>

<style scoped>
.shadow-top-lg {
  box-shadow: 0 -4px 6px -1px rgb(0 0 0 / 0.05), 0 -2px 4px -2px rgb(0 0 0 / 0.05);
}
</style>
