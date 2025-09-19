<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios'; // Import axios
import { useCurrency } from '@/composables/useCurrency';

import type { PublicTicketType } from '@/types/ticket';

interface EventOccurrence {
  id: string | number;
  name: string;
  full_date_time: string;
  tickets?: PublicTicketType[];
}

const props = defineProps({
  showModal: {
    type: Boolean,
    required: true,
  },
  occurrence: {
    type: Object as () => EventOccurrence | undefined,
    required: false,
  },
});

// Use currency composable
const { formatPrice } = useCurrency();

const emit = defineEmits(['close']);

// Use a local ref for selected quantities to avoid directly mutating prop-like data
// Initialize when the occurrence prop changes and is valid
const selectedTickets = ref<Record<string | number, number>>({});

watch(() => props.occurrence, (newOccurrence) => {
  if (newOccurrence && newOccurrence.tickets) {
    const initialQuantities: Record<string | number, number> = {};
    newOccurrence.tickets.forEach(ticket => {
      initialQuantities[ticket.id] = 0; // Default to 0 quantity
    });
    selectedTickets.value = initialQuantities;
  } else {
    selectedTickets.value = {};
  }
}, { immediate: true });


const incrementQuantity = (ticketId: string | number) => {
  // Ensure props.occurrence and props.occurrence.tickets are available
  if (!props.occurrence || !props.occurrence.tickets) {
    console.error('Occurrence or tickets data is not available.');
    return;
  }

  const ticket = props.occurrence.tickets.find(t => t.id === ticketId);

  if (!ticket) {
    console.error(`Ticket details for ID ${ticketId} not found.`);
    return;
  }

  if (selectedTickets.value[ticketId] === undefined) {
    console.warn(`Ticket ${ticket.name || ticketId} is not in the current selection. Cannot increment.`);
    return;
  }

  const currentQuantity = selectedTickets.value[ticketId];

  // Use a default for quantity_available if it's undefined (e.g., 0 or Infinity depending on desired behavior)
  // Assuming 0 if undefined, meaning if not specified, it's unavailable.
  const availableStock = ticket.quantity_available ?? 0;
  if (currentQuantity >= availableStock) {
    console.warn(`Cannot select more of ticket ${ticket.name || ticketId}. Available stock: ${availableStock}. Currently selected: ${currentQuantity}.`);
    return;
  }

  // max_per_order can be null or undefined if there's no limit.
  // If ticket.max_per_order is null or undefined, the condition (currentQuantity >= ticket.max_per_order) effectively becomes false, allowing increment.
  if (ticket.max_per_order !== null && ticket.max_per_order !== undefined && currentQuantity >= ticket.max_per_order) {
    console.warn(`Cannot select more of ticket ${ticket.name || ticketId}. Max per order: ${ticket.max_per_order}. Currently selected: ${currentQuantity}.`);
    return;
  }

  selectedTickets.value[ticketId]++;
};

const decrementQuantity = (ticketId: string | number) => {
  if (selectedTickets.value[ticketId] !== undefined && selectedTickets.value[ticketId] > 0) {
    selectedTickets.value[ticketId]--;
  }
};

const totalPrice = computed(() => {
  if (!props.occurrence || !props.occurrence.tickets) {
    return 0;
  }
  return props.occurrence.tickets.reduce((total, ticket) => {
    // Use membership price if available, otherwise use regular price
    const ticketWithDiscount = ticket as PublicTicketType & { has_membership_discount?: boolean; membership_price?: number };
    const effectivePrice = ticketWithDiscount.has_membership_discount ? ticketWithDiscount.membership_price! : ticket.price;
    return total + (effectivePrice * (selectedTickets.value[ticket.id] || 0));
  }, 0);
});

const closeModal = () => {
  emit('close');
};

const isLoading = ref(false);

// Type for the items to be sent to the new booking endpoint
interface BookingRequestItem {
  ticket_id: string | number;
  quantity: number;
  price_at_purchase: number; // Good to record the price when booking started
  name: string; // For quick reference, though backend should re-verify
}

const hasSelectedTickets = computed(() => {
  if (!props.occurrence || !props.occurrence.tickets) return false;
  return Object.values(selectedTickets.value).some(quantity => quantity > 0);
});

// Currency formatting functions
const formatTicketPrice = (price: number, currency: string): string => {
  return formatPrice(price * 100, currency); // Convert to cents for the composable
};

const formatTotalPrice = (total: number): string => {
  // Get currency from first available ticket
  const firstTicket = props.occurrence?.tickets?.[0];
  const currency = firstTicket?.currency || 'USD';
  return formatPrice(total * 100, currency); // Convert to cents for the composable
};

