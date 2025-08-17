<script setup lang="ts">
import MemberDetailsModal from '@/components/MemberScanner/MemberDetailsModal.vue';
import MemberScannerLoadingModal from '@/components/MemberScanner/MemberScannerLoadingModal.vue';
import { default as PrimaryButton, default as SecondaryButton } from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import type { DetectedBarcode } from 'vue-qrcode-reader';
import { QrcodeStream } from 'vue-qrcode-reader';

// Member-specific interfaces
interface MemberData {
    userId: number;
    userName: string;
    email: string;
    membershipLevel: string;
    membershipStatus?: string;
    expiresAt?: string;
    timestamp: string;
}

interface MemberInfo {
    id: number;
    name: string;
    email: string;
}

interface MemberCheckInHistoryItem {
    id: number;
    scanned_at: string;
    location?: string;
    notes?: string;
    scanner_name: string;
    membership_data: MemberData;
}

// Inertia props
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
    roles: {
        ADMIN: string;
        USER: string;
    };
    user_role: string;
    auth: {
        user: {
            id: number;
            is_admin?: boolean;
            is_organizer_member?: boolean;
        } | null;
    };
}

const page = usePage<CustomPageProps>();
const props = page.props;

// Debug logging
console.log('Page props:', props);
console.log('Auth user:', props.auth?.user);
console.log('Auth user properties:', Object.keys(props.auth?.user || {}));
console.log('Is admin?', props.auth?.user?.is_admin);
console.log('Is organizer member?', props.auth?.user?.is_organizer_member);
console.log('User role:', props.user_role);

// Reactive state
const scannerReady = ref(false);
const scanResult = ref<any>(null);
const memberDetails = ref<MemberInfo | null>(null);
const membershipData = ref<MemberData | null>(null);
const checkInHistory = ref<MemberCheckInHistoryItem[]>([]);
const cameraError = ref<string | null>(null);
const isProcessing = ref(false);
const showDetailsModal = ref(false);
const showLoadingModal = ref(false);
const checkInStatus = ref<{ success: boolean; message: string } | null>(null);
const scannerKey = ref(0);
const lastScannedQr = ref<string | null>(null);
const paused = ref(false);

// Browser API refs (reactive and SSR-safe)
const isHttps = ref(false);
const isSecureContext = ref(false);
const hasGetUserMedia = ref(false);
const currentUrl = ref('');

// Check-in form for recording member check-ins
const checkInForm = useForm({
    qr_code: '' as string,
    location: '' as string,
    notes: '' as string,
    device_identifier: 'web-scanner',
});

// Computed properties
const isAdmin = computed(() => props.auth.user?.is_admin || false);
const isOrganizerMember = computed(() => props.auth.user?.is_organizer_member || false);

const shouldShowScanner = computed(() => {
    // Both platform admins and organizer members can access member scanner
    return isAdmin.value || isOrganizerMember.value;
});

