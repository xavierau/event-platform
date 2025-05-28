<template>
  <div v-if="show && bookingDetails" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
      <div class="mt-3">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Booking Details</h3>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Booking Information -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Booking Information</h4>
              <div class="space-y-1 text-sm">
                <div><span class="font-medium">Booking #:</span> {{ bookingDetails.booking_number }}</div>
                <div><span class="font-medium">Status:</span>
                  <span :class="getBookingStatusClass(bookingDetails.status)">
                    {{ getBookingStatusText(bookingDetails.status) }}
                  </span>
                </div>
                <div><span class="font-medium">Quantity:</span> {{ bookingDetails.quantity }}</div>
                <div><span class="font-medium">Total:</span> {{ formatCurrency(bookingDetails.total_price, bookingDetails.currency) }}</div>
              </div>
            </div>
            <div>
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Customer Information</h4>
              <div class="space-y-1 text-sm">
                <div><span class="font-medium">Name:</span> {{ bookingDetails.user.name }}</div>
                <div><span class="font-medium">Email:</span> {{ bookingDetails.user.email }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Event Information -->
        <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4 mb-4">
          <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Event Information</h4>
          <div class="space-y-1 text-sm">
            <div><span class="font-medium">Event:</span> {{ bookingDetails.event.name }}</div>
            <div v-if="bookingDetails.ticket_definition">
              <span class="font-medium">Ticket Type:</span> {{ bookingDetails.ticket_definition.name }}
            </div>
          </div>
        </div>

        <!-- Check-in History -->
        <div v-if="checkInHistory" class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-4 mb-4">
          <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Check-in History & Usage</h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ checkInHistory.successful_check_ins }}</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Successful</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ checkInHistory.failed_check_ins }}</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Failed</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ checkInHistory.remaining_check_ins }}</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Remaining</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ checkInHistory.max_allowed_check_ins }}</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Max Allowed</div>
            </div>
          </div>

          <!-- Check-in Log Entries -->
          <div v-if="checkInHistory.check_in_logs.length > 0" class="space-y-2">
            <h5 class="font-medium text-gray-900 dark:text-gray-100 text-sm">Recent Check-in Attempts:</h5>
            <div class="max-h-32 overflow-y-auto space-y-1">
              <div
                v-for="log in checkInHistory.check_in_logs.slice(0, 5)"
                :key="log.id"
                class="flex items-center justify-between p-2 bg-white dark:bg-gray-800 rounded text-xs"
              >
                <div class="flex items-center space-x-2">
                  <span :class="getCheckInStatusColor(log.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ getCheckInStatusText(log.status) }}
                  </span>
                  <span class="text-gray-600 dark:text-gray-400">{{ formatCheckInDateTime(log.timestamp) }}</span>
                </div>
                <div v-if="log.event_occurrence" class="text-gray-500 dark:text-gray-400 text-right">
                  <div>{{ log.event_occurrence.name || 'Main Occurrence' }}</div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-gray-600 dark:text-gray-400">
            No previous check-in attempts found.
          </div>
        </div>

        <!-- Event Occurrence Selection for Check-in -->
        <div v-if="eventOccurrences.length > 0" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
          <h5 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Select Event Occurrence for Check-in:</h5>
          <div class="space-y-2 max-h-48 overflow-y-auto">
            <label
              v-for="occurrence in eventOccurrences"
              :key="occurrence.id"
              class="flex items-center p-3 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer transition-colors"
              :class="{ 'bg-indigo-50 dark:bg-indigo-900 border border-indigo-500 dark:border-indigo-400': selectedOccurrenceId === occurrence.id }"
            >
              <input
                type="radio"
                name="event_occurrence"
                :value="occurrence.id"
                v-model="selectedOccurrenceId"
                class="form-radio h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-500 focus:ring-indigo-500"
              />
              <span class="ml-3 text-sm text-gray-700 dark:text-gray-200">
                <span class="font-medium">{{ occurrence.name || 'Main Occurrence' }}</span><br>
                {{ formatOccurrenceDateTime(occurrence.start_at, occurrence.end_at) }}
                <span v-if="occurrence.venue_name" class="block text-xs text-gray-500 dark:text-gray-400">📍 {{ occurrence.venue_name }}</span>
              </span>
            </label>
          </div>
           <p v-if="!selectedOccurrenceId && checkInFormErrors?.event_occurrence_id" class="text-xs text-red-500 mt-1">{{ checkInFormErrors.event_occurrence_id }}</p>
           <p v-else-if="!selectedOccurrenceId && bookingDetails.status === 'confirmed' && !checkInStatus" class="text-xs text-yellow-600 mt-1">Please select an occurrence to proceed with check-in.</p>
        </div>

        <div v-else class="bg-yellow-50 dark:bg-yellow-700 border border-yellow-300 dark:border-yellow-600 rounded-md text-sm text-yellow-700 dark:text-yellow-200 p-3">
            No upcoming occurrences found for this event. Check-in may not be possible.
        </div>

          <div v-if="checkInStatus" class="mt-4 p-3 rounded-md text-sm"
              :class="{
                  'bg-green-50 dark:bg-green-700 border border-green-300 dark:border-green-600 text-green-700 dark:text-green-200': checkInStatus.success,
                  'bg-red-50 dark:bg-red-700 border border-red-300 dark:border-red-600 text-red-700 dark:text-red-200': !checkInStatus.success
              }">
              {{ checkInStatus.message }}
          </div>
      </div>

      <div class="flex justify-end space-x-3 p-5 border-t border-gray-200 dark:border-gray-700">
        <Button variant="outline" @click="$emit('close')">Cancel</Button>
        <Button
          variant="default"
          @click="$emit('check-in')"
          :disabled="!selectedOccurrenceId || (isProcessing && !checkInStatus) || bookingDetails.status !== 'confirmed' || checkInStatus?.success === true"
        >
          <span v-if="isProcessing && !checkInStatus">Processing Check-in...</span>
          <span v-else-if="checkInStatus?.success === true">Checked In</span>
          <span v-else-if="bookingDetails.status !== 'confirmed'">Cannot Check-In (Status: {{ getBookingStatusText(bookingDetails.status) }})</span>
          <span v-else>Confirm Check-in</span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import { getBookingStatusColor, getBookingStatusText } from '@/Utils/booking';
