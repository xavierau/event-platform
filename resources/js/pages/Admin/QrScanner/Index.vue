<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthLayout.vue';
import PrimaryButton from '@/components/ui/button/Button.vue';
import SecondaryButton from '@/components/ui/button/Button.vue';
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
  roles: {
    ADMIN: string;
    ORGANIZER: string;
    USER: string;
  };
  user_role: string;
}

const page = usePage<CustomPageProps>();
const props = page.props;

// Refs
const scannerReady = ref(false);
const scanResult = ref<any>(null); // To store decoded QR data or error message
const bookingDetails = ref<any>(null);
const eventOccurrences = ref<any[]>([]);
const checkInHistory = ref<any>(null);
const selectedEventId = ref<number | null>(null);
const selectedOccurrenceId = ref<number | null>(null);
const cameraError = ref<string | null>(null);
const isProcessing = ref(false);
const showDetailsModal = ref(false);
const showLoadingModal = ref(false);
const checkInStatus = ref<{ success: boolean; message: string } | null>(null);

// Browser API refs (reactive and SSR-safe)
const isHttps = ref(false);
const isSecureContext = ref(false);
const hasGetUserMedia = ref(false);
const currentUrl = ref('');

const isAdmin = computed(() => props.user_role === 'admin');

// Add a new computed property to determine if platform admin can scan all QR codes
const isPlatformAdmin = computed(() => props.user_role === 'admin');

// Update the scanner activation logic
const shouldShowScanner = computed(() => {
  // Platform admins can scan without selecting an event
  if (isPlatformAdmin.value) {
    return true;
  }
  // Organizers need to have events available and select one (if multiple)
  if (!isAdmin.value && props.events.length > 0) {
    return props.events.length === 1 || selectedEventId.value !== null;
  }
  return false;
});

// Form for check-in
const checkInForm = useForm({
  qr_code_identifier: '' as string,
  event_occurrence_id: null as number | null,
  method: 'QR_SCAN',
  operator_user_id: props.auth.user?.id,
});

watch(selectedEventId, (newEventId, oldEventId) => {
  console.group('📋 Event Selection Changed');
  console.log('Previous event ID:', oldEventId);
  console.log('New event ID:', newEventId);
  console.log('Is platform admin?', isPlatformAdmin.value);
  console.log('Should show scanner?', shouldShowScanner.value);
  console.log('Is admin?', isAdmin.value);
  console.log('Available events:', props.events.length);
  console.groupEnd();

  resetScannerState();
});

const onDetect = async (detectedCodes: DetectedBarcode[]) => {
  if (isProcessing.value || detectedCodes.length === 0) return;

  // Show loading modal immediately
  showLoadingModal.value = true;
  isProcessing.value = true;
  scanResult.value = null;
  bookingDetails.value = null;
  eventOccurrences.value = [];
  checkInHistory.value = null;
  checkInStatus.value = null;

  const rawValue = detectedCodes[0].rawValue;

  try {
    const decodedData = decodeQRCodeData(rawValue);

    if (!isQRCodeDataValid(decodedData, 24 * 30)) {
      scanResult.value = { error: 'QR code has expired or is invalid.' };
      showLoadingModal.value = false;
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
      checkInHistory.value = data.check_in_history;
      scanResult.value = { success: `Booking ${data.booking.booking_number} found.` };
      checkInForm.qr_code_identifier = data.booking.booking_number;

      // Hide loading modal and show details modal
      showLoadingModal.value = false;
      showDetailsModal.value = true;
    } else {
      scanResult.value = { error: data.message || 'Failed to validate QR code.' };
      bookingDetails.value = null;
      showLoadingModal.value = false;
    }
  } catch (error: any) {
    console.error('Error processing QR code:', error);
    scanResult.value = { error: error.message || 'Error processing QR code.' };
    bookingDetails.value = null;
    showLoadingModal.value = false;
  } finally {
    isProcessing.value = false;
  }
};

