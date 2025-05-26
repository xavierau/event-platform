<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthLayout.vue';
import PrimaryButton from '@/components/ui/button/PrimaryButton.vue';
import SecondaryButton from '@/components/ui/button/SecondaryButton.vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import type { DetectedBarcode } from 'vue-qrcode-reader';
import { decodeQRCodeData, isQRCodeDataValid } from '@/Utils/qrcode';
import { getBookingStatusColor, getBookingStatusText } from '@/Utils/booking';
import dayjs from 'dayjs';

// Props
interface EventItem {
  id: number;
  name: string;
}

// More generic PageProps, aligning with common Inertia.js usage
interface InertiaSharedProps {
  auth: {
    user: {
      id: number;
      // other user properties
    } | null;
  };
  errors?: Record<string, string>;
  [key: string]: any; // Allows for other props not explicitly defined
}

interface CustomPageProps extends InertiaSharedProps {
  events: EventItem[];
  user_role: string;
}

const page = usePage<CustomPageProps>();
const props = page.props;

// Refs
const scannerReady = ref(false);
const scanResult = ref<any>(null); // To store decoded QR data or error message
const bookingDetails = ref<any>(null);
const eventOccurrences = ref<any[]>([]);
const selectedEventId = ref<number | null>(null);
const selectedOccurrenceId = ref<number | null>(null);
const cameraError = ref<string | null>(null);
const isProcessing = ref(false);
const showDetailsModal = ref(false);
const checkInStatus = ref<{ success: boolean; message: string } | null>(null);

const isAdmin = computed(() => props.user_role === 'admin');

// Form for check-in
const checkInForm = useForm({
  qr_code_identifier: '' as string,
  event_occurrence_id: null as number | null,
  method: 'QR_SCAN',
  operator_user_id: props.auth.user?.id,
});

watch(selectedEventId, () => {
  resetScannerState();
});

const onDetect = async (detectedCodes: DetectedBarcode[]) => {
  if (isProcessing.value || detectedCodes.length === 0) return;
  isProcessing.value = true;
  scanResult.value = null;
  bookingDetails.value = null;
  eventOccurrences.value = [];
  checkInStatus.value = null;

  const rawValue = detectedCodes[0].rawValue;

  try {
    const decodedData = decodeQRCodeData(rawValue);

    if (!isQRCodeDataValid(decodedData, 24 * 30)) {
      scanResult.value = { error: 'QR code has expired or is invalid.' };
      isProcessing.value = false;
      return;
    }

    const qrCodeToValidate = decodedData.bookingNumber;

    const response = await fetch(route('admin.qr-scanner.validate'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        qr_code: qrCodeToValidate,
        event_id: selectedEventId.value,
      }),
    });

    const data = await response.json();

    if (response.ok && data.success) {
      bookingDetails.value = data.booking;
      eventOccurrences.value = data.event_occurrences;
      scanResult.value = { success: `Booking ${data.booking.booking_number} found.` };
      checkInForm.qr_code_identifier = data.booking.booking_number;
      showDetailsModal.value = true;
    } else {
      scanResult.value = { error: data.message || 'Failed to validate QR code.' };
      bookingDetails.value = null;
    }
  } catch (error: any) {
    console.error('Error processing QR code:', error);
    scanResult.value = { error: error.message || 'Error processing QR code.' };
    bookingDetails.value = null;
  } finally {
    isProcessing.value = false;
  }
};

const onCameraError = (error: any) => {
  cameraError.value = `Camera error: ${error.name}. Ensure camera access is allowed.`;
  console.error("Camera error:", error);
};

const onScannerReady = () => {
  scannerReady.value = true;
  cameraError.value = null;
};

