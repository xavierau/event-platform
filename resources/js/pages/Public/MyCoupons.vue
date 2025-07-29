<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import QRCode from 'qrcode';
import FrontendFooter from '@/components/FrontendFooter.vue';

// Access the global route function
declare const route: any;

dayjs.extend(utc);

interface CouponUsageLog {
  id: number;
  created_at: string;
  location: string | null;
}

interface Coupon {
  id: number;
  name: string;
  description: string;
  code: string;
  redemption_methods: string[];
  has_pin: boolean;
}

interface UserCoupon {
  id: number;
  unique_code: string;
  status: string;
  times_used: number;
  times_can_be_used: number;
  expires_at: string | null;
  created_at: string;
  coupon: Coupon;
  usage_logs: CouponUsageLog[];
  is_expired: boolean;
  is_fully_used: boolean;
  is_active: boolean;
}

interface CouponStatistics {
  total: number;
  active: number;
  expired: number;
  fully_used: number;
}

interface Filters {
  status: string;
  search: string | null;
}

const props = defineProps({
  coupons: {
    type: Object as () => ({ data: UserCoupon[], links: any[], meta: any }),
    required: true,
  },
  statistics: {
    type: Object as () => CouponStatistics,
    required: true,
  },
  filters: {
    type: Object as () => Filters,
    required: true,
  },
});

// State management
const searchQuery = ref(props.filters.search || '');
const activeFilter = ref(props.filters.status || 'all');
const showQrModal = ref(false);
const showPinModal = ref(false);
const selectedCoupon = ref<UserCoupon | null>(null);
const qrCodeDataUrl = ref<string>('');
const merchantPin = ref('');
const isProcessingPin = ref(false);
const pinError = ref('');

// Filter options
const filterOptions = [
  { key: 'all', label: 'All Coupons', count: props.statistics.total },
  { key: 'active', label: 'Active', count: props.statistics.active },
  { key: 'expired', label: 'Expired', count: props.statistics.expired },
  { key: 'used', label: 'Fully Used', count: props.statistics.fully_used },
];

// Methods
function setFilter(filter: string) {
  activeFilter.value = filter;
  applyFilters();
}

function applyFilters() {
  router.get('/my-coupons', {
    status: activeFilter.value,
    search: searchQuery.value || undefined,
  }, {
    preserveState: true,
    replace: true,
  });
}

function handleSearch() {
  applyFilters();
}

function clearSearch() {
  searchQuery.value = '';
  applyFilters();
}

async function showQrCode(coupon: UserCoupon) {
  selectedCoupon.value = coupon;

  try {
    // Generate QR code from the coupon's unique code
    const qrCodeUrl = await QRCode.toDataURL(coupon.unique_code, {
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
    qrCodeDataUrl.value = '';
  }

  showQrModal.value = true;
}

function showPinRedemption(coupon: UserCoupon) {
  selectedCoupon.value = coupon;
  merchantPin.value = '';
  pinError.value = '';
  isProcessingPin.value = false;
  showPinModal.value = true;
}

async function submitPinRedemption() {
  if (!selectedCoupon.value || !merchantPin.value) return;
  
  if (merchantPin.value.length !== 6) {
    pinError.value = 'PIN must be exactly 6 digits';
    return;
  }
  
  isProcessingPin.value = true;
  pinError.value = '';
  
  try {
    const response = await fetch('/api/v1/coupons/redeem-by-pin', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        unique_code: selectedCoupon.value.unique_code,
        merchant_pin: merchantPin.value,
        location: 'User Device - My Coupons',
      }),
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Success - close modal and refresh page or show success message
      showPinModal.value = false;
      // Refresh the page to show updated coupon status
      router.reload();
    } else {
      pinError.value = data.message || 'Invalid PIN. Please try again.';
    }
  } catch (error) {
    console.error('PIN redemption error:', error);
    pinError.value = 'An error occurred. Please try again.';
  } finally {
    isProcessingPin.value = false;
  }
}

function getStatusColor(coupon: UserCoupon): string {
  if (coupon.is_active) return 'text-green-600 bg-green-100';
  if (coupon.is_expired) return 'text-yellow-600 bg-yellow-100';
  if (coupon.is_fully_used) return 'text-gray-600 bg-gray-100';
  return 'text-gray-600 bg-gray-100';
}

function getStatusText(coupon: UserCoupon): string {
  if (coupon.is_active) return 'Active';
  if (coupon.is_expired) return 'Expired';
  if (coupon.is_fully_used) return 'Fully Used';
  return 'Inactive';
}

