<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const isActive = (path: string) => {
  const currentUrl = page.url;
  
  // Handle special cases
  if (path === 'home') {
    return currentUrl === '/';
  }
  
  // For other paths, check if the current URL starts with the path
  return currentUrl.startsWith(`/${path.replace(/^\//, '')}`);
};
</script>

<template>
    <!-- Fixed Footer/Bottom Bar (Example from MyBookings, adapt as needed) -->
    <footer class="shadow-top-lg fixed right-0 bottom-0 left-0 z-50 border-t border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
        <div class="container mx-auto flex items-center justify-around">
            <Link href="/"
                  class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
            :class="{ 'text-indigo-800 dark:text-indigo-400': isActive('home') }">
                <span class="block text-xl">🏠</span>
                <!-- Home Icon -->
                <span>Home</span>
            </Link>
            <Link
                :href="route('my-bookings')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
                :class="{ 'text-indigo-800 dark:text-indigo-400': isActive('my-bookings') }"
            >
                <span class="block text-xl">🎟️</span>
                <!-- Ticket Icon -->
                <span>My Bookings</span>
            </Link>
            <Link
                :href="route('my-coupons')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
                :class="{ 'text-indigo-800 dark:text-indigo-400': isActive('my-coupons') }"
            >
                <span class="block text-xl">🎫</span>
                <!-- Coupon/Ticket Icon -->
                <span>My Coupons</span>
            </Link>
            <!-- My Wallet link commented out and replaced with My Profile -->
            <!-- <Link
                :href="route('my-wallet')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
                :class="{ 'text-indigo-800 dark:text-indigo-400': isActive('my-wallet') }"
            >
                <span class="block text-xl">💳</span>
                <span>My Wallet</span>
            </Link> -->
            <Link
                :href="route('profile.edit')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
                :class="{ 'text-indigo-800 dark:text-indigo-400': isActive('profile') }"
            >
                <span class="block text-xl">👤</span>
                <!-- Profile Icon -->
                <span>My Profile</span>
            </Link>
            <Link
                :href="route('my-wishlist')"
                class="text-center text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300"
                :class="{ 'text-indigo-800 dark:text-indigo-400': isActive('my-wishlist') }"
            >
                <span class="block text-xl">❤️</span>
                <!-- Heart Icon -->
                <span>My Wishlist</span>
            </Link>
        </div>
    </footer>
</template>

<style scoped></style>