const resetScannerState = () => {
  scanResult.value = null;
  bookingDetails.value = null;
  eventOccurrences.value = [];
  selectedOccurrenceId.value = null;
  checkInStatus.value = null;
  isProcessing.value = false;
  showDetailsModal.value = false;
  checkInForm.reset();
  checkInForm.qr_code_identifier = '';
  if (isAdmin.value && props.events.length > 0 && !selectedEventId.value) {
    // Admin still needs to select event
  } else if (!isAdmin.value) {
    selectedEventId.value = props.events.length === 1 ? props.events[0].id : null;
  }
};

const handleCheckIn = async () => {
  if (!bookingDetails.value || !selectedOccurrenceId.value) {
    checkInStatus.value = { success: false, message: 'No booking or occurrence selected for check-in.' };
    return;
  }

  checkInForm.event_occurrence_id = selectedOccurrenceId.value;
  isProcessing.value = true;

  try {
    const response = await fetch(route('admin.qr-scanner.check-in'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
        'Accept': 'application/json',
      },
      body: JSON.stringify(checkInForm.data()),
    });

    const data = await response.json();
    if (response.ok && data.success) {
      checkInStatus.value = { success: true, message: data.message || 'Check-in successful!' };
      if (bookingDetails.value) {
          bookingDetails.value.status = 'checked_in';
      }
    } else {
      checkInStatus.value = { success: false, message: data.message || 'Check-in failed.' };
    }
  } catch (error: any) {
    console.error('Check-in error:', error);
    checkInStatus.value = { success: false, message: error.message || 'An unexpected error occurred during check-in.' };
  }
};

const closeModalAndReset = () => {
  showDetailsModal.value = false;
  resetScannerState();
};

onMounted(() => {
  if (!isAdmin.value && props.events.length === 1) {
    selectedEventId.value = props.events[0].id;
  }
});

onUnmounted(() => {
  // Cleanup if necessary
});

const formatOccurrenceDateTime = (startAt: string, endAt?: string): string => {
    if (!startAt) return 'Date TBD';
    const start = dayjs(startAt);
    const end = endAt ? dayjs(endAt) : null;

    if (end && !start.isSame(end, 'day')) {
        return `${start.format('MMM D, HH:mm')} - ${end.format('MMM D, HH:mm, YYYY')}`;
    }
    return start.format('MMM D, YYYY • HH:mm');
};

</script>

