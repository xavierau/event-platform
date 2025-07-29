<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { debounce } from 'lodash';
import axios from 'axios';

interface Coupon {
  id: number;
  name: string;
  code: string;
  organizer_id: number;
  type: string;
  discount_value: number;
  discount_type: string;
}

interface Organizer {
  id: number;
  name: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  member_since: string;
}

interface UserStats {
  total_users: number;
  active_users: number;
  users_with_coupons: number;
}

interface AssignmentHistory {
  id: number;
  user_name: string;
  user_email: string;
  coupon_name: string;
  coupon_code: string;
  unique_code: string;
  status: string;
  assigned_at: string;
  assigned_by: string;
  notes: string | null;
}

const props = defineProps<{
  coupons: Coupon[];
  organizers: Organizer[];
  preSelectedCouponId?: number | null;
  preSelectedCoupon?: Coupon | null;
}>();

// State management
const currentStep = ref(1);
const searchQuery = ref('');
const organizerFilter = ref('');
const searchResults = ref<User[]>([]);
const selectedUsers = ref<User[]>([]);
const selectedCoupon = ref<Coupon | null>(props.preSelectedCoupon || null);
const userStats = ref<UserStats | null>(null);
const isSearching = ref(false);
const isLoadingStats = ref(false);
const showHistory = ref(false);
const assignmentHistory = ref<AssignmentHistory[]>([]);

// Assignment form
const assignmentForm = useForm({
  coupon_id: props.preSelectedCouponId || null as number | null,
  user_ids: [] as number[],
  quantity: 1,
  expires_at: '',
  notes: '',
  processing: false,
  errors: {} as Record<string, string>,
});

// Computed properties
const selectedUserIds = computed(() => selectedUsers.value.map(user => user.id));

const pageTitle = computed(() => {
  return props.preSelectedCoupon ? 
    `Mass Assign: ${props.preSelectedCoupon.name}` : 
    'Mass Coupon Assignment';
});

const pageSubtitle = computed(() => {
  return props.preSelectedCoupon ? 
    `Assign "${props.preSelectedCoupon.code}" to multiple users at once` : 
    'Assign coupons to multiple users at once';
});

const canProceedToStep2 = computed(() => {
  return selectedUsers.value.length > 0;
});

const canProceedToStep3 = computed(() => {
  return selectedCoupon.value !== null;
});

const canSubmitAssignment = computed(() => {
  return assignmentForm.coupon_id && 
         assignmentForm.user_ids.length > 0 && 
         assignmentForm.quantity > 0;
});

const totalCouponsToAssign = computed(() => {
  return selectedUsers.value.length * assignmentForm.quantity;
});

// Methods
const debouncedSearch = debounce(async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }

  isSearching.value = true;
  
  try {
    const response = await fetch(route('admin.coupon-assignment.search-users'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        search: searchQuery.value,
        organizer_id: organizerFilter.value || null,
        limit: 50,
      }),
    });

    const data = await response.json();
    searchResults.value = data.users || [];
  } catch (error) {
    console.error('Search failed:', error);
    searchResults.value = [];
  } finally {
    isSearching.value = false;
  }
}, 300);

const selectUser = (user: User) => {
  if (!selectedUsers.value.find(u => u.id === user.id)) {
    selectedUsers.value.push(user);
    updateUserStats();
  }
};

const removeUser = (userId: number) => {
  selectedUsers.value = selectedUsers.value.filter(u => u.id !== userId);
  updateUserStats();
};

const selectAllSearchResults = () => {
  searchResults.value.forEach(user => selectUser(user));
};

const clearSelectedUsers = () => {
  selectedUsers.value = [];
  userStats.value = null;
};

const updateUserStats = async () => {
  if (selectedUsers.value.length === 0) {
    userStats.value = null;
    return;
  }

  isLoadingStats.value = true;
  
  try {
    const response = await fetch(route('admin.coupon-assignment.user-stats'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        user_ids: selectedUserIds.value,
      }),
    });

    const data = await response.json();
    userStats.value = data;
  } catch (error) {
    console.error('Failed to load user stats:', error);
  } finally {
    isLoadingStats.value = false;
  }
};

const selectCoupon = (coupon: Coupon) => {
  selectedCoupon.value = coupon;
  assignmentForm.coupon_id = coupon.id;
};

const goToStep = (step: number) => {
  if (step === 2 && !canProceedToStep2.value) return;
  if (step === 3 && !canProceedToStep3.value) return;
  
  currentStep.value = step;
  
  if (step === 3) {
    // Prepare final assignment form
    assignmentForm.user_ids = selectedUserIds.value;
  }
};