import dayjs from 'dayjs';

interface Props {
  show: boolean;
  bookingDetails: any;
  checkInHistory: any;
  eventOccurrences: any[];
  selectedOccurrenceId: number | null;
  checkInStatus: { success: boolean; message: string } | null;
  isProcessing: boolean;
  checkInFormErrors?: Record<string, string>;
}

const props = defineProps<Props>();

const selectedOccurrenceId = computed({
  get: () => props.selectedOccurrenceId,
  set: (value) => emit('update:selectedOccurrenceId', value)
});

const emit = defineEmits<{
  close: [];
  'check-in': [];
  'update:selectedOccurrenceId': [value: number | null];
}>();

const getBookingStatusClass = (status: string): string => {
  return getBookingStatusColor(status);
};

const formatCurrency = (amount: number, currency: string): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount / 100); // Assuming amount is in cents
};

const formatOccurrenceDateTime = (startAt: string, endAt?: string): string => {
    if (!startAt) return 'Date TBD';
    const start = dayjs(startAt);
    const end = endAt ? dayjs(endAt) : null;

    if (end && !start.isSame(end, 'day')) {
        return `${start.format('MMM D, HH:mm')} - ${end.format('MMM D, HH:mm, YYYY')}`;
    }
    return start.format('MMM D, YYYY • HH:mm');
};

const formatCheckInDateTime = (timestamp: string): string => {
  return dayjs(timestamp).format('MMM D, YYYY • HH:mm');
};

const getCheckInStatusColor = (status: string): string => {
  switch (status) {
    case 'SUCCESSFUL':
      return 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100';
    case 'FAILED_MAX_USES_REACHED':
      return 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100';
    case 'FAILED_ALREADY_USED':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100';
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100';
  }
};

const getCheckInStatusText = (status: string): string => {
  switch (status) {
    case 'SUCCESSFUL':
      return 'Successful';
    case 'FAILED_MAX_USES_REACHED':
      return 'Max Uses Reached';
    case 'FAILED_ALREADY_USED':
      return 'Already Used';
    case 'FAILED_INVALID_CODE':
      return 'Invalid Code';
    case 'FAILED_NOT_YET_VALID':
      return 'Not Yet Valid';
    case 'FAILED_EXPIRED':
      return 'Expired';
    default:
      return status;
  }
};
</script>
