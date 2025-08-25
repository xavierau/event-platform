<template>
    <!-- Fixed Footer/Bottom Bar (Example from MyBookings, adapt as needed) -->
    <footer class="shadow-top-lg fixed right-0 bottom-0 left-0 z-50 border-t border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
        <div class="container mx-auto flex items-center justify-around">
            <Link href="/" class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300">
                <span class="block text-xl">&#x1F3E0;</span>
                <!-- Home Icon -->
                <span>{{ t('navigation.home') }}</span>
            </Link>
            <Link
                :href="route('my-bookings')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
            >
                <span class="block text-xl">&#x1F39F;&#xFE0F;</span>
                <!-- Ticket Icon -->
                <span>{{ t('navigation.my_bookings') }}</span>
            </Link>
            <Link
                :href="route('my-wishlist')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
            >
                <span class="block text-xl">&#x2764;&#xFE0F;</span>
                <!-- Heart Icon -->
                <span>{{ t('navigation.my_wishlist') }}</span>
            </Link>
            <Link
                :href="route('my-coupons')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
            >
                <span class="block text-xl">🎫</span>
                <span>{{ t('navigation.my_coupons') }}</span>
            </Link>
            <!-- Add other relevant links if needed -->
        </div>
    </footer>
</template>
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>
