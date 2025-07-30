<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ScanLine, QrCode, Percent } from 'lucide-vue-next';

// Define props if needed, for example, if you pass specific data to the dashboard
// const props = defineProps({
//   stats: Object,
// });
</script>

<template>
    <AppLayout>
        <!-- The pageTitle and breadcrumbs are handled by AppLayout via props passed from the controller -->
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="mb-8">
                            <h1 class="text-2xl font-bold mb-2">Welcome to your Dashboard!</h1>
                            <p class="text-gray-600 dark:text-gray-400">Quick access to scanner tools and management features.</p>
                        </div>

                        <!-- Quick Actions Section -->
                        <div class="mb-8">
                            <h2 class="text-lg font-semibold mb-4">Quick Actions</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Member Scanner -->
                                <Link :href="route('admin.member-scanner.index')"
                                      class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-blue-500 text-white p-2 rounded-lg group-hover:bg-blue-600 transition-colors">
                                            <ScanLine class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-blue-900 dark:text-blue-100">Member Scanner</h3>
                                            <p class="text-sm text-blue-700 dark:text-blue-300">Scan member QR codes for check-in</p>
                                        </div>
                                    </div>
                                </Link>

                                <!-- QR Scanner -->
                                <Link :href="route('admin.qr-scanner.index')"
                                      class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors group">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-green-500 text-white p-2 rounded-lg group-hover:bg-green-600 transition-colors">
                                            <QrCode class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-green-900 dark:text-green-100">Booking Scanner</h3>
                                            <p class="text-sm text-green-700 dark:text-green-300">Scan booking QR codes</p>
                                        </div>
                                    </div>
                                </Link>

                                <!-- Coupon Scanner -->
                                <Link :href="route('admin.coupons.scanner')"
                                      class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-6 hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors group">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-purple-500 text-white p-2 rounded-lg group-hover:bg-purple-600 transition-colors">
                                            <Percent class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-purple-900 dark:text-purple-100">Coupon Scanner</h3>
                                            <p class="text-sm text-purple-700 dark:text-purple-300">Scan and validate coupons</p>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>

                        <!-- Dashboard content will go here -->
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
