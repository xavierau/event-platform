<template>
  <div v-if="show && memberDetails" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
      <div class="mt-3">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Member Details</h3>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Member Information -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Member Information</h4>
              <div class="space-y-2 text-sm">
                <div class="flex items-center space-x-2">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                  <span class="font-medium">Name:</span>
                  <span>{{ memberDetails.name }}</span>
                </div>
                <div class="flex items-center space-x-2">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                  </svg>
                  <span class="font-medium">Email:</span>
                  <span>{{ memberDetails.email }}</span>
                </div>
                <div class="flex items-center space-x-2">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                  <span class="font-medium">Member ID:</span>
                  <span class="font-mono text-xs bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded">#{{ memberDetails.id }}</span>
                </div>
              </div>
            </div>
            <div>
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Membership Information</h4>
              <div class="space-y-2 text-sm">
                <div class="flex items-center space-x-2">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                  </svg>
                  <span class="font-medium">Level:</span>
                  <span :class="getMembershipLevelClass(membershipData?.membershipLevel || 'Standard')">
                    {{ membershipData?.membershipLevel || 'Standard' }}
                  </span>
                </div>
                <div class="flex items-center space-x-2">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <span class="font-medium">Status:</span>
                  <span :class="getMembershipStatusClass(membershipData?.membershipStatus || 'Active')">
                    {{ membershipData?.membershipStatus || 'Active' }}
                  </span>
                </div>
                <div v-if="membershipData?.expiresAt" class="flex items-center space-x-2">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                  <span class="font-medium">Expires:</span>
                  <span class="text-xs">{{ formatDate(membershipData.expiresAt) }}</span>
                </div>
                <div class="flex items-center space-x-2">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <span class="font-medium">QR Generated:</span>
                  <span class="text-xs">{{ formatDateTime(membershipData?.timestamp) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Check-in Form -->
        <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4 mb-4">
          <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Record Check-in</h4>
          <div class="space-y-3">
            <div>
              <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Location
              </label>
              <input
                id="location"
                v-model="checkInForm.location"
                type="text"
                placeholder="e.g., Main Entrance, VIP Lounge, Registration Desk"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200 text-sm"
              />
            </div>
            <div>
              <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Notes (Optional)
              </label>
              <textarea
                id="notes"
                v-model="checkInForm.notes"
                rows="2"
                placeholder="Any additional notes about this check-in..."
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-3 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200 text-sm"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Check-in History -->
        <div v-if="checkInHistory && checkInHistory.length > 0" class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-4 mb-4">
          <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Recent Check-in History</h4>
          <div class="space-y-2 max-h-32 overflow-y-auto">
            <div
              v-for="checkIn in checkInHistory.slice(0, 5)"
              :key="checkIn.id"
              class="flex items-center justify-between p-2 bg-white dark:bg-gray-800 rounded text-xs"
            >
              <div class="flex items-center space-x-2">
                <svg class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-gray-600 dark:text-gray-400">{{ formatDateTime(checkIn.scanned_at) }}</span>
                <span v-if="checkIn.location" class="text-gray-500 dark:text-gray-400">@ {{ checkIn.location }}</span>
              </div>
              <div class="text-gray-500 dark:text-gray-400 text-right">
                <div>by {{ checkIn.scanner_name }}</div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
            No previous check-in history found for this member.
          </p>
        </div>

        <!-- Check-in Status -->
        <div v-if="checkInStatus" class="mt-4 p-3 rounded-md text-sm"
            :class="{
                'bg-green-50 dark:bg-green-700 border border-green-300 dark:border-green-600 text-green-700 dark:text-green-200': checkInStatus.success,
                'bg-red-50 dark:bg-red-700 border border-red-300 dark:border-red-600 text-red-700 dark:text-red-200': !checkInStatus.success
            }">
            <div class="flex items-center space-x-2">
              <svg v-if="checkInStatus.success" class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <svg v-else class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 001.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
              </svg>
              <span>{{ checkInStatus.message }}</span>
            </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3 p-5 border-t border-gray-200 dark:border-gray-700">
        <Button variant="outline" @click="$emit('close')">Cancel</Button>
        <Button
          variant="default"
          @click="$emit('check-in')"
          :disabled="isProcessing || checkInStatus?.success === true"
        >
          <span v-if="isProcessing">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Processing...
          </span>
          <span v-else-if="checkInStatus?.success === true">
            ✅ Checked In
          </span>
          <span v-else>
            Record Check-in
          </span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import dayjs from 'dayjs';

interface MemberInfo {
  id: number;
  name: string;
  email: string;
}

interface MemberData {
  userId: number;
  userName: string;
  email: string;
  membershipLevel: string;
  membershipStatus?: string;
  expiresAt?: string;
  timestamp: string;
}

interface MemberCheckInHistoryItem {
  id: number;
  scanned_at: string;
  location?: string;
  notes?: string;
  scanner_name: string;
  membership_data: MemberData;
}

interface CheckInForm {
  qr_code: string;
  location: string;
  notes: string;
  device_identifier: string;
}

interface Props {
  show: boolean;
  memberDetails: MemberInfo | null;
  membershipData: MemberData | null;
  checkInHistory: MemberCheckInHistoryItem[];
  checkInStatus: { success: boolean; message: string } | null;
  checkInForm: CheckInForm;
  isProcessing: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  close: [];
  'check-in': [];
}>();

// Format utilities
const formatDate = (dateString: string): string => {
  if (!dateString) return 'N/A';
  return dayjs(dateString).format('MMM D, YYYY');
};

const formatDateTime = (dateString: string): string => {
  if (!dateString) return 'N/A';
  return dayjs(dateString).format('MMM D, YYYY • HH:mm');
};

// Membership level styling
const getMembershipLevelClass = (level: string): string => {
  switch (level?.toLowerCase()) {
    case 'premium':
    case 'vip':
      return 'px-2 py-1 bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100 rounded-full text-xs font-medium';
    case 'gold':
      return 'px-2 py-1 bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100 rounded-full text-xs font-medium';
    case 'silver':
      return 'px-2 py-1 bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100 rounded-full text-xs font-medium';
    case 'standard':
    default:
      return 'px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100 rounded-full text-xs font-medium';
  }
};

// Membership status styling
const getMembershipStatusClass = (status: string): string => {
  switch (status?.toLowerCase()) {
    case 'active':
      return 'px-2 py-1 bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100 rounded-full text-xs font-medium';
    case 'expired':
      return 'px-2 py-1 bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100 rounded-full text-xs font-medium';
    case 'suspended':
      return 'px-2 py-1 bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100 rounded-full text-xs font-medium';
    default:
      return 'px-2 py-1 bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100 rounded-full text-xs font-medium';
  }
};
</script>
