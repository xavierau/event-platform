<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Check } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    description: Record<string, string>;
    price: number;
    price_formatted: string;
    stripe_price_id: string;
    duration_months: number;
    benefits: Record<string, string>;
    is_popular: boolean;
    slug: string;
}

const props = defineProps<{
    plan: MembershipLevel;
    isPopular?: boolean;
}>();

const emit = defineEmits<{
    select: [plan: MembershipLevel];
}>();

const { t, locale } = useI18n();

const planName = computed(() => {
    return props.plan.name[locale.value] || props.plan.name.en || '';
});

const planDescription = computed(() => {
    return props.plan.description[locale.value] || props.plan.description.en || '';
});

const benefitsList = computed(() => {
    if (!props.plan.benefits) return [];
    const benefits = props.plan.benefits[locale.value] || props.plan.benefits.en || '';
    if (!benefits) return [];
    return benefits.split('\n').filter(benefit => benefit.trim());
});

const selectPlan = () => {
    emit('select', props.plan);
};
</script>

<template>
    <div :class="[
        'relative rounded-lg border bg-card p-4 shadow-sm transition-all duration-200 h-full flex flex-col w-full max-w-xs flex-shrink-0',
        isPopular 
            ? 'border-primary ring-2 ring-primary/20 mt-4' 
            : 'border-border hover:border-primary/50 mt-8',
        'hover:shadow-md'
    ]">
        <!-- Popular Badge -->
        <div v-if="isPopular" class="absolute -top-3 left-1/2 -translate-x-1/2">
            <Badge variant="default" class="bg-primary text-primary-foreground px-3 py-1">
                {{ t('pricing.most_popular') }}
            </Badge>
        </div>
        
        <!-- Plan Header -->
        <div class="text-center">
            <h3 class="text-xl font-bold text-foreground">{{ planName }}</h3>
            <p class="mt-2 text-sm text-muted-foreground">{{ planDescription }}</p>
        </div>
        
        <!-- Price -->
        <div class="mt-6 text-center px-2">
            <div class="flex items-baseline justify-center gap-1 flex-wrap">
                <span class="text-3xl font-bold text-foreground break-words">
                    {{ plan.price === 0 ? t('pricing.free') : plan.price_formatted }}
                </span>
                <span v-if="plan.price > 0" class="text-sm text-muted-foreground whitespace-nowrap">
                    / {{ t('pricing.month') }}
                </span>
            </div>
        </div>
        
        <!-- Benefits -->
        <div class="mt-6 flex-grow">
            <ul class="space-y-3">
                <li 
                    v-for="benefit in benefitsList" 
                    :key="benefit" 
                    class="flex items-center gap-3"
                >
                    <Check class="h-4 w-4 text-primary flex-shrink-0" />
                    <span class="text-sm text-foreground">
                        {{ benefit }}
                    </span>
                </li>
            </ul>
        </div>
        
        <!-- Select Button -->
        <div class="mt-8">
            <Button 
                @click="selectPlan"
                :variant="isPopular ? 'default' : 'outline'"
                class="w-full"
                size="lg"
            >
                {{ t('pricing.select_plan') }}
            </Button>
        </div>
    </div>
</template>