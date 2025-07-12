<template>
  <div v-if="show && couponDetails" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
      <div class="mt-3">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Coupon Details</h3>
          <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <!-- Close icon -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Coupon Information -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Coupon Information</h4>
              <div class="space-y-1 text-sm">
                <div><span class="font-medium">Name:</span> {{ couponDetails.details.coupon.name }}</div>
                <div><span class="font-medium">Code:</span> {{ couponDetails.user_coupon.unique_code }}</div>
                <div><span class="font-medium">Type:</span> {{ formatType(couponDetails.details.coupon.type) }}</div>
                <div><span class="font-medium">Discount:</span> {{ formatDiscount(couponDetails.details.coupon.discount_value, couponDetails.details.coupon.discount_type) }}</div>
              </div>
            </div>
            <div>
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Validity & Status</h4>
              <div class="space-y-1 text-sm">
                <div><span class="font-medium">Status:</span>
                  <span :class="statusClass(couponDetails.user_coupon.status)">
                    {{ couponDetails.user_coupon.status }}
                  </span>
                </div>
                <div><span class="font-medium">Valid From:</span> {{ formatDate(couponDetails.details.coupon.valid_from) }}</div>
                <div><span class="font-medium">Expires At:</span> {{ formatDate(couponDetails.details.coupon.expires_at) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Usage History -->
        <div v-if="couponHistory" class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-4 mb-4">
          <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Usage History</h4>
          <!-- Simplified usage display -->
        </div>

        <div v-if="redeemStatus" class="mt-4 p-3 rounded-md text-sm"
            :class="{
                'bg-green-50 dark:bg-green-700 border border-green-300 dark:border-green-600 text-green-700 dark:text-green-200': redeemStatus.success,
                'bg-red-50 dark:bg-red-700 border border-red-300 dark:border-red-600 text-red-700 dark:text-red-200': !redeemStatus.success
            }">
            {{ redeemStatus.message }}
        </div>
      </div>

      <div class="flex justify-end space-x-3 p-5 border-t border-gray-200 dark:border-gray-700">
        <button @click="emit('close')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Cancel</button>
        <button
          @click="emit('redeem')"
          :disabled="isProcessing || couponDetails.user_coupon.status !== 'ACTIVE' || redeemStatus?.success === true"
          class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:bg-indigo-300"
        >
          <span v-if="isProcessing && !redeemStatus">Redeeming...</span>
          <span v-else-if="redeemStatus?.success === true">Redeemed</span>
          <span v-else-if="couponDetails.user_coupon.status !== 'ACTIVE'">Cannot Redeem</span>
          <span v-else>Confirm Redemption</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import dayjs from 'dayjs';

interface Props {
  show: boolean;
  couponDetails: any;
  couponHistory: any;
  redeemStatus: { success: boolean; message: string } | null;
  isProcessing: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
  close: [];
  redeem: [];
}>();

const formatType = (type: string) => type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

const formatDiscount = (value: number, type: 'fixed' | 'percentage') => {
    if (type === 'fixed') {
        return `$${(value / 100).toFixed(2)}`;
    }
    return `${value}%`;
};

const formatDate = (date: string | null) => {
    if (!date) return 'N/A';
    return dayjs(date).format('MMM D, YYYY');
};

const statusClass = (status: string) => {
    const base = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full';
    switch (status) {
        case 'ACTIVE': return `${base} bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100`;
        case 'FULLY_USED': return `${base} bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100`;
        case 'EXPIRED': return `${base} bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100`;
        default: return `${base} bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100`;
    }
};

</script>