const onCameraError = (error: any) => {
  console.group('🎥 Camera Error Details');
  console.error('Error object:', error);
  console.error('Error name:', error.name);
  console.error('Error message:', error.message);
  console.error('Error stack:', error.stack);
  console.error('Navigator userAgent:', typeof navigator !== 'undefined' ? navigator.userAgent : 'N/A');
  console.error('Is HTTPS?', typeof window !== 'undefined' ? window.location.protocol === 'https:' : false);
  console.error('Current URL:', typeof window !== 'undefined' ? window.location.href : 'N/A');

  // Check if getUserMedia is available
  console.error('getUserMedia available?', typeof navigator !== 'undefined' && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia));

  // Check permissions API if available
  if (typeof navigator !== 'undefined' && navigator.permissions) {
    navigator.permissions.query({ name: 'camera' as PermissionName }).then(result => {
      console.error('Camera permission state:', result.state);
    }).catch(permErr => {
      console.error('Could not check camera permissions:', permErr);
    });
  }

  console.groupEnd();

  let errorMessage = `Camera error: ${error.name}`;

  switch (error.name) {
    case 'NotAllowedError':
      errorMessage = 'Camera access denied. Please allow camera access in your browser settings and refresh the page.';
      break;
    case 'NotFoundError':
      errorMessage = 'No camera found. Please ensure a camera is connected to your device.';
      break;
    case 'NotReadableError':
      errorMessage = 'Camera is already in use by another application. Please close other apps using the camera.';
      break;
    case 'OverconstrainedError':
      errorMessage = 'Camera constraints could not be satisfied. Try refreshing the page.';
      break;
    case 'SecurityError':
      errorMessage = 'Camera access blocked due to security restrictions. Ensure you are on HTTPS.';
      break;
    case 'AbortError':
      errorMessage = 'Camera access was aborted. Please try again.';
      break;
    default:
      errorMessage = `Camera error: ${error.name}. ${error.message || 'Please ensure camera access is allowed.'}`;
  }

  cameraError.value = errorMessage;
};

const onScannerReady = () => {
  console.group('✅ Camera Successfully Initialized');
  console.log('Scanner ready at:', new Date().toISOString());
  console.log('Current URL:', typeof window !== 'undefined' ? window.location.href : 'N/A');
  console.log('Is HTTPS?', typeof window !== 'undefined' ? window.location.protocol === 'https:' : false);
  console.log('User agent:', typeof navigator !== 'undefined' ? navigator.userAgent : 'N/A');

  // Log available media devices if possible
  if (typeof navigator !== 'undefined' && navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
    navigator.mediaDevices.enumerateDevices().then(devices => {
      const videoDevices = devices.filter(device => device.kind === 'videoinput');
      console.log('Available video devices:', videoDevices.length);
      videoDevices.forEach((device, index) => {
        console.log(`Video device ${index + 1}:`, {
          deviceId: device.deviceId,
          label: device.label || 'Unknown camera',
          groupId: device.groupId
        });
      });
    }).catch(err => {
      console.error('Could not enumerate devices:', err);
    });
  }

  console.groupEnd();

  scannerReady.value = true;
  cameraError.value = null;
};

const resetScannerState = () => {
  console.group('🔄 Resetting Scanner State');
  console.log('Reset initiated at:', new Date().toISOString());
  console.log('Current state before reset:', {
    isProcessing: isProcessing.value,
    scannerReady: scannerReady.value,
    cameraError: cameraError.value,
    showDetailsModal: showDetailsModal.value,
    showLoadingModal: showLoadingModal.value,
    scanResult: scanResult.value,
    bookingDetails: !!bookingDetails.value
  });

  // Reset all scan-related state
  scanResult.value = null;
  bookingDetails.value = null;
  eventOccurrences.value = [];
  checkInHistory.value = null;
  selectedOccurrenceId.value = null;
  checkInStatus.value = null;
  isProcessing.value = false;
  showDetailsModal.value = false;
  showLoadingModal.value = false;

  // Reset camera/scanner state to allow new scans
  cameraError.value = null;
  // Note: Don't reset scannerReady to false as it will disable the camera
  // The camera should remain active and ready for the next scan

  // Reset form
  checkInForm.reset();
  checkInForm.qr_code_identifier = '';

  console.log('State after reset:', {
    isProcessing: isProcessing.value,
    scannerReady: scannerReady.value,
    cameraError: cameraError.value,
    showDetailsModal: showDetailsModal.value,
    showLoadingModal: showLoadingModal.value,
    shouldShowScanner: shouldShowScanner.value
  });

  // For platform admins, don't require event selection
  if (isPlatformAdmin.value) {
    console.log('Platform admin - scanner should be ready for next scan');
    console.groupEnd();
    return;
  }

  if (isAdmin.value && props.events.length > 0 && !selectedEventId.value) {
    // Regular admin still needs to select event
    console.log('Regular admin - needs event selection');
  } else if (!isAdmin.value) {
    selectedEventId.value = props.events.length === 1 ? props.events[0].id : null;
    console.log('Organizer - auto-selected event:', selectedEventId.value);
  }

  console.groupEnd();
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

    // Handle 204 No Content response (successful check-in)
    if (response.status === 204) {
      checkInStatus.value = { success: true, message: 'Check-in successful!' };
      if (bookingDetails.value) {
        bookingDetails.value.status = 'used';
      }
      // Refresh check-in history
      await refreshCheckInHistory();
    } else {
      const data = await response.json();
      checkInStatus.value = { success: false, message: data.message || 'Check-in failed.' };
    }
  } catch (error: any) {
    console.error('Check-in error:', error);
    checkInStatus.value = { success: false, message: error.message || 'An unexpected error occurred during check-in.' };
  } finally {
    isProcessing.value = false;
  }
};

