<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogClose,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox'; // Assuming path for Checkbox
import { Label } from '@/components/ui/label';

export interface TicketDefinitionOption {
    id: number;
    name: string; // Name (preferably translated for current UI locale)
    price: number; // Price in smallest currency unit (e.g., cents)
    currency_code: string; // e.g., 'USD', 'EUR', etc.
    // Add other fields if needed for display, e.g., status
}

const props = defineProps<{
    show: boolean;
    availableTicketDefinitions: TicketDefinitionOption[];
    initiallySelectedIds?: number[];
}>();

const emit = defineEmits(['close', 'ticketDefinitionsSelected']);

const searchTerm = ref('');
const internalSelectedIds = ref<Set<number>>(new Set());

// Initialize or reset internal selection when the modal is shown or initial IDs change
watch(
    () => [props.show, props.initiallySelectedIds],
    () => {
        if (props.show) {
            internalSelectedIds.value = new Set(props.initiallySelectedIds || []);
        } else {
            // Optionally reset searchTerm when modal is hidden if desired
            // searchTerm.value = '';
        }
    },
    { immediate: true, deep: true } // deep might be overkill if initiallySelectedIds is simple array of numbers
);

const filteredTicketDefinitions = computed(() => {
    if (!searchTerm.value) {
        return props.availableTicketDefinitions;
    }
    return props.availableTicketDefinitions.filter(td =>
        td.name.toLowerCase().includes(searchTerm.value.toLowerCase())
    );
});

const formatPrice = (price: number, currencyCode: string) => {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currencyCode }).format(price / 100);
    } catch (e) {
        // Fallback for invalid currency codes or other issues
        return `${(price / 100).toFixed(2)} ${currencyCode}`;
    }
};

const handleSelectionChange = (ticketDefId: number, isSelected: boolean | 'indeterminate') => {
    // Coerce 'indeterminate' to false for Set logic, or handle as needed
    const checked = typeof isSelected === 'boolean' ? isSelected : false;
    if (checked) {
        internalSelectedIds.value.add(ticketDefId);
    } else {
        internalSelectedIds.value.delete(ticketDefId);
    }
};

const confirmSelection = () => {
    emit('ticketDefinitionsSelected', Array.from(internalSelectedIds.value));
    closeModal();
};

const closeModal = () => {
    emit('close');
};

</script>

<template>
    <Dialog :open="show" @update:open="show ? closeModal() : null">
        <DialogContent class="sm:max-w-lg" @escape-key-down="closeModal" @pointer-down-outside="closeModal">
            <DialogHeader>
                <DialogTitle>Select Ticket Definitions</DialogTitle>
                <DialogDescription>
                    Choose from existing ticket definitions to associate.
                </DialogDescription>
            </DialogHeader>

            <div class="mt-4 mb-6">
                <Input
                    type="text"
                    placeholder="Search tickets by name..."
                    v-model="searchTerm"
                    class="w-full"
                />
            </div>

            <div class="max-h-80 overflow-y-auto border rounded-md p-1 space-y-1 bg-background">
                <template v-if="filteredTicketDefinitions.length > 0">
                    <div
                        v-for="ticketDef in filteredTicketDefinitions"
                        :key="ticketDef.id"
                        class="flex items-center justify-between p-3 rounded-md hover:bg-muted/50 transition-colors"
                    >
                        <div class="flex items-center space-x-3">
                             <Checkbox
                                :id="`td-select-${ticketDef.id}`"
                                :checked="internalSelectedIds.has(ticketDef.id)"
                                @update:checked="(isChecked) => handleSelectionChange(ticketDef.id, isChecked)"
                            />
                            <Label :for="`td-select-${ticketDef.id}`" class="cursor-pointer">
                                <span class="font-medium">{{ ticketDef.name }}</span>
                                <div class="text-xs text-muted-foreground">
                                    {{ formatPrice(ticketDef.price, ticketDef.currency_code) }}
                                </div>
                            </Label>
                        </div>
                    </div>
                </template>
                <div v-else class="text-center text-muted-foreground py-10">
                    <p v-if="searchTerm">No ticket definitions match your search.</p>
                    <p v-else>No ticket definitions available to select.</p>
                </div>
            </div>

            <DialogFooter class="mt-6">
                <DialogClose as-child>
                    <Button type="button" variant="outline" @click="closeModal">Cancel</Button>
                </DialogClose>
                <Button type="button" @click="confirmSelection" :disabled="internalSelectedIds.size === 0 && (props.initiallySelectedIds || []).length === 0">
                    Confirm Selection
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
