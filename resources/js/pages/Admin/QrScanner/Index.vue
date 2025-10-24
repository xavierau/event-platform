<script setup lang="ts">
import QRCodeBookingDetailsModal from '@/components/QrScanner/QRCodeBookingDetailsModal.vue';
import QRCodeLoadingModal from '@/components/QrScanner/QRCodeLoadingModal.vue';
import { default as PrimaryButton, default as SecondaryButton } from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { decodeQRCodeData, isQRCodeDataValid } from '@/Utils/qrcode';
import { logger } from '@/Utils/logger';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type { DetectedBarcode } from 'vue-qrcode-reader';
import { QrcodeStream } from 'vue-qrcode-reader';

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
const scannerKey = ref(0); // Add key to force scanner re-render
const lastScannedQr = ref<string | null>(null); // Track last scanned QR to allow re-scanning
const paused = ref(false); // Add paused state for QR scanner

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
    const result = (() => {
        // Platform admins can scan without selecting an event
        if (isPlatformAdmin.value) {
            return true;
        }
        // Organizers need to have events available and select one (if multiple)
        if (!isAdmin.value && props.events.length > 0) {
            return props.events.length === 1 || selectedEventId.value !== null;
        }
        return false;
    })();
    
    logger.qrScanner.stateChange('shouldShowScanner', {
        result,
        isPlatformAdmin: isPlatformAdmin.value,
        isAdmin: isAdmin.value,
        eventsCount: props.events.length,
        selectedEventId: selectedEventId.value,
        userRole: props.user_role
    });
    
    return result;
});

// Form for check-in
const checkInForm = useForm({
    qr_code_identifier: '' as string,
    event_occurrence_id: null as number | null,
    method: 'QR_SCAN',
    device_identifier: null as string | null,
    location_description: null as string | null,
    operator_user_id: props.auth.user?.id,
    notes: null as string | null,
});

watch(selectedEventId, () => {
    resetScannerState();
});