<template>
  <Head title="QR Code Scanner" />
  <AuthenticatedLayout title="QR Code Scanner">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        QR Code Scanner
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
          <div v-if="isAdmin && props.events.length > 0" class="mb-6">
            <label for="event-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Select Event to Scan For:
            </label>
            <select
              id="event-select"
              v-model="selectedEventId"
              class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
            >
              <option :value="null">-- Select an Event --</option>
              <option v-for="eventItem in props.events" :key="eventItem.id" :value="eventItem.id">
                {{ eventItem.name }}
              </option>
            </select>
            <p v-if="!selectedEventId && isAdmin" class="text-sm text-yellow-600 mt-1">
              Admins: Please select an event to enable the scanner.
            </p>
          </div>
          <div v-else-if="!isAdmin && props.events.length === 0" class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
            <p>You are not currently an organizer for any published events. Please contact an administrator if you believe this is an error.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden relative aspect-square md:aspect-auto min-h-[300px]">
              <div v-if="cameraError" class="p-4 bg-red-100 text-red-700 text-sm">
                {{ cameraError }}
                <p v-if="cameraError.includes('NotAllowedError') || cameraError.includes('NotFoundError')" class="mt-1">
                  Please grant camera permissions in your browser settings and ensure a camera is connected, then refresh the page.
                </p>
              </div>
              <qrcode-stream
                v-if="!cameraError && ((isAdmin && selectedEventId) || (!isAdmin && props.events.length > 0))"
                @detect="onDetect"
                @error="onCameraError"
                @camera-on="onScannerReady"
                :track="true"
                :formats="['qr_code']"
                class="w-full h-full"
              >
                <div v-if="!scannerReady && !cameraError" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                  <p class="text-gray-500 dark:text-gray-400">Initializing camera...</p>
                </div>
              </qrcode-stream>
               <div v-if="(isAdmin && !selectedEventId && props.events.length > 0) || (!isAdmin && props.events.length === 0)" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                  <p class="text-gray-500 dark:text-gray-400 text-center p-4">
                    <span v-if="isAdmin && !selectedEventId">Please select an event above to activate the QR scanner.</span>
                    <span v-if="!isAdmin && props.events.length === 0">No events available for scanning.</span>
                  </p>
              </div>
            </div>

            <div class="space-y-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Scan Status</h3>
              <div v-if="isProcessing && !scanResult && !bookingDetails && !checkInStatus" class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Processing QR code...
              </div>
              <div v-if="scanResult?.success" class="p-3 bg-green-50 dark:bg-green-700 border border-green-300 dark:border-green-600 rounded-md text-sm text-green-700 dark:text-green-200">
                {{ scanResult.success }}
              </div>
              <div v-if="scanResult?.error" class="p-3 bg-red-50 dark:bg-red-700 border border-red-300 dark:border-red-600 rounded-md text-sm text-red-700 dark:text-red-200">
                {{ scanResult.error }}
              </div>

              <div v-if="!bookingDetails && !scanResult && !isProcessing && !checkInStatus && ((isAdmin && selectedEventId) || (!isAdmin && props.events.length > 0))" class="text-sm text-gray-500 dark:text-gray-400">
                Point the camera at a QR code to scan.
              </div>
               <div v-else-if="!bookingDetails && !scanResult && !isProcessing && !checkInStatus && ((isAdmin && !selectedEventId && props.events.length > 0) || (!isAdmin && props.events.length === 0))" class="text-sm text-gray-500 dark:text-gray-400">
                 <span v-if="isAdmin && !selectedEventId">Select an event to begin scanning.</span>
                 <span v-else>Scanner is idle.</span>
              </div>

            </div>
          </div>
           <div class="mt-6 flex justify-end">
                <SecondaryButton @click="resetScannerState" :disabled="isProcessing && !showDetailsModal">
                    Reset Scanner
                </SecondaryButton>
            </div>
        </div>
      </div>
    </div>

    <div v-if="showDetailsModal && bookingDetails" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4 transition-opacity duration-300" @click.self="closeModalAndReset">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="flex justify-between items-center p-5 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Booking: {{ bookingDetails.booking_number }}
          </h3>
          <button @click="closeModalAndReset" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div class="p-6 space-y-4">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100">
                        {{ bookingDetails.ticket_definition?.name || 'General Admission' }}
                        <span class="text-sm text-gray-500 dark:text-gray-400"> ({{ bookingDetails.quantity }}x)</span>
                    </h4>
                    <span
                        :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1',
                           getBookingStatusColor(bookingDetails.status)
                           ]"
                    >
                        {{ getBookingStatusText(bookingDetails.status) }}
                    </span>
                </div>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <h5 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Event Details</h5>
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-1">{{ bookingDetails.event.name }}</p>
            </div>


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
             <p v-if="!selectedOccurrenceId && checkInForm.errors.event_occurrence_id" class="text-xs text-red-500 mt-1">{{ checkInForm.errors.event_occurrence_id }}</p>
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
          <SecondaryButton @click="closeModalAndReset">Cancel</SecondaryButton>
          <PrimaryButton
            @click="handleCheckIn"
            :disabled="!selectedOccurrenceId || (isProcessing && !checkInStatus) || bookingDetails.status !== 'confirmed' || checkInStatus?.success === true"
          >
            <span v-if="isProcessing && !checkInStatus">Processing Check-in...</span>
            <span v-else-if="checkInStatus?.success === true">Checked In</span>
            <span v-else-if="bookingDetails.status !== 'confirmed'">Cannot Check-In (Status: {{ getBookingStatusText(bookingDetails.status) }})</span>
            <span v-else>Confirm Check-in</span>
          </PrimaryButton>
        </div>
      </div>
    </div>

  </AuthenticatedLayout>
</template>
