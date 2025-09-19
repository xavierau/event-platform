<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { getTranslation, currentLocale } from '@/Utils/i18n';

interface MembershipLevel {
    id: number;
    name: Record<string, string> | string;
    slug: string;
    is_active: boolean;
}

interface MembershipDiscount {
    membership_level_id: number;
    discount_type: 'percentage' | 'fixed';
    discount_value: number;
}

interface Props {
    membershipLevels: MembershipLevel[];
    ticketPrice: number | null | undefined;
    currency: string;
    modelValue: MembershipDiscount[];
    errors?: Record<string, string>;
}

interface Emits {
    (e: 'update:modelValue', value: MembershipDiscount[]): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const isExpanded = ref(false);
const currentLocaleValue = ref(currentLocale.value || 'en');

// Convert modelValue to internal state for easier manipulation
const discountConfigs = ref<Record<number, MembershipDiscount>>({});

// Initialize discountConfigs from modelValue
const initializeDiscounts = () => {
    const configs: Record<number, MembershipDiscount> = {};
    if (props.modelValue && Array.isArray(props.modelValue)) {
        props.modelValue.forEach(discount => {
            configs[discount.membership_level_id] = { ...discount };
        });
    }
    discountConfigs.value = configs;
};

// Initialize on mount and when modelValue changes
watch(() => props.modelValue, initializeDiscounts, { immediate: true, deep: true });

const activeDiscountsCount = computed(() => {
    return Object.keys(discountConfigs.value).length;
});

const summaryText = computed(() => {
    if (activeDiscountsCount.value === 0) {
        return 'No membership discounts configured';
    }
    return `${activeDiscountsCount.value} membership level${activeDiscountsCount.value !== 1 ? 's' : ''} configured`;
});

const getMembershipName = (membership: MembershipLevel): string => {
    if (typeof membership.name === 'string') {
        return membership.name;
    }
    return getTranslation(membership.name, currentLocaleValue.value) || membership.slug;
};

const hasDiscount = (membershipLevelId: number): boolean => {
    return membershipLevelId in discountConfigs.value;
};

const getDiscountConfig = (membershipLevelId: number): MembershipDiscount => {
    return discountConfigs.value[membershipLevelId] || {
        membership_level_id: membershipLevelId,
        discount_type: 'percentage',
        discount_value: 0
    };
};

const toggleDiscount = (membershipLevelId: number) => {
    if (hasDiscount(membershipLevelId)) {
        delete discountConfigs.value[membershipLevelId];
    } else {
        discountConfigs.value[membershipLevelId] = {
            membership_level_id: membershipLevelId,
            discount_type: 'percentage',
            discount_value: 10 // Default 10% discount
        };
    }
    emitUpdate();
};

const updateDiscountType = (membershipLevelId: number, type: 'percentage' | 'fixed') => {
    if (hasDiscount(membershipLevelId)) {
        discountConfigs.value[membershipLevelId].discount_type = type;
        // Reset value to sensible default when switching types
        if (type === 'percentage') {
            discountConfigs.value[membershipLevelId].discount_value = Math.min(50, discountConfigs.value[membershipLevelId].discount_value);
        } else {
            // For fixed discounts, default to 10% of ticket price or minimum $5
            const defaultFixedDiscount = props.ticketPrice ? Math.max(500, Math.round(props.ticketPrice * 0.1)) : 500;
            discountConfigs.value[membershipLevelId].discount_value = Math.min(defaultFixedDiscount, discountConfigs.value[membershipLevelId].discount_value);
        }
        emitUpdate();
    }
};


const emitUpdate = () => {
    const discounts = Object.values(discountConfigs.value);
    emit('update:modelValue', discounts);
};

const calculateDiscountedPrice = (membershipLevelId: number): number | null => {
    if (!props.ticketPrice || !hasDiscount(membershipLevelId)) {
        return null;
    }

    const config = getDiscountConfig(membershipLevelId);
    if (config.discount_type === 'percentage') {
        return Math.max(0, props.ticketPrice - (props.ticketPrice * config.discount_value / 100));
    } else {
        return Math.max(0, props.ticketPrice - config.discount_value);
    }
};

const formatPrice = (price: number): string => {
    return (price / 100).toFixed(2);
};

const getValidationError = (membershipLevelId: number, field: string): string | undefined => {
    return props.errors?.[`membership_discounts.${membershipLevelId}.${field}`];
};

const isDiscountValueValid = (membershipLevelId: number): boolean => {
    if (!hasDiscount(membershipLevelId)) return true;

    const config = getDiscountConfig(membershipLevelId);

    if (config.discount_type === 'percentage') {
        return config.discount_value >= 0 && config.discount_value <= 100;
    } else {
        return config.discount_value >= 0 && (!props.ticketPrice || config.discount_value <= props.ticketPrice);
    }
};
</script>

<template>
    <Card class="mb-6">
        <CardHeader class="cursor-pointer" @click="isExpanded = !isExpanded">
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <component
                        :is="isExpanded ? ChevronDownIcon : ChevronRightIcon"
                        class="h-5 w-5 text-gray-400"
                    />
                    Membership Discounts
                </CardTitle>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ summaryText }}
                </div>
            </div>
        </CardHeader>

        <CardContent v-if="isExpanded" class="space-y-6">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Configure discounts for different membership levels. Members will see reduced prices when viewing tickets.
            </div>

            <div v-if="membershipLevels.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                No active membership levels found. Please create membership levels first.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="membership in membershipLevels.filter(m => m.is_active)"
                    :key="membership.id"
                    class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                :id="`discount-${membership.id}`"
                                :checked="hasDiscount(membership.id)"
                                @change="toggleDiscount(membership.id)"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <Label :for="`discount-${membership.id}`" class="font-medium">
                                {{ getMembershipName(membership) }}
                            </Label>
                        </div>

                        <div v-if="hasDiscount(membership.id) && props.ticketPrice" class="text-sm text-green-600 dark:text-green-400">
                            {{ formatPrice(calculateDiscountedPrice(membership.id)!) }} {{ currency }}
                            <span class="text-gray-500">
                                ({{ formatPrice(props.ticketPrice - calculateDiscountedPrice(membership.id)!) }} off)
                            </span>
                        </div>
                    </div>

                    <div v-if="hasDiscount(membership.id)" class="ml-7 space-y-4">
                        <!-- Discount Type Selection -->
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input
                                    type="radio"
                                    :name="`discount-type-${membership.id}`"
                                    value="percentage"
                                    :checked="getDiscountConfig(membership.id).discount_type === 'percentage'"
                                    @change="updateDiscountType(membership.id, 'percentage')"
                                    class="mr-2"
                                />
                                Percentage
                            </label>
                            <label class="flex items-center">
                                <input
                                    type="radio"
                                    :name="`discount-type-${membership.id}`"
                                    value="fixed"
                                    :checked="getDiscountConfig(membership.id).discount_type === 'fixed'"
                                    @change="updateDiscountType(membership.id, 'fixed')"
                                    class="mr-2"
                                />
                                Fixed Amount
                            </label>
                        </div>

                        <!-- Discount Value Input -->
                        <div class="flex items-center gap-2">
                            <Input
                                type="number"
                                v-model.number="discountConfigs[membership.id].discount_value"
                                @input="emitUpdate()"
                                :step="getDiscountConfig(membership.id).discount_type === 'percentage' ? '1' : '0.01'"
                                :min="0"
                                :max="getDiscountConfig(membership.id).discount_type === 'percentage' ? 100 : undefined"
                                class="w-24"
                                :class="{ 'border-red-500': !isDiscountValueValid(membership.id) }"
                            />
                            <span class="text-sm text-gray-500">
                                {{ getDiscountConfig(membership.id).discount_type === 'percentage' ? '%' : currency }}
                            </span>

                            <div v-if="props.ticketPrice" class="ml-4 text-sm text-gray-600 dark:text-gray-400">
                                Original: {{ formatPrice(props.ticketPrice) }} {{ currency }} →
                                Member: <span class="font-semibold text-green-600 dark:text-green-400">
                                    {{ formatPrice(calculateDiscountedPrice(membership.id)!) }} {{ currency }}
                                </span>
                            </div>
                        </div>

                        <!-- Validation Errors -->
                        <InputError
                            :message="getValidationError(membership.id, 'discount_value')"
                            class="mt-1"
                        />

                        <!-- Warnings -->
                        <div v-if="!isDiscountValueValid(membership.id)" class="text-sm text-red-600 dark:text-red-400">
                            <template v-if="getDiscountConfig(membership.id).discount_type === 'percentage'">
                                Percentage must be between 0% and 100%
                            </template>
                            <template v-else>
                                Fixed discount cannot exceed ticket price
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Summary -->
            <div v-if="activeDiscountsCount > 0 && props.ticketPrice" class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <h4 class="font-medium mb-3 text-gray-900 dark:text-white">Pricing Preview</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Regular Price:</span>
                        <span class="font-mono">{{ formatPrice(props.ticketPrice) }} {{ currency }}</span>
                    </div>
                    <div
                        v-for="membership in membershipLevels.filter(m => m.is_active && hasDiscount(m.id))"
                        :key="`preview-${membership.id}`"
                        class="flex justify-between text-green-600 dark:text-green-400"
                    >
                        <span>{{ getMembershipName(membership) }}:</span>
                        <span class="font-mono">{{ formatPrice(calculateDiscountedPrice(membership.id)!) }} {{ currency }}</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>