function formatDate(dateString: string): string {
  return dayjs(dateString).format('MMM D, YYYY h:mm A');
}

function getUsageText(coupon: UserCoupon): string {
  return `${coupon.times_used} / ${coupon.times_can_be_used} uses`;
}

function canRedeem(coupon: UserCoupon): boolean {
  return coupon.is_active;
}

function hasQrRedemption(coupon: UserCoupon): boolean {
  return coupon.coupon.redemption_methods.includes('qr');
}

function hasPinRedemption(coupon: UserCoupon): boolean {
  return coupon.coupon.redemption_methods.includes('pin');
}
</script>

<template>
<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
<Head title="My Coupons" />

    <!-- Header Section -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50 border-b dark:border-gray-700">
      <div class="container mx-auto flex items-center p-4 relative">
        <Link href="/" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 absolute left-4">
          ← Back
        </Link>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 flex-1 text-center">My Coupons</h1>
      </div>
    </header>

    <main class="container mx-auto py-6 px-4 pb-24">
      <!-- Subtitle -->
      <div class="mb-6">
        <p class="text-gray-600 dark:text-gray-300 text-center">Manage and redeem your coupons</p>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ statistics.total }}</div>
          <div class="text-sm text-gray-600 dark:text-gray-400">Total Coupons</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ statistics.active }}</div>
          <div class="text-sm text-gray-600 dark:text-gray-400">Active</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ statistics.expired }}</div>
          <div class="text-sm text-gray-600 dark:text-gray-400">Expired</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ statistics.fully_used }}</div>
          <div class="text-sm text-gray-600 dark:text-gray-400">Fully Used</div>
        </div>
      </div>

      <!-- Filters and Search -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <!-- Filter Tabs -->
          <div class="flex flex-wrap gap-2 mb-4">
            <button
              v-for="option in filterOptions"
              :key="option.key"
              @click="setFilter(option.key)"
              :class="[
                'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                activeFilter === option.key
                  ? 'bg-blue-100 dark:bg-blue-700 text-blue-700 dark:text-blue-200 border border-blue-300 dark:border-blue-600'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
              ]"
            >
              {{ option.label }} ({{ option.count }})
            </button>
          </div>

          <!-- Search Bar -->
          <div class="flex gap-4">
            <div class="flex-1">
              <input
                v-model="searchQuery"
                @keyup.enter="handleSearch"
                type="text"
                placeholder="Search by coupon name, description, or code..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
            </div>
            <button
              @click="handleSearch"
              class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors"
            >
              Search
            </button>
            <button
              v-if="searchQuery"
              @click="clearSearch"
              class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors"
            >
              Clear
            </button>
          </div>
        </div>
      </div>

      <!-- Coupons Grid -->
      <div v-if="coupons.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div
          v-for="coupon in coupons.data"
          :key="coupon.id"
          class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden"
        >
          <!-- Coupon Header -->
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-start mb-2">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ coupon.coupon.name }}</h3>
              <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStatusColor(coupon)]">
                {{ getStatusText(coupon) }}
              </span>
            </div>
            <p class="text-gray-600 dark:text-gray-400 text-sm mb-3">{{ coupon.coupon.description }}</p>
            <div class="text-sm text-gray-500 dark:text-gray-400">
              <div>Code: <span class="font-mono">{{ coupon.unique_code }}</span></div>
              <div>Usage: {{ getUsageText(coupon) }}</div>
              <div v-if="coupon.expires_at">
                Expires: {{ formatDate(coupon.expires_at) }}
              </div>
            </div>
          </div>

          <!-- Redemption Methods -->
          <div class="p-6">
            <div class="flex flex-wrap gap-2">
              <!-- QR Code Redemption -->
              <button
                v-if="hasQrRedemption(coupon)"
                @click="showQrCode(coupon)"
                :disabled="!canRedeem(coupon)"
                :class="[
                  'flex-1 px-4 py-2 rounded-md text-sm font-medium transition-colors',
                  canRedeem(coupon)
                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                ]"
              >
                Show QR Code
              </button>

              <!-- PIN Redemption -->
              <button
                v-if="hasPinRedemption(coupon)"
                @click="showPinRedemption(coupon)"
                :disabled="!canRedeem(coupon)"
                :class="[
                  'flex-1 px-4 py-2 rounded-md text-sm font-medium transition-colors',
                  canRedeem(coupon)
                    ? 'bg-green-600 text-white hover:bg-green-700'
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                ]"
              >
                Redeem by PIN
              </button>
            </div>

            <!-- Usage History -->
            <div v-if="coupon.usage_logs.length > 0" class="mt-4 pt-4 border-t border-gray-200">
              <h4 class="text-sm font-medium text-gray-900 mb-2">Recent Usage</h4>
              <div class="space-y-1">
                <div
                  v-for="log in coupon.usage_logs.slice(0, 2)"
                  :key="log.id"
                  class="text-xs text-gray-600"
                >
                  {{ formatDate(log.created_at) }}
                  <span v-if="log.location"> at {{ log.location }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <div class="text-gray-400 text-6xl mb-4">🎫</div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No coupons found</h3>
        <p class="text-gray-600">
          {{ searchQuery ? 'Try adjusting your search or filters.' : 'You don\'t have any coupons yet.' }}
        </p>
      </div>

      <!-- Pagination -->
      <div v-if="coupons.links && coupons.links.length > 3" class="flex justify-center">
        <nav class="flex space-x-2">
          <Link
            v-for="link in coupons.links"
            :key="link.label"
            :href="link.url"
            :class="[
              'px-3 py-2 text-sm rounded-md',
              link.active
                ? 'bg-blue-600 text-white'
                : link.url
                ? 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            ]"
            v-html="link.label"
          />
        </nav>
      </div>
    </main>

    <FrontendFooter />

    <!-- QR Code Modal -->
    <div v-if="showQrModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-start mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">QR Code for Redemption</h3>
          <button @click="showQrModal = false" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <div v-if="selectedCoupon" class="text-center">
          <div class="mb-4">
            <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ selectedCoupon.coupon.name }}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ selectedCoupon.unique_code }}</p>
          </div>

          <!-- QR Code -->
          <div class="w-64 h-64 mx-auto bg-white border border-gray-200 dark:border-gray-600 rounded-lg flex items-center justify-center mb-4">
            <img
              v-if="qrCodeDataUrl"
              :src="qrCodeDataUrl"
              alt="QR Code for coupon redemption"
              class="w-full h-full object-contain p-2"
            />
            <div v-else class="text-center">
              <div class="text-gray-400 text-2xl mb-2">📱</div>
              <div class="text-sm text-gray-600 dark:text-gray-400">Generating QR Code...</div>
            </div>
          </div>

          <p class="text-sm text-gray-600 dark:text-gray-400">Show this QR code to the merchant for scanning</p>
        </div>
      </div>
    </div>

    <!-- PIN Redemption Modal -->
    <div v-if="showPinModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-start mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">PIN Redemption</h3>
          <button @click="showPinModal = false" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <div v-if="selectedCoupon" class="text-center">
          <div class="mb-6">
            <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ selectedCoupon.coupon.name }}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ selectedCoupon.unique_code }}</p>
          </div>

          <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
            <div class="text-blue-800 dark:text-blue-200 text-sm">
              <div class="font-medium mb-2">🔐 PIN Redemption Process</div>
              <ol class="text-left space-y-1 text-xs">
                <li>1. Hand your device to the merchant</li>
                <li>2. Merchant enters their 6-digit PIN below</li>
                <li>3. Coupon will be redeemed automatically</li>
              </ol>
            </div>
          </div>

          <!-- PIN Input Form -->
          <div class="space-y-4">
            <div>
              <label for="merchantPin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Merchant PIN (6 digits)
              </label>
              <input
                id="merchantPin"
                v-model="merchantPin"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="Enter 6-digit PIN"
                class="w-full px-4 py-3 text-center text-lg font-mono border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                :disabled="isProcessingPin"
                @keyup.enter="submitPinRedemption"
              >
            </div>
            
            <!-- Error Message -->
            <div v-if="pinError" class="text-red-600 dark:text-red-400 text-sm text-center">
              {{ pinError }}
            </div>
            
            <!-- Submit Button -->
            <button
              @click="submitPinRedemption"
              :disabled="isProcessingPin || merchantPin.length !== 6"
              class="w-full py-3 px-4 rounded-md text-white font-medium transition-colors"
              :class="[
                isProcessingPin || merchantPin.length !== 6
                  ? 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed'
                  : 'bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800'
              ]"
            >
              <span v-if="isProcessingPin">Processing...</span>
              <span v-else>Redeem Coupon</span>
            </button>
          </div>
        </div>
      </div>
  </div>
</div>
</template>

<style scoped>
.shadow-top-lg {
  box-shadow: 0 -4px 6px -1px rgb(0 0 0 / 0.05), 0 -2px 4px -2px rgb(0 0 0 / 0.05);
}
</style>
