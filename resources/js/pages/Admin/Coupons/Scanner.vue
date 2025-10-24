<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PrimaryButton from '../../../components/ui/button/Button.vue';
import SecondaryButton from '../../../components/ui/button/Button.vue';
import QRCodeLoadingModal from '@/components/QrScanner/QRCodeLoadingModal.vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import type { DetectedBarcode } from 'vue-qrcode-reader';
import AppLayout from '@/layouts/AppLayout.vue';



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
const scanResult = ref<any>(null);
const cameraError = ref<string | null>(null);
const isProcessing = ref(false);
const showDetailsModal = ref(false);
const showLoadingModal = ref(false);
const scannerKey = ref(0);
const paused = ref(false);
const couponDetails = ref<any>(null);

// Browser API refs (reactive and SSR-safe)
const isHttps = ref(false);
const isSecureContext = ref(false);
const hasGetUserMedia = ref(false);
const currentUrl = ref('');
const redeemForm = useForm({
    unique_code: '' as string,
    location: 'Scanner' as string,
    details: {} as any,
    operator_user_id: props.auth.user?.id,
});

const isAdmin = computed(() => props.user_role === 'admin');

// Coupon scanner is available to all authenticated users
const shouldShowScanner = computed(() => true);

const onDetect = async (detectedCodes: DetectedBarcode[]) => {
    if (isProcessing.value) {
        return;
    }

    if (detectedCodes.length === 0) {
        return;
    }

    const rawValue = detectedCodes[0].rawValue;

    // Show loading modal immediately
    showLoadingModal.value = true;
    isProcessing.value = true;
    scanResult.value = null;

       try {
           const response = await fetch(route('api.v1.coupon-scanner.show', { uniqueCode: rawValue }));
           const data = await response.json();

           if (response.ok && data.success) {
               couponDetails.value = data.data;
               scanResult.value = { success: `Coupon ${data.data.user_coupon.unique_code} found.` };
               redeemForm.unique_code = data.data.user_coupon.unique_code;
               showDetailsModal.value = true;
           } else {
               scanResult.value = { error: data.message || 'Failed to validate coupon.' };
           }
       } catch (error: any) {
           scanResult.value = { error: error.message || 'Error processing QR code.' };
       } finally {
           showLoadingModal.value = false;
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
};

const onScannerReady = () => {
    scannerReady.value = true;
    cameraError.value = null;
};

const resetScannerState = async () => {
    // Reset all scan-related state
    scanResult.value = null;
    couponDetails.value = null;
    isProcessing.value = false;
    showDetailsModal.value = false;
    showLoadingModal.value = false;

    // Reset camera/scanner state to allow new scans
    cameraError.value = null;

    // Use the official vue-qrcode-reader approach: pause and unpause to clear internal cache
    // This allows re-scanning of the same QR code without camera re-initialization
    paused.value = true;

    // Brief pause to ensure the scanner processes the pause state
    await new Promise(resolve => setTimeout(resolve, 100));

    paused.value = false;
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
                facingMode: 'environment' // Prefer back camera for QR scanning
            }
        });

        // Stop the stream after testing
        stream.getTracks().forEach(track => {
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
});

onUnmounted(() => {
    // Cleanup if necessary
});

</script>

<template>
    <Head title="Coupon Scanner" />
    <AppLayout title="Coupon Scanner">
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">

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
                                :key="scannerKey"
                                :paused="paused"
                                @detect="onDetect"
                                @error="onCameraError"
                                @camera-on="onScannerReady"
                                :formats="['qr_code']"
                                class="w-full h-full"
                            >
                                <div v-if="!scannerReady && !cameraError" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                                    <p class="text-gray-500 dark:text-gray-400">Initializing camera...</p>
                                </div>
                            </qrcode-stream>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Scan Status</h3>

                            <!-- Debug Information -->
                            <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md p-3 text-xs">
                                <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">System Status:</h4>
                                <div class="space-y-1 text-blue-800 dark:text-blue-200">
                                    <div>🔒 HTTPS: {{ isHttps ? 'Yes' : 'No' }}</div>
                                    <div>🎥 Camera API: {{ hasGetUserMedia ? 'Available' : 'Not Available' }}</div>
                                    <div>🔐 Secure Context: {{ isSecureContext ? 'Yes' : 'No' }}</div>
                                    <div>📷 Scanner Ready: {{ scannerReady ? 'Yes' : 'No' }}</div>
                                    <div>❌ Camera Error: {{ cameraError ? 'Yes' : 'No' }}</div>
                                </div>
                            </div>
                            <div v-if="isProcessing && !scanResult && !couponDetails" class="flex items-center text-sm text-gray-600 dark:text-gray-400">
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

                            <div v-if="!couponDetails && !scanResult && !isProcessing && shouldShowScanner" class="text-sm text-gray-500 dark:text-gray-400">
                                Point the camera at a coupon QR code to scan.
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

        <!-- Modals -->
        <QRCodeLoadingModal :show="showLoadingModal" />

        <!-- TODO: Add coupon details modal for viewing coupon info and redemption -->

    </AppLayout>
</template>