const confirmPurchase = async () => {
  isLoading.value = true;

  const bookingItems: BookingRequestItem[] = [];
  if (props.occurrence && props.occurrence.tickets) {
    props.occurrence.tickets.forEach(ticket => {
      const quantity = selectedTickets.value[ticket.id];
      if (quantity > 0) {
        const ticketWithDiscount = ticket as PublicTicketType & { has_membership_discount?: boolean; membership_price?: number };
        bookingItems.push({
          ticket_id: ticket.id,
          quantity: quantity,
          price_at_purchase: ticketWithDiscount.has_membership_discount ? ticketWithDiscount.membership_price! : ticket.price,
          name: ticket.name
        });
      }
    });
  }

  if (bookingItems.length === 0 && totalPrice.value > 0) {
      console.error('No items selected for a paid purchase.');
      alert('Please select tickets before proceeding.');
      isLoading.value = false;
      return;
  }

  // Even if totalPrice is 0, we might still want to record the "booking" of free tickets.
  // The backend will decide if a payment gateway is needed.

  const payload = {
    occurrence_id: props.occurrence?.id,
    items: bookingItems,
    // total_amount: totalPrice.value // Send for backend verification
  };

  try {
    // Use Axios for the POST request
    const response = await axios.post('/bookings/initiate', payload);
    const responseData = response.data; // Axios automatically parses JSON

    // No need to check response.ok, Axios throws an error for non-2xx responses

    if (responseData.requires_payment && responseData.checkout_url) {
      // Paid booking, redirect to Stripe
      window.location.href = responseData.checkout_url;
      // Modal will close upon redirection or page change
    } else if (responseData.booking_confirmed) {
      // Free booking confirmed, or payment not required
      alert(responseData.message || 'Your booking is confirmed!'); // Show success message
      closeModal(); // Close the modal
    } else {
      // Unexpected response
      console.error('Unexpected response from server:', responseData);
      alert('An unexpected error occurred. Please contact support.');
    }

  } catch (error) {
    if (axios.isAxiosError(error) && error.response) {
        // Error response from server (e.g., 4xx, 5xx)
        console.error('Booking/Payment initiation failed:', error.response.data.message || error.response.data.error || error.response.statusText);
        alert(error.response.data.message || error.response.data.error || 'Could not process your request. Please try again.');
    } else {
        // Network error or other issues
        console.error('Error during booking confirmation:', error);
        alert('An unexpected error occurred. Please try again.');
    }
  } finally {
    isLoading.value = false;
    // Don't close modal if redirecting, only if it's a free booking confirmation or error.
  }
};

</script>

<template>
  <Transition name="modal">
    <div
      v-if="showModal && occurrence"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 dark:bg-opacity-75 p-4"
      @click.self="closeModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Choose Tickets</h2>
          <button @click="closeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-2xl">&times;</button>
        </div>

        <div class="p-4 overflow-y-auto">
          <div v-if="occurrence" class="mb-4">
            <h3 class="text-md font-medium text-gray-700 dark:text-gray-300">{{ occurrence.name }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ occurrence.full_date_time }}</p>
          </div>

          <div v-if="occurrence && occurrence.tickets && occurrence.tickets.length > 0" class="space-y-3">
            <div
              v-for="ticket in occurrence.tickets"
              :key="ticket.id"
              class="p-3 border dark:border-gray-700 rounded-md flex justify-between items-center bg-gray-50 dark:bg-gray-700/50"
            >
              <div>
                <h4 class="font-semibold text-gray-700 dark:text-gray-200">{{ ticket.name }}</h4>
                <p v-if="ticket.description" class="text-xs text-gray-500 dark:text-gray-400">{{ ticket.description }}</p>

                <!-- Display pricing with membership discount styling -->
                <div v-if="(ticket as any).has_membership_discount" class="space-y-1">
                  <p class="text-sm text-pink-500 dark:text-pink-400 font-medium">
                    {{ formatTicketPrice((ticket as any).membership_price, ticket.currency) }}
                    <span class="ml-2 text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-0.5 rounded">
                      Save {{ (ticket as any).savings_percentage }}%
                    </span>
                  </p>
                  <p class="text-xs text-gray-400 dark:text-gray-500 line-through">
                    {{ formatTicketPrice(ticket.price, ticket.currency) }}
                  </p>
                </div>

                <!-- Regular pricing when no membership discount -->
                <p v-else class="text-sm text-pink-500 dark:text-pink-400 font-medium">
                  {{ formatTicketPrice(ticket.price, ticket.currency) }}
                </p>
              </div>
              <div class="flex items-center space-x-2">
                <button
                  @click="decrementQuantity(ticket.id)"
                  class="px-2 py-1 border dark:border-gray-600 rounded text-pink-500 dark:text-pink-400 hover:bg-pink-50 dark:hover:bg-pink-700/30 disabled:opacity-50"
                  :disabled="(selectedTickets[ticket.id] || 0) === 0"
                >
                  -
                </button>
                <span class="w-8 text-center text-gray-700 dark:text-gray-300">{{ selectedTickets[ticket.id] || 0 }}</span>
                <button
                  @click="incrementQuantity(ticket.id)"
                  class="px-2 py-1 border dark:border-gray-600 rounded text-pink-500 dark:text-pink-400 hover:bg-pink-50 dark:hover:bg-pink-700/30"
                  :disabled="ticket.quantity_available !== undefined && (selectedTickets[ticket.id] || 0) >= ticket.quantity_available"
                >
                  +
                </button>
              </div>
            </div>
          </div>
          <div v-else class="text-center text-gray-500 dark:text-gray-400 py-4">
            <p>No tickets available for this occurrence.</p>
          </div>
        </div>

        <div class="p-4 border-t dark:border-gray-700 mt-auto">
          <div class="flex justify-between items-center mb-3">
            <span class="text-gray-700 dark:text-gray-300">Total:</span>
            <span class="text-xl font-bold text-pink-500 dark:text-pink-400">{{ formatTotalPrice(totalPrice) }}</span>
          </div>
          <button
            @click="confirmPurchase"
            class="w-full bg-pink-500 text-white py-2.5 rounded-md hover:bg-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700 font-semibold disabled:opacity-70"
            :disabled="isLoading || (totalPrice > 0 && !hasSelectedTickets)"
          >
            <span v-if="isLoading">Processing...</span>
            <span v-else>Purchase</span>
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
  transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.95) translateY(20px);
}
</style>