const refreshCheckInHistory = async () => {
  if (!bookingDetails.value) return;

  try {
    const response = await fetch(route('admin.qr-scanner.validate'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        qr_code: bookingDetails.value.booking_number,
        event_id: selectedEventId.value,
      }),
    });

    const data = await response.json();
    if (response.ok && data.success) {
      checkInHistory.value = data.check_in_history;
      bookingDetails.value = data.booking; // Update booking details
    }
  } catch (error) {
    console.error('Error refreshing check-in history:', error);
  }
};

const closeModalAndReset = () => {
  showDetailsModal.value = false;
  showLoadingModal.value = false;
  resetScannerState();
};

const testCameraAccess = async () => {
  console.group('🧪 Manual Camera Access Test');
  console.log('Test initiated at:', new Date().toISOString());

  if (typeof navigator === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    console.error('❌ getUserMedia not supported');
    alert('Camera API not supported in this browser');
    console.groupEnd();
    return;
  }

  try {
    console.log('Requesting camera access...');
    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: 'environment' // Prefer back camera for QR scanning
      }
    });

    console.log('✅ Camera access granted!');
    console.log('Stream details:', {
      id: stream.id,
      active: stream.active,
      videoTracks: stream.getVideoTracks().length,
      audioTracks: stream.getAudioTracks().length
    });

    // Log video track details
    stream.getVideoTracks().forEach((track, index) => {
      console.log(`Video track ${index + 1}:`, {
        label: track.label,
        kind: track.kind,
        enabled: track.enabled,
        readyState: track.readyState,
        settings: track.getSettings(),
        capabilities: track.getCapabilities ? track.getCapabilities() : 'Not available'
      });
    });

    // Stop the stream after testing
    stream.getTracks().forEach(track => {
      track.stop();
      console.log('Stopped track:', track.label);
    });

    alert('✅ Camera access test successful! Check console for details.');

  } catch (error: any) {
    console.error('❌ Camera access test failed:', error);
    console.error('Error details:', {
      name: error.name,
      message: error.message,
      stack: error.stack
    });

    let userMessage = 'Camera access failed: ';
    switch (error.name) {
      case 'NotAllowedError':
        userMessage += 'Permission denied. Please allow camera access and try again.';
        break;
      case 'NotFoundError':
        userMessage += 'No camera found. Please ensure a camera is connected.';
        break;
      case 'NotReadableError':
        userMessage += 'Camera is in use by another application.';
        break;
      case 'SecurityError':
        userMessage += 'Security error. Ensure you are on HTTPS.';
        break;
      default:
        userMessage += error.message || 'Unknown error occurred.';
    }

    alert(userMessage);
  }

  console.groupEnd();
};

