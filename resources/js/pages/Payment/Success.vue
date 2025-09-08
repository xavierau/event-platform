<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Success Header -->
      <div class="text-center mb-8">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900 mb-4">
          <CheckCircleIcon class="h-8 w-8 text-green-600 dark:text-green-400" />
        </div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Payment Successful!</h1>
        <p class="text-lg text-gray-600 dark:text-gray-300">Thank you for your purchase. Your booking has been confirmed.</p>
      </div>

      <!-- Transaction Details -->
      <div v-if="transaction" class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Order Details</h2>
        </div>
        <div class="px-6 py-4">
          <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Order ID</dt>
              <dd class="text-sm text-gray-900 dark:text-gray-200">#{{ transaction.id }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</dt>
              <dd class="text-sm text-gray-900 dark:text-gray-200">{{ formatCurrency(transaction.total_amount, transaction.currency) }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Status</dt>
              <dd class="text-sm text-gray-900 dark:text-gray-200">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">
                  Confirmed
                </span>
              </dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Transaction Date</dt>
              <dd class="text-sm text-gray-900 dark:text-gray-200">{{ formatDate(transaction.updated_at) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Booking Details -->
      <div v-if="bookings && bookings.length > 0" class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Your Tickets</h2>
        </div>
        <div class="px-6 py-4">
          <div class="space-y-4">
            <div v-for="booking in bookings" :key="booking.id" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-700/50">
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ booking.event_occurrence?.event?.name || 'Event' }}
                  </h3>
                  <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                    {{ booking.event_occurrence?.name || booking.event_occurrence?.event?.name }}
                  </p>
                  <div class="mt-2 space-y-1">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                      <span class="font-medium dark:text-gray-200">Ticket Type:</span>
                      {{ booking.ticket_definition?.name || 'General Admission' }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                      <span class="font-medium dark:text-gray-200">Price:</span>
                      {{ formatCurrency(booking.price_at_booking, booking.currency) }}
                    </p>
                  </div>
                </div>
                <div class="ml-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">
                    Confirmed
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Next Steps -->
      <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-blue-900 dark:text-blue-200 mb-2">What's Next?</h3>
        <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1">
          <li>• You will receive a confirmation email shortly with your ticket details</li>
          <li>• QR codes for event entry will be included in your confirmation email</li>
          <li>• Please bring your ticket (digital or printed) to the event</li>
          <li>• Contact support if you have any questions about your booking</li>
        </ul>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <Link
          :href="route('home')"
          class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 "
        >
          Back to Home
        </Link>
        <Link
          :href="route('my-bookings')"
          class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 "
        >
          {{ t('navigation.my_bookings') }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { CheckCircleIcon } from '@heroicons/vue/24/outline'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Transaction {
  id: number
  total_amount: number
  currency: string
  updated_at: string
}

import type { TicketEventOccurrenceInfo, BookingTicketInfo } from '@/types/ticket';

interface Booking {
  id: number
  quantity: number
  price_at_booking: number
  currency: string
  ticket_definition?: BookingTicketInfo
  event_occurrence?: TicketEventOccurrenceInfo
}

const props = defineProps<{
  transaction?: Transaction
  bookings?: Booking[]
  session_id?: string
}>()

console.log(props.bookings)

// Helper function to format currency
const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency?.toUpperCase() || 'USD',
  }).format(amount / 100) // Convert from cents to dollars
}

// Helper function to format date
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