const submitAssignment = async () => {
  if (!canSubmitAssignment.value) return;

  assignmentForm.processing = true;

  try {
    const response = await axios.post(route('admin.coupon-assignment.assign'), {
      coupon_id: assignmentForm.coupon_id,
      user_ids: assignmentForm.user_ids,
      quantity: assignmentForm.quantity,
      expires_at: assignmentForm.expires_at,
      notes: assignmentForm.notes,
    }, {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    if (response.data.success) {
      // Reset form and go back to step 1
      currentStep.value = 1;
      selectedUsers.value = [];
      selectedCoupon.value = props.preSelectedCoupon || null;
      assignmentForm.reset();
      assignmentForm.coupon_id = props.preSelectedCouponId || null;
      assignmentForm.errors = {};
      userStats.value = null;
      
      // Show success message
      alert(`Success: ${response.data.message}`);
      
      // Log assignment details
      console.log('Assignment successful:', response.data);
    } else {
      alert(`Error: ${response.data.message}`);
    }
  } catch (error) {
    console.error('Assignment failed:', error);
    
    if (error.response?.data?.errors) {
      // Handle validation errors
      Object.keys(error.response.data.errors).forEach(key => {
        assignmentForm.errors[key] = error.response.data.errors[key][0];
      });
    } else if (error.response?.data?.message) {
      alert(`Error: ${error.response.data.message}`);
    } else {
      alert('An unexpected error occurred during assignment.');
    }
  } finally {
    assignmentForm.processing = false;
  }
};

const loadHistory = async () => {
  try {
    const response = await fetch(route('admin.coupon-assignment.history'), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    const data = await response.json();
    assignmentHistory.value = data.assignments || [];
    showHistory.value = true;
  } catch (error) {
    console.error('Failed to load history:', error);
  }
};

const formatDiscount = (value: number, type: string): string => {
  if (type === 'percentage') {
    return `${value}%`;
  } else {
    return `$${(value / 100).toFixed(2)}`;
  }
};

// Watchers
watch(searchQuery, debouncedSearch);
watch(organizerFilter, debouncedSearch);
</script>

<template>
  <div>
    <Head title="Mass Coupon Assignment" />
    
    <AppLayout>
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6 lg:p-8 bg-white dark:bg-gray-800">
              
              <!-- Page Header -->
              <PageHeader :title="pageTitle" :subtitle="pageSubtitle">
                <template #actions>
                  <button
                    @click="loadHistory"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 disabled:opacity-25 transition mr-3"
                  >
                    View History
                  </button>
                  <Link 
                    :href="route('admin.coupons.index')" 
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition"
                  >
                    Back to Coupons
                  </Link>
                </template>
              </PageHeader>

              <!-- Progress Steps -->
              <div class="mb-8">
                <div class="flex items-center justify-center">
                  <div class="flex items-center space-x-4">
                    <!-- Step 1 -->
                    <div class="flex items-center">
                      <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                        currentStep >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-300 text-gray-700'
                      ]">
                        1
                      </div>
                      <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">Select Users</span>
                    </div>
                    
                    <div class="w-12 h-1 bg-gray-300 rounded"></div>
                    
                    <!-- Step 2 -->
                    <div class="flex items-center">
                      <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                        currentStep >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-300 text-gray-700'
                      ]">
                        2
                      </div>
                      <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">Choose Coupon</span>
                    </div>
                    
                    <div class="w-12 h-1 bg-gray-300 rounded"></div>
                    
                    <!-- Step 3 -->
                    <div class="flex items-center">
                      <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                        currentStep >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-300 text-gray-700'
                      ]">
                        3
                      </div>
                      <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">Confirm & Assign</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 1: Select Users -->
              <div v-if="currentStep === 1" class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Step 1: Select Users</h3>
                
                <!-- Search Controls -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search Users</label>
                    <input
                      id="search"
                      v-model="searchQuery"
                      type="text"
                      placeholder="Search by name or email..."
                      class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    >
                  </div>
                  <div>
                    <label for="organizer" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter by Organizer</label>
                    <select
                      id="organizer"
                      v-model="organizerFilter"
                      class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    >
                      <option value="">All Organizers</option>
                      <option v-for="organizer in organizers" :key="organizer.id" :value="organizer.id">
                        {{ organizer.name }}
                      </option>
                    </select>
                  </div>
                </div>

                <!-- Search Results -->
                <div v-if="searchQuery.length >= 2" class="space-y-4">
                  <div class="flex justify-between items-center">
                    <h4 class="font-medium text-gray-900 dark:text-white">Search Results</h4>
                    <button
                      v-if="searchResults.length > 0"
                      @click="selectAllSearchResults"
                      class="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                      Select All ({{ searchResults.length }})
                    </button>
                  </div>
                  
                  <div v-if="isSearching" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                    <span class="ml-2 text-sm text-gray-600">Searching...</span>
                  </div>
                  
                  <div v-else-if="searchResults.length === 0 && searchQuery.length >= 2" class="text-center py-4 text-gray-500">
                    No users found matching your search.
                  </div>
                  
                  <div v-else class="max-h-60 overflow-y-auto border border-gray-200 rounded-md">
                    <div
                      v-for="user in searchResults"
                      :key="user.id"
                      class="p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer flex justify-between items-center"
                      @click="selectUser(user)"
                    >
                      <div>
                        <div class="font-medium text-gray-900">{{ user.name }}</div>
                        <div class="text-sm text-gray-600">{{ user.email }}</div>
                        <div class="text-xs text-gray-500">Member since {{ user.member_since }}</div>
                      </div>
                      <div v-if="selectedUsers.find(u => u.id === user.id)" class="text-green-600">
                        ✓ Selected
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Selected Users -->
                <div v-if="selectedUsers.length > 0" class="space-y-4">
                  <div class="flex justify-between items-center">
                    <h4 class="font-medium text-gray-900 dark:text-white">Selected Users ({{ selectedUsers.length }})</h4>
                    <button
                      @click="clearSelectedUsers"
                      class="text-sm text-red-600 hover:text-red-800"
                    >
                      Clear All
                    </button>
                  </div>
                  
                  <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-md">
                    <div
                      v-for="user in selectedUsers"
                      :key="user.id"
                      class="p-2 border-b border-gray-200 flex justify-between items-center"
                    >
                      <div>
                        <span class="font-medium text-gray-900">{{ user.name }}</span>
                        <span class="text-sm text-gray-600 ml-2">{{ user.email }}</span>
                      </div>
                      <button
                        @click="removeUser(user.id)"
                        class="text-red-600 hover:text-red-800 text-sm"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                  
                  <!-- User Statistics -->
                  <div v-if="userStats" class="bg-gray-50 p-4 rounded-md">
                    <h5 class="font-medium text-gray-900 mb-2">Selected User Statistics</h5>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                      <div>
                        <span class="text-gray-600">Total Users:</span>
                        <span class="font-medium ml-1">{{ userStats.total_users }}</span>
                      </div>
                      <div>
                        <span class="text-gray-600">Active Users:</span>
                        <span class="font-medium ml-1">{{ userStats.active_users }}</span>
                      </div>
                      <div>
                        <span class="text-gray-600">With Coupons:</span>
                        <span class="font-medium ml-1">{{ userStats.users_with_coupons }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Next Button -->
                <div class="flex justify-end">
                  <button
                    @click="goToStep(2)"
                    :disabled="!canProceedToStep2"
                    :class="[
                      'px-4 py-2 rounded-md font-semibold text-sm',
                      canProceedToStep2 
                        ? 'bg-indigo-600 text-white hover:bg-indigo-700' 
                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    ]"
                  >
                    Next: Choose Coupon
                  </button>
                </div>
              </div>

              <!-- Step 2: Choose Coupon -->
              <div v-if="currentStep === 2" class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Step 2: Choose Coupon</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div
                    v-for="coupon in coupons"
                    :key="coupon.id"
                    :class="[
                      'border-2 rounded-lg p-4 cursor-pointer transition-colors',
                      selectedCoupon?.id === coupon.id
                        ? 'border-indigo-600 bg-indigo-50'
                        : 'border-gray-200 hover:border-gray-300'
                    ]"
                    @click="selectCoupon(coupon)"
                  >
                    <div class="space-y-2">
                      <div class="flex justify-between items-start">
                        <h4 class="font-medium text-gray-900">{{ coupon.name }}</h4>
                        <div v-if="selectedCoupon?.id === coupon.id" class="text-indigo-600">
                          ✓
                        </div>
                      </div>
                      <div class="text-sm text-gray-600">
                        Code: <span class="font-mono">{{ coupon.code }}</span>
                      </div>
                      <div class="text-sm text-gray-600">
                        Type: <span class="capitalize">{{ coupon.type.replace('_', ' ') }}</span>
                      </div>
                      <div class="text-sm text-gray-600">
                        Discount: {{ formatDiscount(coupon.discount_value, coupon.discount_type) }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between">
                  <button
                    @click="goToStep(1)"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                  >
                    Back: Select Users
                  </button>
                  <button
                    @click="goToStep(3)"
                    :disabled="!canProceedToStep3"
                    :class="[
                      'px-4 py-2 rounded-md font-semibold text-sm',
                      canProceedToStep3 
                        ? 'bg-indigo-600 text-white hover:bg-indigo-700' 
                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    ]"
                  >
                    Next: Confirm Assignment
                  </button>
                </div>
              </div>

              <!-- Step 3: Confirm & Assign -->
              <div v-if="currentStep === 3" class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Step 3: Confirm & Assign</h3>
                
                <!-- Assignment Summary -->
                <div class="bg-gray-50 p-6 rounded-lg space-y-4">
                  <h4 class="font-medium text-gray-900">Assignment Summary</h4>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <h5 class="text-sm font-medium text-gray-700 mb-2">Selected Coupon</h5>
                      <div v-if="selectedCoupon" class="space-y-1">
                        <div class="font-medium">{{ selectedCoupon.name }}</div>
                        <div class="text-sm text-gray-600">Code: {{ selectedCoupon.code }}</div>
                        <div class="text-sm text-gray-600">
                          Discount: {{ formatDiscount(selectedCoupon.discount_value, selectedCoupon.discount_type) }}
                        </div>
                      </div>
                    </div>
                    
                    <div>
                      <h5 class="text-sm font-medium text-gray-700 mb-2">Recipients</h5>
                      <div class="text-sm text-gray-600">
                        <div>{{ selectedUsers.length }} users selected</div>
                        <div>{{ assignmentForm.quantity }} coupon(s) per user</div>
                        <div class="font-medium">Total: {{ totalCouponsToAssign }} coupons</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Assignment Options -->
                <div class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity per User</label>
                      <input
                        id="quantity"
                        v-model.number="assignmentForm.quantity"
                        type="number"
                        min="1"
                        max="10"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                      >
                      <div v-if="assignmentForm.errors.quantity" class="text-red-600 text-sm mt-1">
                        {{ assignmentForm.errors.quantity }}
                      </div>
                    </div>
                    
                    <div>
                      <label for="expires_at" class="block text-sm font-medium text-gray-700">Custom Expiry (Optional)</label>
                      <input
                        id="expires_at"
                        v-model="assignmentForm.expires_at"
                        type="datetime-local"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                      >
                    </div>
                  </div>
                  
                  <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                    <textarea
                      id="notes"
                      v-model="assignmentForm.notes"
                      rows="3"
                      placeholder="Add any notes about this assignment..."
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    ></textarea>
                  </div>
                </div>

                <!-- Error Display -->
                <div v-if="Object.keys(assignmentForm.errors).length > 0" class="bg-red-50 border border-red-200 rounded-md p-4">
                  <h4 class="text-red-800 font-medium mb-2">Please fix the following errors:</h4>
                  <ul class="list-disc list-inside text-red-700 text-sm space-y-1">
                    <li v-for="(error, key) in assignmentForm.errors" :key="key">{{ error }}</li>
                  </ul>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between">
                  <button
                    @click="goToStep(2)"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                  >
                    Back: Choose Coupon
                  </button>
                  <button
                    @click="submitAssignment"
                    :disabled="!canSubmitAssignment || assignmentForm.processing"
                    :class="[
                      'px-6 py-2 rounded-md font-semibold text-sm',
                      canSubmitAssignment && !assignmentForm.processing
                        ? 'bg-green-600 text-white hover:bg-green-700' 
                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    ]"
                  >
                    <span v-if="assignmentForm.processing">Assigning...</span>
                    <span v-else>Assign {{ totalCouponsToAssign }} Coupons</span>
                  </button>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- History Modal -->
      <div v-if="showHistory" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto">
          <div class="flex justify-between items-start mb-4">
            <h3 class="text-lg font-semibold">Assignment History</h3>
            <button @click="showHistory = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coupon</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="assignment in assignmentHistory" :key="assignment.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ assignment.user_name }}</div>
                    <div class="text-sm text-gray-500">{{ assignment.user_email }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ assignment.coupon_name }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600">{{ assignment.unique_code }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="[
                      'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                      assignment.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    ]">
                      {{ assignment.status }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div>{{ assignment.assigned_at }}</div>
                    <div class="text-xs">by {{ assignment.assigned_by }}</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppLayout>
  </div>
</template>