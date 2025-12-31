<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios'; // Import axios
import { Link, usePage } from '@inertiajs/vue3';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/composables/useCurrency';
import { ChevronDown, ChevronUp, Crown, Star } from 'lucide-vue-next';

import type { PublicTicketType, MembershipPriceInfo } from '@/types/ticket';

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
  isAuthenticated: {
    type: Boolean,
    default: false,
  },
  userMembershipLevelId: {
    type: Number,
    required: false,
  },
});

// i18n and page setup
const { t } = useI18n();
const page = usePage();

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

// Track which ticket's membership tiers are expanded
const expandedMembershipTiers = ref<Record<string | number, boolean>>({});

const toggleMembershipTiers = (ticketId: string | number) => {
  expandedMembershipTiers.value[ticketId] = !expandedMembershipTiers.value[ticketId];
};

// Check if a ticket has membership prices available
const hasMembershipPrices = (ticket: PublicTicketType): boolean => {
  return Boolean(ticket.all_membership_prices && ticket.all_membership_prices.length > 0);
};

// Get membership level name (handles translatable names)
const getMembershipLevelName = (membershipPrice: MembershipPriceInfo): string => {
  if (typeof membershipPrice.membership_level_name === 'string') {
    return membershipPrice.membership_level_name;
  }
  // Handle translatable names - get current locale or fallback to 'en'
  const currentLocale = (page.props as any).locale || 'en';
  return membershipPrice.membership_level_name[currentLocale]
    || membershipPrice.membership_level_name['en']
    || Object.values(membershipPrice.membership_level_name)[0]
    || '';
};

// Compute highest savings percentage across all tickets for non-authenticated users CTA
const highestSavingsPercentage = computed(() => {
  if (!props.occurrence?.tickets) return 0;
  let maxSavings = 0;
  props.occurrence.tickets.forEach(ticket => {
    if (ticket.all_membership_prices) {
      ticket.all_membership_prices.forEach(mp => {
        if (mp.savings_percentage > maxSavings) {
          maxSavings = mp.savings_percentage;
        }
      });
    }
  });
  return maxSavings;
});

// Check if any ticket has membership pricing available
const anyTicketHasMembershipPricing = computed(() => {
  if (!props.occurrence?.tickets) return false;
  return props.occurrence.tickets.some(ticket => hasMembershipPrices(ticket));
});

// Get the user's current savings percentage (if they have a membership)
const userCurrentSavingsPercentage = computed(() => {
  if (!props.userMembershipLevelId || !props.occurrence?.tickets) return 0;
  let maxCurrentSavings = 0;
  props.occurrence.tickets.forEach(ticket => {
    if (ticket.all_membership_prices) {
      const userTier = ticket.all_membership_prices.find(
        mp => mp.membership_level_id === props.userMembershipLevelId
      );
      if (userTier && userTier.savings_percentage > maxCurrentSavings) {
        maxCurrentSavings = userTier.savings_percentage;
      }
    }
  });
  return maxCurrentSavings;
});

// Check if there's a better tier available (higher savings than user's current tier)
const hasUpgradeAvailable = computed(() => {
  if (!props.userMembershipLevelId) return false;
  return highestSavingsPercentage.value > userCurrentSavingsPercentage.value;
});