// QR Detection - handles JSON format from MyProfile.vue
const onDetect = async (detectedCodes: DetectedBarcode[]) => {
    if (isProcessing.value || detectedCodes.length === 0) {
        return;
    }

    const rawValue = detectedCodes[0].rawValue;

    // Prevent scanning the same QR code repeatedly
    if (lastScannedQr.value === rawValue) {
        return;
    }

    // Show loading modal immediately
    showLoadingModal.value = true;
    isProcessing.value = true;
    scanResult.value = null;
    memberDetails.value = null;
    membershipData.value = null;
    checkInHistory.value = [];
    checkInStatus.value = null;

    try {
        // Detect QR type - member QR codes are JSON format
        const qrType = detectQrType(rawValue);

        if (qrType.type !== 'member') {
            scanResult.value = { error: 'This QR code is not a member QR code. Please scan a member QR code from the user profile.' };
            showLoadingModal.value = false;
            isProcessing.value = false;
            return;
        }

        lastScannedQr.value = rawValue;

        // Validate the member QR code via API
        const response = await fetch(route('admin.member-scanner.validate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
                Accept: 'application/json',
            },
            body: JSON.stringify({
                qr_code: rawValue,
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            memberDetails.value = data.member;
            membershipData.value = data.membership_data;
            scanResult.value = { success: `Member ${data.member.name} validated successfully.` };

            // Get check-in history
            await fetchCheckInHistory(data.member.id);

            // Hide loading modal and show details modal
            showLoadingModal.value = false;
            showDetailsModal.value = true;
        } else {
            scanResult.value = { error: data.message || 'Failed to validate member QR code.' };
            showLoadingModal.value = false;
        }
    } catch (error: any) {
        console.error('Error processing member QR code:', error);
        scanResult.value = { error: error.message || 'Error processing member QR code.' };
        showLoadingModal.value = false;
    } finally {
        isProcessing.value = false;
    }
};

// QR Type Detection
function detectQrType(rawValue: string): { type: string; data: any } {
    try {
        const parsed = JSON.parse(rawValue);

        // Member QR codes have userId, userName, email, membershipLevel
        if (parsed.userId && parsed.userName && parsed.email && parsed.membershipLevel) {
            return { type: 'member', data: parsed };
        }

        // Booking QR codes start with BK- or are UUIDs
        if (typeof rawValue === 'string' && (rawValue.startsWith('BK-') || rawValue.match(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i))) {
            return { type: 'booking', data: rawValue };
        }
    } catch {
        // Not JSON, check for other formats
        if (typeof rawValue === 'string' && rawValue.startsWith('BK-')) {
            return { type: 'booking', data: rawValue };
        }
    }

    return { type: 'unknown', data: rawValue };
}

// Fetch member check-in history
const fetchCheckInHistory = async (memberId: number) => {
    try {
        const response = await fetch(route('admin.member-scanner.history', memberId), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
            },
        });

        const data = await response.json();
        if (response.ok && data.success) {
            checkInHistory.value = data.history || [];
        }
    } catch (error) {
        console.error('Error fetching check-in history:', error);
        checkInHistory.value = [];
    }
};

// Handle member check-in
const handleCheckIn = async () => {
    if (!memberDetails.value || !membershipData.value) {
        checkInStatus.value = { success: false, message: 'No member selected for check-in.' };
        return;
    }

    // Set form data
    checkInForm.qr_code = lastScannedQr.value || '';
    checkInForm.location = checkInForm.location || 'Member Scanner';

    isProcessing.value = true;

    try {
        const response = await fetch(route('admin.member-scanner.check-in'), {
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
            checkInStatus.value = { success: true, message: 'Member check-in successful!' };

            // Refresh check-in history
            await fetchCheckInHistory(memberDetails.value.id);

            // Clear form
            checkInForm.location = '';
            checkInForm.notes = '';
        } else {
            const data = await response.json();
            checkInStatus.value = { success: false, message: data.message || 'Check-in failed.' };
        }
    } catch (error: any) {
        console.error('Member check-in error:', error);
        checkInStatus.value = {
            success: false,
            message: error.message || 'An unexpected error occurred during check-in.',
        };
    } finally {
        isProcessing.value = false;
    }
};

// Camera error handling
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

// Reset scanner state
const resetScannerState = async () => {
    // Reset all scan-related state
    scanResult.value = null;
    memberDetails.value = null;
    membershipData.value = null;
    checkInHistory.value = [];
    checkInStatus.value = null;
    isProcessing.value = false;
    showDetailsModal.value = false;
    showLoadingModal.value = false;
    lastScannedQr.value = null;

    // Reset camera/scanner state
    cameraError.value = null;

    // Pause and unpause to clear internal cache
    paused.value = true;
    await new Promise((resolve) => setTimeout(resolve, 100));
    paused.value = false;

    // Reset form
    checkInForm.reset();
    checkInForm.qr_code = '';
    checkInForm.location = '';
    checkInForm.notes = '';
};

const closeModalAndReset = () => {
    showDetailsModal.value = false;
    showLoadingModal.value = false;
    resetScannerState();
};

// Test camera access
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
});

