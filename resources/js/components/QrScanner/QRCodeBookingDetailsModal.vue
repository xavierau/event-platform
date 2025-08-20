<template>
    <div v-if="show && bookingDetails" class="bg-opacity-50 fixed inset-0 z-50 h-full w-full overflow-y-auto bg-gray-600">
        <div class="relative top-20 mx-auto w-11/12 rounded-md border bg-white p-5 shadow-lg md:w-3/4 lg:w-1/2 dark:bg-gray-800">
            <div class="mt-3">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Booking Details</h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Booking Information -->
                <div class="mb-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Booking Information</h4>
                            <div class="space-y-1 text-sm">
                                <div><span class="font-medium">Booking #:</span> {{ bookingDetails.booking_number }}</div>
                                <div>
                                    <span class="font-medium">Status:</span>
                                    <span :class="getBookingStatusClass(bookingDetails.status)">
                                        {{ getBookingStatusText(bookingDetails.status) }}
                                    </span>
                                </div>
                                <div><span class="font-medium">Quantity:</span> {{ bookingDetails.quantity }}</div>
                                <div>
                                    <span class="font-medium">Total:</span> {{ formatCurrency(bookingDetails.total_price, bookingDetails.currency) }}
                                </div>
                                <div v-if="bookingDetails.seat_number">
                                    <span class="font-medium">Assigned Seat:</span>
                                    <span
                                        class="ml-1 inline-flex items-center rounded-md bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-800 dark:text-indigo-100"
                                    >
                                        🪑 {{ bookingDetails.seat_number }}
                                    </span>
                                </div>
                                <div v-else class="text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-medium">Seating:</span> General Admission
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Customer Information</h4>
                            <div class="space-y-1 text-sm">
                                <div><span class="font-medium">Name:</span> {{ bookingDetails.user.name }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event Information -->
                <div class="mb-4 rounded-lg bg-blue-50 p-4 dark:bg-blue-900">
                    <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Event Information</h4>
                    <div class="space-y-1 text-sm">
                        <div><span class="font-medium">Event:</span> {{ bookingDetails.event.name }}</div>
                        <div v-if="bookingDetails.ticket_definition">
                            <span class="font-medium">Ticket Type:</span> {{ bookingDetails.ticket_definition.name }}
                        </div>
                    </div>
                </div>

                <!-- Check-in History -->
                <div v-if="checkInHistory" class="mb-4 rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900">
                    <h4 class="mb-3 font-medium text-gray-900 dark:text-gray-100">Check-in History & Usage</h4>
                    <div class="mb-4 grid grid-cols-2 gap-4 md:grid-cols-4">
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
                        <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100">Recent Check-in Attempts:</h5>
                        <div class="max-h-32 space-y-1 overflow-y-auto">
                            <div
                                v-for="log in checkInHistory.check_in_logs.slice(0, 5)"
                                :key="log.id"
                                class="flex items-center justify-between rounded bg-white p-2 text-xs dark:bg-gray-800"
                            >
                                <div class="flex items-center space-x-2">
                                    <span :class="getCheckInStatusColor(log.status)" class="rounded-full px-2 py-1 text-xs font-medium">
                                        {{ getCheckInStatusText(log.status) }}
                                    </span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ formatCheckInDateTime(log.timestamp) }}</span>
                                </div>
                                <div v-if="log.event_occurrence" class="text-right text-gray-500 dark:text-gray-400">
                                    <div>{{ log.event_occurrence.name || 'Main Occurrence' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-600 dark:text-gray-400">No previous check-in attempts found.</div>
                </div>

                <!-- Event Occurrence Selection for Check-in -->
                <div v-if="eventOccurrences.length > 0" class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                    <h5 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Select Event Occurrence for Check-in:</h5>
                    <div class="max-h-48 space-y-2 overflow-y-auto">
                        <label
                            v-for="occurrence in eventOccurrences"
                            :key="occurrence.id"
                            class="flex cursor-pointer items-center rounded-md p-3 transition-colors hover:bg-gray-100 dark:hover:bg-gray-600"
                            :class="{
                                'border border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-900':
                                    selectedOccurrenceId === occurrence.id,
                            }"
                        >
                            <input
                                type="radio"
                                name="event_occurrence"
                                :value="occurrence.id"
                                v-model="selectedOccurrenceId"
                                class="form-radio h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-500"
                            />
                            <span class="ml-3 text-sm text-gray-700 dark:text-gray-200">
                                <span class="font-medium">{{ occurrence.name || 'Main Occurrence' }}</span
                                ><br />
                                {{ formatOccurrenceDateTime(occurrence.start_at, occurrence.end_at) }}
                                <span v-if="occurrence.venue_name" class="block text-xs text-gray-500 dark:text-gray-400"
                                    >📍 {{ occurrence.venue_name }}</span
                                >
                            </span>
                        </label>
                    </div>
                    <p v-if="!selectedOccurrenceId && checkInFormErrors?.event_occurrence_id" class="mt-1 text-xs text-red-500">
                        {{ checkInFormErrors.event_occurrence_id }}
                    </p>
                    <p
                        v-else-if="!selectedOccurrenceId && bookingDetails.status === 'confirmed' && !checkInStatus"
                        class="mt-1 text-xs text-yellow-600"
                    >
                        Please select an occurrence to proceed with check-in.
                    </p>
                </div>

                <div
                    v-else
                    class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-700 dark:border-yellow-600 dark:bg-yellow-700 dark:text-yellow-200"
                >
                    No upcoming occurrences found for this event. Check-in may not be possible.
                </div>

                <div
                    v-if="checkInStatus"
                    class="mt-4 rounded-md p-3 text-sm"
                    :class="{
                        'border border-green-300 bg-green-50 text-green-700 dark:border-green-600 dark:bg-green-700 dark:text-green-200':
                            checkInStatus.success,
                        'border border-red-300 bg-red-50 text-red-700 dark:border-red-600 dark:bg-red-700 dark:text-red-200': !checkInStatus.success,
                    }"
                >
                    {{ checkInStatus.message }}
                </div>
            </div>

            <div class="flex justify-end space-x-3 border-t border-gray-200 p-5 dark:border-gray-700">
                <Button variant="outline" @click="$emit('close')">Cancel</Button>
                <Button
                    variant="default"
                    @click="$emit('check-in')"
                    :disabled="
                        !selectedOccurrenceId ||
                        (isProcessing && !checkInStatus) ||
                        bookingDetails.status !== 'confirmed' ||
                        checkInStatus?.success === true
                    "
                >
                    <span v-if="isProcessing && !checkInStatus">Processing Check-in...</span>
                    <span v-else-if="checkInStatus?.success === true">Checked In</span>
                    <span v-else-if="bookingDetails.status !== 'confirmed'"
                        >Cannot Check-In (Status: {{ getBookingStatusText(bookingDetails.status) }})</span
                    >
                    <span v-else>Confirm Check-in</span>
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { getBookingStatusColor, getBookingStatusText } from '@/Utils/booking';
import dayjs from 'dayjs';
import { computed } from 'vue';

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
    set: (value) => emit('update:selectedOccurrenceId', value),
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