const onDetect = async (detectedCodes: DetectedBarcode[]) => {
    logger.qrScanner.scanAttempt(true, {
        codesDetected: detectedCodes.length,
        isProcessing: isProcessing.value
    });

    if (isProcessing.value || detectedCodes.length === 0) {
        logger.qrScanner.stateChange('onDetect_early_return', {
            isProcessing: isProcessing.value,
            codesLength: detectedCodes.length
        });
        return;
    }

    const rawValue = detectedCodes[0].rawValue;
    
    logger.qrScanner.stateChange('qr_code_detected', {
        rawValue: rawValue.substring(0, 50) + (rawValue.length > 50 ? '...' : ''), // Truncate for logging
        selectedEventId: selectedEventId.value
    });

    // Show loading modal immediately
    showLoadingModal.value = true;
    isProcessing.value = true;
    scanResult.value = null;
    bookingDetails.value = null;
    eventOccurrences.value = [];
    checkInHistory.value = null;
    checkInStatus.value = null;

    try {
        let qrCodeToValidate = rawValue;

        // Try to decode as base64 JSON first (new format)
        try {
            const decodedData = decodeQRCodeData(rawValue);

            if (!isQRCodeDataValid(decodedData, 24 * 30)) {
                scanResult.value = { error: 'QR code has expired or is invalid.' };
                showLoadingModal.value = false;
                isProcessing.value = false;
                return;
            }

            qrCodeToValidate = decodedData.bookingNumber;
        } catch (decodeError) {
            console.warn('Failed to decode QR code data:', decodeError);
            // If decoding fails, treat the raw value as the QR code identifier
            // This handles both BK-format and UUID format QR codes
            qrCodeToValidate = rawValue;
        }

        lastScannedQr.value = rawValue; // Store the original scanned QR

        const response = await fetch(route('admin.qr-scanner.validate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
                Accept: 'application/json',
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
    
    logger.qrScanner.error({
        name: error.name,
        message: errorMessage,
        originalError: error.message,
        shouldShowScanner: shouldShowScanner.value
    });
};

const onScannerReady = () => {
    scannerReady.value = true;
    logger.qrScanner.scannerReady(true);
    cameraError.value = null;
};

const resetScannerState = async () => {
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
    lastScannedQr.value = null; // Reset last scanned QR to allow re-scanning

    // Reset camera/scanner state to allow new scans
    cameraError.value = null;

    // Use the official vue-qrcode-reader approach: pause and unpause to clear internal cache
    // This allows re-scanning of the same QR code without camera re-initialization
    paused.value = true;

    // Brief pause to ensure the scanner processes the pause state
    await new Promise((resolve) => setTimeout(resolve, 100));

    paused.value = false;

    // Reset form
    checkInForm.reset();
    checkInForm.qr_code_identifier = '';
    checkInForm.event_occurrence_id = null;
    checkInForm.device_identifier = null;
    checkInForm.location_description = null;
    checkInForm.notes = null;

    // For platform admins, don't require event selection
    if (isPlatformAdmin.value) {
        return;
    }

    if (isAdmin.value && props.events.length > 0 && !selectedEventId.value) {
        // Regular admin still needs to select event
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
                Accept: 'application/json',
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
        checkInStatus.value = {
            success: false,
            message: error.message || 'An unexpected error occurred during check-in.',
        };
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
                Accept: 'application/json',
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
    if (typeof navigator === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Camera API not supported in this browser');
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment', // Prefer back camera for QR scanning
            },
        });

        // Stop the stream after testing
        stream.getTracks().forEach((track) => {
            track.stop();
        });

        alert('✅ Camera access test successful!');
    } catch (error: any) {
        console.error('Camera access test failed:', error);

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
};

onMounted(() => {
    // Initialize browser API reactive values
    if (typeof window !== 'undefined') {
        isHttps.value = window.location.protocol === 'https:';
        isSecureContext.value = window.isSecureContext;
        hasGetUserMedia.value = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        currentUrl.value = window.location.href;
    }

    // Auto-select event for organizers with single event
    if (!isAdmin.value && props.events.length === 1) {
        selectedEventId.value = props.events[0].id;
    }
    
    // Log initialization state
    logger.qrScanner.init({
        userRole: props.user_role,
        isPlatformAdmin: isPlatformAdmin.value,
        isAdmin: isAdmin.value,
        eventsCount: props.events.length,
        hasGetUserMedia: hasGetUserMedia.value,
        isHttps: isHttps.value,
        isSecureContext: isSecureContext.value,
        autoSelectedEvent: !isAdmin.value && props.events.length === 1 ? props.events[0].id : null,
        shouldShowScanner: shouldShowScanner.value
    });
});

onUnmounted(() => {
    // Cleanup logger
    logger.destroy();
});
</script>

<template>
    <Head title="QR Code Scanner" />
    <AppLayout title="QR Code Scanner">
        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div v-if="props.events.length > 1" class="mb-6">
                        <label for="event-select" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Select Event to Scan For:
                        </label>
                        <select
                            id="event-select"
                            v-model="selectedEventId"
                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 text-base focus:border-indigo-500 focus:ring-2 focus:ring-3 focus:outline-none sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option :value="null">
                                {{ isPlatformAdmin ? '-- All Events (Platform Admin) --' : '-- Select an Event --' }}
                            </option>
                            <option v-for="eventItem in props.events" :key="eventItem.id" :value="eventItem.id">
                                {{ eventItem.name }}
                            </option>
                        </select>
                        <p v-if="!selectedEventId && !isPlatformAdmin" class="mt-1 text-sm text-yellow-600">
                            Please select an event to enable the scanner.
                        </p>
                        <p v-if="isPlatformAdmin" class="mt-1 text-sm text-blue-600">
                            Platform Admin: You can scan QR codes for any event. Optionally select a specific event to filter results.
                        </p>
                    </div>
                    <div v-else-if="!isAdmin && props.events.length === 0" class="mb-4 border-l-4 border-yellow-400 bg-yellow-50 p-4 text-yellow-700">
                        <p>
                            You are not currently an organizer for any published events. Please contact an administrator if you believe this is an
                            error.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div
                            class="relative aspect-square min-h-[300px] overflow-hidden rounded-lg border border-gray-300 md:aspect-auto dark:border-gray-600"
                        >
                            <div v-if="cameraError" class="bg-red-100 p-4 text-sm text-red-700">
                                {{ cameraError }}
                                <p v-if="cameraError.includes('NotAllowedError') || cameraError.includes('NotFoundError')" class="mt-1">
                                    Please grant camera permissions in your browser settings and ensure a camera is connected, then refresh the page.
                                </p>
                            </div>
                            <qrcode-stream
                                v-if="!cameraError && shouldShowScanner"
                                :key="scannerKey"
                                :paused="paused"
                                @detect="onDetect"
                                @error="onCameraError"
                                @camera-on="onScannerReady"
                                :formats="['qr_code']"
                                class="h-full w-full"
                            >
                                <div
                                    v-if="!scannerReady && !cameraError"
                                    class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700"
                                >
                                    <p class="text-gray-500 dark:text-gray-400">Initializing camera...</p>
                                </div>
                            </qrcode-stream>
                            <div
                                v-if="
                                    (isAdmin && !selectedEventId && props.events.length > 0 && !isPlatformAdmin) ||
                                    (!isAdmin && props.events.length === 0)
                                "
                                class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700"
                            >
                                <p class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    <span v-if="isAdmin && !selectedEventId && !isPlatformAdmin"
                                        >Please select an event above to activate the QR scanner.</span
                                    >
                                    <span v-if="!isAdmin && props.events.length === 0">No events available for scanning.</span>
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Scan Status</h3>

                            <!-- Debug Information -->
                            <div class="rounded-md border border-blue-200 bg-blue-50 p-3 text-xs dark:border-blue-700 dark:bg-blue-900">
                                <h4 class="mb-2 font-medium text-blue-900 dark:text-blue-100">Debug Info:</h4>
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
                            <div
                                v-if="isProcessing && !scanResult && !bookingDetails && !checkInStatus"
                                class="flex items-center text-sm text-gray-600 dark:text-gray-400"
                            >
                                <svg
                                    class="mr-3 -ml-1 h-5 w-5 animate-spin text-indigo-600"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                Processing QR code...
                            </div>
                            <div
                                v-if="scanResult?.success"
                                class="rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-700 dark:border-green-600 dark:bg-green-700 dark:text-green-200"
                            >
                                {{ scanResult.success }}
                            </div>
                            <div
                                v-if="scanResult?.error"
                                class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-600 dark:bg-red-700 dark:text-red-200"
                            >
                                {{ scanResult.error }}
                            </div>

                            <div
                                v-if="!bookingDetails && !scanResult && !isProcessing && !checkInStatus && shouldShowScanner"
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Point the camera at a QR code to scan.
                            </div>
                            <div
                                v-else-if="!bookingDetails && !scanResult && !isProcessing && !checkInStatus && !shouldShowScanner"
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                <span v-if="isAdmin && !selectedEventId && !isPlatformAdmin">Select an event to begin scanning.</span>
                                <span v-else-if="!isAdmin && props.events.length === 0">No events available for scanning.</span>
                                <span v-else>Scanner is idle.</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-between">
                        <PrimaryButton @click="testCameraAccess" class="bg-blue-600 hover:bg-blue-700"> Test Camera Access </PrimaryButton>
                        <SecondaryButton @click="resetScannerState" :disabled="isProcessing && !showDetailsModal"> Reset Scanner </SecondaryButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <QRCodeLoadingModal :show="showLoadingModal" />

        <QRCodeBookingDetailsModal
            :show="showDetailsModal"
            :booking-details="bookingDetails"
            :check-in-history="checkInHistory"
            :event-occurrences="eventOccurrences"
            :selected-occurrence-id="selectedOccurrenceId"
            :check-in-status="checkInStatus"
            :is-processing="isProcessing"
            :check-in-form-errors="checkInForm.errors"
            @close="closeModalAndReset"
            @check-in="handleCheckIn"
            @update:selected-occurrence-id="selectedOccurrenceId = $event"
        />
    </AppLayout>
</template>
