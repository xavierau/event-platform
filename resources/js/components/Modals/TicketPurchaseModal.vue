<script setup lang="ts">
import { ref, computed, watch } from 'vue';

interface TicketType {
  id: string | number;
  name: string;
  description?: string;
  price: number;
  quantity_available?: number;
}

interface EventOccurrence {
  id: string | number;
  name: string;
  full_date_time: string;
  tickets?: TicketType[];
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
  if (selectedTickets.value[ticketId] !== undefined) {
    // TODO: Add check against quantity_available if needed
    selectedTickets.value[ticketId]++;
  }
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
    return total + (ticket.price * (selectedTickets.value[ticket.id] || 0));
  }, 0);
});

const closeModal = () => {
  emit('close');
};

const confirmPurchase = () => {
  // Placeholder for purchase logic
  console.log('Purchase confirmed:', selectedTickets.value, 'Total:', totalPrice.value);
  // Potentially emit an event with ticket details
  closeModal();
};

</script>

<template>
  <Transition name="modal">
    <div
      v-if="showModal && occurrence"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 p-4"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
          <h2 class="text-lg font-semibold text-gray-800">选择票档</h2>
          <button @click="closeModal" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>

        <div class="p-4 overflow-y-auto">
          <div v-if="occurrence" class="mb-4">
            <h3 class="text-md font-medium text-gray-700">{{ occurrence.name }}</h3>
            <p class="text-sm text-gray-500">{{ occurrence.full_date_time }}</p>
          </div>

          <div v-if="occurrence && occurrence.tickets && occurrence.tickets.length > 0" class="space-y-3">
            <div
              v-for="ticket in occurrence.tickets"
              :key="ticket.id"
              class="p-3 border rounded-md flex justify-between items-center"
            >
              <div>
                <h4 class="font-semibold text-gray-700">{{ ticket.name }}</h4>
                <p v-if="ticket.description" class="text-xs text-gray-500">{{ ticket.description }}</p>
                <p class="text-sm text-pink-500 font-medium">¥{{ ticket.price.toFixed(2) }}</p>
              </div>
              <div class="flex items-center space-x-2">
                <button
                  @click="decrementQuantity(ticket.id)"
                  class="px-2 py-1 border rounded text-pink-500 hover:bg-pink-50 disabled:opacity-50"
                  :disabled="(selectedTickets[ticket.id] || 0) === 0"
                >
                  -
                </button>
                <span class="w-8 text-center">{{ selectedTickets[ticket.id] || 0 }}</span>
                <button
                  @click="incrementQuantity(ticket.id)"
                  class="px-2 py-1 border rounded text-pink-500 hover:bg-pink-50"
                  :disabled="ticket.quantity_available !== undefined && (selectedTickets[ticket.id] || 0) >= ticket.quantity_available"
                >
                  +
                </button>
              </div>
            </div>
          </div>
          <div v-else class="text-center text-gray-500 py-4">
            <p>当前场次暂无可售票品。</p>
          </div>
        </div>

        <div class="p-4 border-t mt-auto">
          <div class="flex justify-between items-center mb-3">
            <span class="text-gray-700">总计:</span>
            <span class="text-xl font-bold text-pink-500">¥{{ totalPrice.toFixed(2) }}</span>
          </div>
          <button
            @click="confirmPurchase"
            class="w-full bg-pink-500 text-white py-2.5 rounded-md hover:bg-pink-600 font-semibold disabled:opacity-70"
            :disabled="totalPrice === 0"
          >
            确认购买
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
