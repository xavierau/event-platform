<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CouponLoadingModal from '@/components/CouponScanner/CouponLoadingModal.vue';
import CouponDetailsModal from '@/components/CouponScanner/CouponDetailsModal.vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import type { DetectedBarcode } from 'vue-qrcode-reader';

interface OrganizerItem {
  id: number;
  name: string;
}

interface InertiaSharedProps {
  auth: {
    user: {
      id: number;
    } | null;
  };
  errors?: Record<string, string>;
  [key: string]: any;
}

interface CustomPageProps extends InertiaSharedProps {
  organizers: OrganizerItem[];
}

const page = usePage<CustomPageProps>();
const props = page.props;

const scannerReady = ref(false);
const scanResult = ref<any>(null);
const couponDetails = ref<any>(null);
const couponHistory = ref<any>(null);
const cameraError = ref<string | null>(null);
const isProcessing = ref(false);
const showDetailsModal = ref(false);
const showLoadingModal = ref(false);
const redeemStatus = ref<{ success: boolean; message: string } | null>(null);
const scannerKey = ref(0);
const lastScannedQr = ref<string | null>(null);
const paused = ref(false);

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

const onDetect = async (detectedCodes: DetectedBarcode[]) => {
    if (isProcessing.value || detectedCodes.length === 0) {
        return;
    }

    const rawValue = detectedCodes[0].rawValue;
    lastScannedQr.value = rawValue;

    showLoadingModal.value = true;
    isProcessing.value = true;
    scanResult.value = null;
    couponDetails.value = null;
    redeemStatus.value = null;

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

const handleRedeem = async () => {
    if (!redeemForm.unique_code) return;

    isProcessing.value = true;
    redeemStatus.value = null;

    try {
        const response = await fetch(route('api.v1.coupon-scanner.redeem', { uniqueCode: redeemForm.unique_code }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(redeemForm.data()),
        });
        const data = await response.json();
        redeemStatus.value = { success: response.ok && data.success, message: data.message };
    } catch (error: any) {
        redeemStatus.value = { success: false, message: error.message || 'An unexpected error occurred.' };
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
};

const onScannerReady = () => {
  scannerReady.value = true;
  cameraError.value = null;
};

const resetScannerState = async () => {
  scanResult.value = null;
  couponDetails.value = null;
  couponHistory.value = null;
  redeemStatus.value = null;
  isProcessing.value = false;
  showDetailsModal.value = false;
  showLoadingModal.value = false;
  lastScannedQr.value = null;

  cameraError.value = null;
  paused.value = true;

  await new Promise(resolve => setTimeout(resolve, 100));

  paused.value = false;
  redeemForm.reset();
  redeemForm.unique_code = '';
};

onMounted(() => {
  isHttps.value = window.location.protocol === 'https:';
  isSecureContext.value = window.isSecureContext;
  hasGetUserMedia.value = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  currentUrl.value = window.location.href;
});

onUnmounted(() => {
  // Cleanup if needed
});
</script>

<template>
    <Head title="Coupon Scanner" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Coupon QR Code Scanner</h1>
                        <div v-if="!isSecureContext" class="mt-4 p-4 bg-red-100 text-red-700 rounded-md">
                            Camera access requires a secure context (HTTPS). You are currently on an insecure connection.
                        </div>
                        <div v-else-if="!hasGetUserMedia" class="mt-4 p-4 bg-red-100 text-red-700 rounded-md">
                            Your browser does not support the necessary camera APIs. Please try a different browser.
                        </div>
                        <div v-else>
                            <div class="mt-6">
                                <div class="relative w-full max-w-md mx-auto border-4 border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <QrcodeStream :key="scannerKey" @detect="onDetect" @camera-error="onCameraError" @ready="onScannerReady" :paused="paused" />
                                    <div v-if="!scannerReady" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                                        <p class="text-white text-lg">Initializing Camera...</p>
                                    </div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-64 h-64 border-4 border-red-500 rounded-lg opacity-50"></div>
                                    </div>
                                </div>
                                <div v-if="cameraError" class="mt-4 p-4 text-center bg-red-100 text-red-700 rounded-md">
                                    {{ cameraError }}
                                </div>
                                <div v-if="scanResult && scanResult.error" class="mt-4 p-4 text-center bg-red-100 text-red-700 rounded-md">
                                    {{ scanResult.error }}
                                </div>
                                <div class="mt-4 text-center">
                                    <button @click="resetScannerState" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md">Reset Scanner</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <CouponLoadingModal :show="showLoadingModal" />
        <CouponDetailsModal
            :show="showDetailsModal"
            :coupon-details="couponDetails"
            :coupon-history="couponHistory"
            :redeem-status="redeemStatus"
            :is-processing="isProcessing"
            @close="resetScannerState"
            @redeem="handleRedeem"
        />
    </AppLayout>
</template>
