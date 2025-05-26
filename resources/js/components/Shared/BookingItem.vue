<script setup lang="ts">
import { useCurrency } from '@/composables/useCurrency';
import dayjs from 'dayjs';
import type { BookingItem } from '@/types/booking';
import { getBookingStatusColor, getBookingStatusText } from '@/Utils/booking';
import { QrCodeIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
  booking: BookingItem;
}>();

const emit = defineEmits<{
  showDetails: [booking: BookingItem];
}>();

const { formatPrice } = useCurrency();

function formatBookingPrice(totalPrice: number, currency: string): string {
  if (totalPrice === 0) return 'Free';
  return formatPrice(totalPrice, currency);
}

function handleClick() {
  emit('showDetails', props.booking);
}
</script>

<template>
  <div
    class="px-6 py-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors duration-200 min-h-[120px]"
    @click="handleClick"
  >
    <div class="flex justify-between items-start h-full">
      <div class="flex-1 min-w-0 pr-4">
        <div class="flex items-start flex-col sm:flex-row sm:items-center sm:space-x-3 space-y-2 sm:space-y-0 mb-3">
          <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 leading-tight">
            {{ booking.ticket_definition?.name || 'General Admission' }}
          </h4>
          <span
            :class="[
              'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium flex-shrink-0',
              getBookingStatusColor(booking.status)
            ]"
          >
            {{ getBookingStatusText(booking.status) }}
          </span>
        </div>
        <div class="space-y-2">
          <p class="text-sm text-gray-600 dark:text-gray-400 break-all">
            <span class="font-medium dark:text-gray-300">Booking #:</span>
            <span class="ml-1">{{ booking.booking_number }}</span>
          </p>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            <span class="font-medium dark:text-gray-300">Booked on:</span>
            <span class="ml-1">{{ dayjs(booking.created_at).format('MMM DD, YYYY') }}</span>
          </p>
        </div>
      </div>
      <div class="flex-shrink-0 text-right flex flex-col justify-between h-full min-h-[80px]">
        <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">
          {{ formatBookingPrice(booking.total_price, booking.currency) }}
        </p>
        <div class="text-sm text-gray-500 dark:text-gray-400 mt-auto flex items-center justify-end">
          <QrCodeIcon class="h-10 w-10 text-gray-500 dark:text-gray-400" />
        </div>
      </div>
    </div>
  </div>
</template>