// Calculate additional savings percentage if user upgrades
const additionalSavingsWithUpgrade = computed(() => {
  return highestSavingsPercentage.value - userCurrentSavingsPercentage.value;
});

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
              class="p-3 border dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-700/50"
            >
              <!-- Main ticket row -->
              <div class="flex justify-between items-center">
                <div>
                  <h4 class="font-semibold text-gray-700 dark:text-gray-200">{{ ticket.name }}</h4>
                  <p v-if="ticket.description" class="text-xs text-gray-500 dark:text-gray-400">{{ ticket.description }}</p>

                  <!-- Display pricing with membership discount styling -->
                  <div v-if="(ticket as any).has_membership_discount" class="space-y-1">
                    <p class="text-sm text-pink-500 dark:text-pink-400 font-medium">
                      {{ formatTicketPrice((ticket as any).membership_price, ticket.currency) }}
                      <span class="ml-2 text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-0.5 rounded">
                        {{ t('tickets.membership_pricing.save_percent', { percent: (ticket as any).savings_percentage }) }}
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

              <!-- Membership Pricing Collapsible Section -->
              <div v-if="hasMembershipPrices(ticket)" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                <button
                  @click="toggleMembershipTiers(ticket.id)"
                  class="flex items-center justify-between w-full text-left text-sm text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300"
                >
                  <span class="flex items-center gap-1.5">
                    <Crown class="w-4 h-4" />
                    {{ t('tickets.membership_pricing.title') }}
                  </span>
                  <component
                    :is="expandedMembershipTiers[ticket.id] ? ChevronUp : ChevronDown"
                    class="w-4 h-4"
                  />
                </button>

                <!-- Expanded membership tiers list -->
                <Transition name="slide">
                  <div v-if="expandedMembershipTiers[ticket.id]" class="mt-2 space-y-2">
                    <div
                      v-for="membershipPrice in ticket.all_membership_prices"
                      :key="membershipPrice.membership_level_id"
                      class="flex items-center justify-between p-2 rounded-md text-sm"
                      :class="[
                        userMembershipLevelId === membershipPrice.membership_level_id
                          ? 'bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-600'
                          : 'bg-gray-100 dark:bg-gray-600/50'
                      ]"
                    >
                      <div class="flex items-center gap-2">
                        <Star
                          v-if="userMembershipLevelId === membershipPrice.membership_level_id"
                          class="w-4 h-4 text-amber-500"
                        />
                        <span class="font-medium text-gray-700 dark:text-gray-200">
                          {{ getMembershipLevelName(membershipPrice) }}
                        </span>
                        <span
                          v-if="userMembershipLevelId === membershipPrice.membership_level_id"
                          class="text-xs text-amber-600 dark:text-amber-400"
                        >
                          ({{ t('tickets.membership_pricing.your_tier') }})
                        </span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="text-pink-500 dark:text-pink-400 font-medium">
                          {{ formatTicketPrice(membershipPrice.discounted_price, ticket.currency) }}
                        </span>
                        <span class="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-1.5 py-0.5 rounded">
                          {{ t('tickets.membership_pricing.save_percent', { percent: membershipPrice.savings_percentage }) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </Transition>
              </div>
            </div>
          </div>
          <div v-else class="text-center text-gray-500 dark:text-gray-400 py-4">
            <p>No tickets available for this occurrence.</p>
          </div>
        </div>

        <!-- Membership CTA for non-members -->
        <div
          v-if="(!isAuthenticated || !userMembershipLevelId) && anyTicketHasMembershipPricing && highestSavingsPercentage > 0"
          class="mx-4 mb-2 p-3 bg-gradient-to-r from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/30 border border-amber-200 dark:border-amber-700 rounded-lg"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <Crown class="w-5 h-5 text-amber-500 flex-shrink-0" />
              <span class="text-sm font-medium text-amber-800 dark:text-amber-200">
                {{ t('tickets.membership_pricing.save_up_to', { percent: highestSavingsPercentage }) }}
              </span>
            </div>
            <Link
              href="/membership"
              class="px-3 py-1.5 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-700 rounded-md transition-colors whitespace-nowrap"
            >
              {{ t('tickets.membership_pricing.become_member') }}
            </Link>
          </div>
        </div>

        <!-- Membership Upgrade CTA for existing members with upgrade available -->
        <div
          v-else-if="isAuthenticated && userMembershipLevelId && hasUpgradeAvailable && additionalSavingsWithUpgrade > 0"
          class="mx-4 mb-2 p-3 bg-gradient-to-r from-purple-50 to-indigo-100 dark:from-purple-900/30 dark:to-indigo-800/30 border border-purple-200 dark:border-purple-700 rounded-lg"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <Crown class="w-5 h-5 text-purple-500 flex-shrink-0" />
              <span class="text-sm font-medium text-purple-800 dark:text-purple-200">
                {{ t('tickets.membership_pricing.upgrade_save_more', { percent: additionalSavingsWithUpgrade }) }}
              </span>
            </div>
            <Link
              href="/membership"
              class="px-3 py-1.5 text-sm font-medium text-white bg-purple-500 hover:bg-purple-600 dark:bg-purple-600 dark:hover:bg-purple-700 rounded-md transition-colors whitespace-nowrap"
            >
              {{ t('tickets.membership_pricing.upgrade_membership') }}
            </Link>
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

/* Slide transition for membership tiers */
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease-out;
  overflow: hidden;
}

.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-10px);
}

.slide-enter-to,
.slide-leave-from {
  opacity: 1;
  max-height: 500px;
  transform: translateY(0);
}
</style>