onUnmounted(() => {
    // Cleanup if necessary
});
</script>

<template>
    <Head title="Member Scanner" />
    <AppLayout title="Member Scanner">
        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <!-- Access Control Notice -->
                    <div v-if="!shouldShowScanner" class="mb-4 border-l-4 border-red-400 bg-red-50 p-4 text-red-700">
                        <p>
                            Access denied. Only platform administrators or organizer members can access the member scanner.
                        </p>
                    </div>

                    <!-- Scanner Interface -->
                    <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Camera Scanner -->
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
                                v-if="!shouldShowScanner"
                                class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700"
                            >
                                <p class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    Platform admin or organizer membership required to use member scanner.
                                </p>
                            </div>
                        </div>

                        <!-- Status Panel -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Member Scanner Status</h3>

                            <!-- Instructions -->
                            <div class="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm dark:border-blue-700 dark:bg-blue-900">
                                <h4 class="mb-2 font-medium text-blue-900 dark:text-blue-100">Instructions:</h4>
                                <div class="space-y-1 text-blue-800 dark:text-blue-200">
                                    <div>📱 Ask members to open their profile page</div>
                                    <div>🔍 Have them show their member QR code</div>
                                    <div>📷 Point the camera at the member's QR code</div>
                                    <div>✅ System will validate and show member details</div>
                                </div>
                            </div>

                            <!-- Debug Information -->
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs dark:border-gray-700 dark:bg-gray-900">
                                <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">System Status:</h4>
                                <div class="space-y-1 text-gray-800 dark:text-gray-200">
                                    <div>🔒 HTTPS: {{ isHttps ? 'Yes' : 'No' }}</div>
                                    <div>🎥 Camera API: {{ hasGetUserMedia ? 'Available' : 'Not Available' }}</div>
                                    <div>🔐 Secure Context: {{ isSecureContext ? 'Yes' : 'No' }}</div>
                                    <div>👤 Role: {{ props.user_role }}</div>
                                    <div>📷 Scanner Ready: {{ scannerReady ? 'Yes' : 'No' }}</div>
                                    <div>❌ Camera Error: {{ cameraError ? 'Yes' : 'No' }}</div>
                                    <div>🔄 Should Show Scanner: {{ shouldShowScanner ? 'Yes' : 'No' }}</div>
                                </div>
                            </div>

                            <!-- Processing Status -->
                            <div
                                v-if="isProcessing && !scanResult && !memberDetails && !checkInStatus"
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
                                Processing member QR code...
                            </div>

                            <!-- Success Messages -->
                            <div
                                v-if="scanResult?.success"
                                class="rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-700 dark:border-green-600 dark:bg-green-700 dark:text-green-200"
                            >
                                {{ scanResult.success }}
                            </div>

                            <!-- Error Messages -->
                            <div
                                v-if="scanResult?.error"
                                class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-600 dark:bg-red-700 dark:text-red-200"
                            >
                                {{ scanResult.error }}
                            </div>

                            <!-- Idle State -->
                            <div
                                v-if="!memberDetails && !scanResult && !isProcessing && !checkInStatus && shouldShowScanner"
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Point the camera at a member QR code to scan.
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
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
        <MemberScannerLoadingModal :show="showLoadingModal" />

        <MemberDetailsModal
            :show="showDetailsModal"
            :member-details="memberDetails"
            :membership-data="membershipData"
            :check-in-history="checkInHistory"
            :check-in-status="checkInStatus"
            :check-in-form="checkInForm"
            :is-processing="isProcessing"
            @close="closeModalAndReset"
            @check-in="handleCheckIn"
        />
    </AppLayout>
</template>
