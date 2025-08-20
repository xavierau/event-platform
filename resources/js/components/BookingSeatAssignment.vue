<template>
  <div class="space-y-4">
    <!-- Display current seat -->
    <div v-if="booking.seat_number" class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
          <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>
        <div>
          <p class="font-medium text-green-900">Seat Assigned</p>
          <p class="text-sm text-green-700">{{ booking.seat_number }}</p>
        </div>
      </div>
      <button
        v-if="canManage"
        @click="removeSeat"
        :disabled="isLoading"
        class="px-3 py-1 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded"
      >
        Remove
      </button>
    </div>

    <!-- Assign seat form -->
    <div v-else-if="canManage" class="space-y-3">
      <div>
        <label for="seat_number" class="block text-sm font-medium text-gray-700 mb-1">
          Assign Seat Number
        </label>
        <div class="flex gap-2">
          <input
            id="seat_number"
            v-model="seatNumber"
            type="text"
            placeholder="e.g., A12, B5, VIP-1"
            maxlength="20"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :disabled="isLoading"
          />
          <button
            @click="assignSeat"
            :disabled="!seatNumber.trim() || isLoading"
            class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
          >
            {{ isLoading ? 'Assigning...' : 'Assign' }}
          </button>
        </div>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
      </div>
    </div>

    <!-- No seat assigned (read-only) -->
    <div v-else class="p-3 bg-gray-50 border border-gray-200 rounded-md">
      <p class="text-sm text-gray-600">No seat assigned</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

interface Booking {
  id: number
  seat_number?: string
  metadata?: {
    seat_number?: string
    seat_assigned_by?: number
    seat_assigned_at?: string
  }
}

interface Props {
  booking: Booking
  canManage?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canManage: false
})

const emit = defineEmits<{
  updated: [booking: Booking]
}>()

const seatNumber = ref('')
const isLoading = ref(false)
const error = ref('')

const assignSeat = async () => {
  if (!seatNumber.value.trim()) return

  isLoading.value = true
  error.value = ''

  try {
    const response = await fetch(`/api/bookings/${props.booking.id}/seat`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        seat_number: seatNumber.value.trim()
      })
    })

    const data = await response.json()

    if (response.ok) {
      // Update the booking object
      const updatedBooking = {
        ...props.booking,
        seat_number: data.seat_number,
        metadata: {
          ...props.booking.metadata,
          seat_number: data.seat_number,
          seat_assigned_by: data.booking.metadata?.seat_assigned_by,
          seat_assigned_at: data.booking.metadata?.seat_assigned_at
        }
      }
      
      emit('updated', updatedBooking)
      seatNumber.value = ''
    } else {
      error.value = data.message || 'Failed to assign seat'
    }
  } catch (err) {
    error.value = 'Network error. Please try again.'
  } finally {
    isLoading.value = false
  }
}

const removeSeat = async () => {
  if (!confirm('Are you sure you want to remove the seat assignment?')) return

  isLoading.value = true
  error.value = ''

  try {
    const response = await fetch(`/api/bookings/${props.booking.id}/seat`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })

    const data = await response.json()

    if (response.ok) {
      // Update the booking object
      const updatedBooking = {
        ...props.booking,
        seat_number: undefined,
        metadata: {
          ...props.booking.metadata
        }
      }
      
      // Remove seat-related metadata
      if (updatedBooking.metadata) {
        delete updatedBooking.metadata.seat_number
        delete updatedBooking.metadata.seat_assigned_by
        delete updatedBooking.metadata.seat_assigned_at
      }
      
      emit('updated', updatedBooking)
    } else {
      error.value = data.message || 'Failed to remove seat assignment'
    }
  } catch (err) {
    error.value = 'Network error. Please try again.'
  } finally {
    isLoading.value = false
  }
}
</script>