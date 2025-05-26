<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { type Booking } from '@/types'; // Assuming a Booking type will be defined in @/types
import { Eye } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

// Props passed from the controller
// The controller should pass pageTitle and breadcrumbs as well
defineProps<{
    bookings: { data: Booking[] }; // Assuming pagination or a structured response
    pageTitle: string; // Expected by AppLayout
    breadcrumbs: Array<{ title: string; href?: string }>; // Changed text to title
 }>();

// Helper to format date, assuming booking has a created_at or similar date field
const formatDate = (dateString: string | undefined) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <Head :title="pageTitle || 'Bookings'" />

    <AppLayout :page-title="pageTitle || 'Manage Bookings'" :breadcrumbs="breadcrumbs || []">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Optional: Add a Create Booking button if applicable -->
                        <!-- <div class="flex justify-end mb-4">
                            <Button as-child>
                                <Link :href="route('admin.bookings.create')">Create Booking</Link>
                            </Button>
                        </div> -->

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Event
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            User
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Booked On
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="!bookings.data || bookings.data.length === 0">
                                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No bookings found.</td>
                                    </tr>
                                    <tr v-for="booking in bookings.data" :key="booking.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ booking.id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <!-- Assuming booking.event.name exists; adjust if structure is different -->
                                            {{ booking.event?.name || 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <!-- Assuming booking.user.name exists -->
                                            {{ booking.user?.name || 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ booking.status }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(booking.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Button variant="outline" size="sm" as-child>
                                                <Link :href="route('admin.bookings.show', booking.id)">
                                                    <Eye class="h-4 w-4 mr-1" /> View
                                                </Link>
                                            </Button>
                                            <!-- Add Edit/Delete buttons here if needed -->
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Optional: Pagination links -->
                        <!-- <div v-if="bookings.links && bookings.links.length > 1" class="mt-4">
                            <Link
                                v-for="(link, index) in bookings.links"
                                :key="index"
                                :href="link.url || ''"
                                v-html="link.label"
                                class="px-3 py-1 mx-1 border rounded"
                                :class="{ 'bg-blue-500 text-white': link.active, 'text-gray-700': !link.active, 'opacity-50 cursor-not-allowed': !link.url }"
                                :disabled="!link.url"
                            />
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
