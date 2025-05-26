<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import QRCode from 'qrcode';
import type { BookingItem } from '@/types/booking';
import { getBookingStatusColor, getBookingStatusText } from '@/Utils/booking';
import { createQRCodeData, encodeQRCodeData } from '@/Utils/qrcode';

dayjs.extend(utc);

interface Props {
  showModal: boolean;
  booking: BookingItem | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  close: [];
  refreshBookings: [];
}>();

const page = usePage();
const qrCodeDataUrl = ref<string>('');
const isGeneratingQR = ref(false);
const qrRefreshInterval = ref<NodeJS.Timeout | null>(null);

// Get current user from page props
const currentUser = computed(() => (page.props.auth as any)?.user);

// Generate QR code data
const generateQRCodeData = async () => {
  if (!props.booking || !currentUser.value) return;

  isGeneratingQR.value = true;

  try {
    // Create the data object to encode using utility functions
    const qrData = createQRCodeData(
      currentUser.value.id,
      props.booking.booking_number,
      props.booking.id
    );

    // Convert to base64 using utility function
    const base64Data = encodeQRCodeData(qrData);

    // Generate QR code from base64 data
    const qrCodeUrl = await QRCode.toDataURL(base64Data, {
      width: 256,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#FFFFFF'
      }
    });

    qrCodeDataUrl.value = qrCodeUrl;
  } catch (error) {
    console.error('Error generating QR code:', error);
  } finally {
    isGeneratingQR.value = false;
  }
};

// Start QR refresh interval
const startQRRefresh = () => {
  // Clear any existing interval
  if (qrRefreshInterval.value) {
    clearInterval(qrRefreshInterval.value);
  }

  // Set up new interval to refresh QR code every minute
  qrRefreshInterval.value = setInterval(() => {
    if (props.showModal && props.booking) {
      generateQRCodeData();
    }
  }, 60000); // 60 seconds
};

// Stop QR refresh interval
const stopQRRefresh = () => {
  if (qrRefreshInterval.value) {
    clearInterval(qrRefreshInterval.value);
    qrRefreshInterval.value = null;
  }
};

// Watch for modal opening/closing to manage QR code generation and refresh
watch(() => props.showModal, (newValue) => {
  if (newValue && props.booking) {
    generateQRCodeData();
    startQRRefresh();
  } else {
    stopQRRefresh();
  }
});

// Format event date
function formatEventDate(startAt?: string, endAt?: string): string {
  if (!startAt) return 'Date TBD';

  const start = dayjs(startAt);
  const end = endAt ? dayjs(endAt) : null;

  if (end && !start.isSame(end, 'day')) {
    return `${start.format('MMM DD')} - ${end.format('MMM DD, YYYY')}`;
  }

  return start.format('MMM DD, YYYY • h:mm A');
}

// Close modal
function closeModal() {
  stopQRRefresh();
  emit('refreshBookings'); // Refresh bookings status when modal closes
  emit('close');
}

// Handle backdrop click
function handleBackdropClick(event: Event) {
  if (event.target === event.currentTarget) {
    closeModal();
  }
}

// Cleanup interval on component unmount
onUnmounted(() => {
  stopQRRefresh();
});
</script>

<template>
  <!-- Modal Backdrop -->
  <div
    v-if="showModal"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
    @click="handleBackdropClick"
  >
    <!-- Modal Content -->
    <div
      class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto"
      @click.stop
    >
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Booking Details</h2>
        <button
          @click="closeModal"
          class="text-gray-400 hover:text-gray-600 transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div v-if="booking" class="p-6">
        <!-- QR Code Section -->
        <div class="text-center mb-6">
          <div class="bg-gray-50 rounded-lg p-4 inline-block">
            <div v-if="isGeneratingQR" class="w-64 h-64 flex items-center justify-center">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            </div>
            <img
              v-else-if="qrCodeDataUrl"
              :src="qrCodeDataUrl"
              alt="Booking QR Code"
              class="w-64 h-64"
            />
            <div v-else class="w-64 h-64 flex items-center justify-center text-gray-500">
              Failed to generate QR code
            </div>
          </div>
          <p class="text-sm text-gray-600 mt-2">
            Show this QR code for check-in
          </p>
          <div v-if="isGeneratingQR" class="flex items-center justify-center mt-2 text-xs text-gray-500">
            <svg class="animate-spin -ml-1 mr-2 h-3 w-3 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Refreshing QR code...
          </div>
        </div>

        <!-- Booking Information -->
        <div class="space-y-4">
          <!-- Ticket Name and Status -->
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <h3 class="text-lg font-medium text-gray-900">
                {{ booking.ticket_definition?.name || 'General Admission' }}
              </h3>
              <span
                :class="[
                  'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1',
                  getBookingStatusColor(booking.status)
                ]"
              >
                {{ getBookingStatusText(booking.status) }}
              </span>
            </div>
          </div>

          <!-- Event Information -->
          <div v-if="booking.event" class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 mb-2">Event Details</h4>
            <p class="text-sm text-gray-700 mb-1">{{ booking.event.name }}</p>

            <!-- Event Occurrences -->
            <div v-if="booking.event_occurrences?.length" class="mt-3">
              <p class="text-xs font-medium text-gray-600 mb-2">Valid for:</p>
              <div class="space-y-2">
                <div
                  v-for="occurrence in booking.event_occurrences"
                  :key="occurrence.id"
                  class="text-xs text-gray-600"
                >
                  <div class="font-medium">{{ occurrence.name || 'Event Date' }}</div>
                  <div>{{ formatEventDate(occurrence.start_at, occurrence.end_at) }}</div>
                  <div v-if="occurrence.venue_name" class="flex items-center mt-1">
                    <span class="mr-1">📍</span>
                    <span>{{ occurrence.venue_name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Booking Details -->
          <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 mb-2">Booking Information</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Booking Number:</span>
                <span class="font-mono text-gray-900 text-xs">{{ booking.booking_number }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Quantity:</span>
                <span class="text-gray-900">{{ booking.quantity }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Total Price:</span>
                <span class="text-gray-900 font-medium">
                  {{ booking.total_price === 0 ? 'Free' : `${booking.currency?.toUpperCase()} ${(booking.total_price / 100).toFixed(2)}` }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Booked on:</span>
                <span class="text-gray-900">{{ dayjs(booking.created_at).format('MMM DD, YYYY') }}</span>
              </div>
            </div>
          </div>

          <!-- QR Code Info -->
          <div class="bg-blue-50 rounded-lg p-4">
            <h4 class="font-medium text-blue-900 mb-2">QR Code Information</h4>
            <p class="text-xs text-blue-700">
              This QR code contains encrypted booking information and is valid for check-in.
              The QR code refreshes automatically every minute for enhanced security.
              Generated at {{ dayjs().format('MMM DD, YYYY • h:mm A') }}.
            </p>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="flex justify-end space-x-3 p-6 border-t border-gray-200">
        <button
          @click="closeModal"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>