onMounted(() => {
  // Initialize browser API reactive values
  if (typeof window !== 'undefined') {
    isHttps.value = window.location.protocol === 'https:';
    isSecureContext.value = window.isSecureContext;
    hasGetUserMedia.value = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    currentUrl.value = window.location.href;
  }

  console.group('🚀 QR Scanner Component Mounted');
  console.log('Mount time:', new Date().toISOString());
  console.log('Current URL:', currentUrl.value);
  console.log('Protocol:', typeof window !== 'undefined' ? window.location.protocol : 'N/A');
  console.log('Is HTTPS?', isHttps.value);
  console.log('User role:', props.user_role);
  console.log('Is admin?', isAdmin.value);
  console.log('Is platform admin?', isPlatformAdmin.value);
  console.log('Should show scanner?', shouldShowScanner.value);
  console.log('Available events:', props.events.length);
  console.log('Events:', props.events);

  // Check browser capabilities
  console.log('Navigator.mediaDevices available?', typeof navigator !== 'undefined' && !!(navigator.mediaDevices));
  console.log('getUserMedia available?', hasGetUserMedia.value);
  console.log('Permissions API available?', typeof navigator !== 'undefined' && !!(navigator.permissions));

  // Check if we're in a secure context
  console.log('Secure context?', isSecureContext.value);

  // Log user agent for debugging
  console.log('User agent:', typeof navigator !== 'undefined' ? navigator.userAgent : 'N/A');

  // Auto-select event for organizers with single event
  if (!isAdmin.value && props.events.length === 1) {
    selectedEventId.value = props.events[0].id;
    console.log('Auto-selected event for organizer:', props.events[0]);
  }

  // Test camera permissions immediately
  if (typeof navigator !== 'undefined' && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    console.log('Testing camera access...');
    navigator.mediaDevices.getUserMedia({ video: true })
      .then(stream => {
        console.log('✅ Camera access test successful');
        console.log('Stream:', stream);
        console.log('Video tracks:', stream.getVideoTracks().length);
        stream.getVideoTracks().forEach((track, index) => {
          console.log(`Video track ${index + 1}:`, {
            label: track.label,
            enabled: track.enabled,
            readyState: track.readyState,
            settings: track.getSettings()
          });
        });
        // Stop the test stream
        stream.getTracks().forEach(track => track.stop());
      })
      .catch(error => {
        console.error('❌ Camera access test failed:', error);
        console.error('Error details:', {
          name: error.name,
          message: error.message,
          stack: error.stack
        });
      });
  } else {
    console.error('❌ getUserMedia not available');
  }

  console.groupEnd();
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
              <option :value="null">
                {{ isPlatformAdmin ? '-- All Events (Platform Admin) --' : '-- Select an Event --' }}
              </option>
              <option v-for="eventItem in props.events" :key="eventItem.id" :value="eventItem.id">
                {{ eventItem.name }}
              </option>
            </select>
            <p v-if="!selectedEventId && isAdmin && !isPlatformAdmin" class="text-sm text-yellow-600 mt-1">
              Admins: Please select an event to enable the scanner.
            </p>
            <p v-if="isPlatformAdmin" class="text-sm text-blue-600 mt-1">
              Platform Admin: You can scan QR codes for any event. Optionally select a specific event to filter results.
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
                v-if="!cameraError && shouldShowScanner"
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
               <div v-if="(isAdmin && !selectedEventId && props.events.length > 0 && !isPlatformAdmin) || (!isAdmin && props.events.length === 0)" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                  <p class="text-gray-500 dark:text-gray-400 text-center p-4">
                    <span v-if="isAdmin && !selectedEventId && !isPlatformAdmin">Please select an event above to activate the QR scanner.</span>
                    <span v-if="!isAdmin && props.events.length === 0">No events available for scanning.</span>
                  </p>
              </div>
            </div>

            <div class="space-y-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Scan Status</h3>

              <!-- Debug Information -->
              <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md p-3 text-xs">
                <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">Debug Info:</h4>
                <div class="space-y-1 text-blue-800 dark:text-blue-200">
                  <div>🔒 HTTPS: {{ isHttps ? 'Yes' : 'No' }}</div>
                  <div>🎥 Camera API: {{ hasGetUserMedia ? 'Available' : 'Not Available' }}</div>
                  <div>🔐 Secure Context: {{ isSecureContext ? 'Yes' : 'No' }}</div>
                  <div>👤 Role: {{ props.user_role }}</div>
                  <div>🛡️ Platform Admin: {{ isPlatformAdmin ? 'Yes' : 'No' }}</div>
                  <div>📅 Events: {{ props.events.length }}</div>
                  <div>🎯 Selected Event: {{ selectedEventId || 'None' }}</div>
                  <div>📷 Scanner Ready: {{ scannerReady ? 'Yes' : 'No' }}</div>
                  <div>❌ Camera Error: {{ cameraError ? 'Yes' : 'No' }}</div>
                  <div>🔄 Should Show Scanner: {{ shouldShowScanner ? 'Yes' : 'No' }}</div>
                </div>
              </div>
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

              <div v-if="!bookingDetails && !scanResult && !isProcessing && !checkInStatus && shouldShowScanner" class="text-sm text-gray-500 dark:text-gray-400">
                Point the camera at a QR code to scan.
              </div>
               <div v-else-if="!bookingDetails && !scanResult && !isProcessing && !checkInStatus && !shouldShowScanner" class="text-sm text-gray-500 dark:text-gray-400">
                 <span v-if="isAdmin && !selectedEventId && !isPlatformAdmin">Select an event to begin scanning.</span>
                 <span v-else-if="!isAdmin && props.events.length === 0">No events available for scanning.</span>
                 <span v-else>Scanner is idle.</span>
              </div>

            </div>
          </div>
           <div class="mt-6 flex justify-between">
                <PrimaryButton @click="testCameraAccess" class="bg-blue-600 hover:bg-blue-700">
                    Test Camera Access
                </PrimaryButton>
                <SecondaryButton @click="resetScannerState" :disabled="isProcessing && !showDetailsModal">
                    Reset Scanner
                </SecondaryButton>
            </div>
        </div>
      </div>
    </div>

    <!-- Loading Modal -->
    <div v-if="showLoadingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
      <div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Processing QR Code...</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">Validating booking and checking usage history</p>
        </div>
      </div>
    </div>

    <!-- Booking Details Modal -->
    <div v-if="showDetailsModal && bookingDetails" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Booking Details</h3>
            <button @click="closeModalAndReset" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
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
