<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import PricingCard from '@/components/PricingCard.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { LoaderCircle, ArrowLeft } from 'lucide-vue-next';
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
    benefits: string[];
    is_popular: boolean;
    slug: string;
}

const props = defineProps<{
    membershipLevels: MembershipLevel[];
}>();

const { t, locale } = useI18n();
const currentStep = ref<'pricing' | 'registration'>('pricing');
const selectedPlan = ref<MembershipLevel | null>(null);

const form = useForm({
    name: '',
    email: '',
    mobile_number: '',
    password: '',
    password_confirmation: '',
    selected_price_id: '',
});

const selectedPlanName = computed(() => {
    if (!selectedPlan.value) return '';
    return selectedPlan.value.name[locale.value] || selectedPlan.value.name.en || '';
});

const selectPlan = (plan: MembershipLevel) => {
    selectedPlan.value = plan;
    form.selected_price_id = plan.stripe_price_id;
    currentStep.value = 'registration';
};

const goBackToPricing = () => {
    currentStep.value = 'pricing';
};

const submitRegistration = () => {
    form.post(route('register.subscription.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Handled by redirect to Stripe Checkout or success page
        },
    });
};
</script>

<template>
    <AuthLayout>
        <Head :title="t('auth.register.with_plan')" />
        
        <!-- Step Indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center">
                    <div :class="[
                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-medium',
                        currentStep === 'pricing' 
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-background text-foreground border-primary'
                    ]">1</div>
                    <span class="ml-2 text-sm font-medium text-foreground">
                        {{ t('registration.steps.choose_plan') }}
                    </span>
                </div>
                <div class="mx-4 h-px w-16 bg-border" />
                <div class="flex items-center">
                    <div :class="[
                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-medium',
                        currentStep === 'registration'
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-background text-muted-foreground border-border'
                    ]">2</div>
                    <span class="ml-2 text-sm font-medium" :class="[
                        currentStep === 'registration' ? 'text-foreground' : 'text-muted-foreground'
                    ]">
                        {{ t('registration.steps.create_account') }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Pricing Selection Step -->
        <div v-if="currentStep === 'pricing'" class="space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-foreground">{{ t('pricing.choose_your_plan') }}</h2>
                <p class="mt-2 text-muted-foreground max-w-2xl mx-auto">
                    {{ t('pricing.plan_description') }}
                </p>
            </div>
            
            <div class="grid gap-6 md:grid-cols-3 max-w-5xl mx-auto">
                <PricingCard
                    v-for="plan in membershipLevels"
                    :key="plan.id"
                    :plan="plan"
                    :is-popular="plan.is_popular"
                    @select="selectPlan"
                />
            </div>
            
            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.have_account') }}
                <TextLink :href="route('login')" class="underline underline-offset-4">
                    {{ t('auth.login.submit') }}
                </TextLink>
            </div>
        </div>
        
        <!-- Registration Form Step -->
        <div v-else-if="currentStep === 'registration'" class="space-y-6 max-w-md mx-auto">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-foreground">
                    {{ t('registration.create_account') }}
                </h2>
                <div class="mt-2 text-sm text-muted-foreground">
                    <p>{{ t('registration.selected_plan', { plan: selectedPlanName }) }}</p>
                    <button
                        @click="goBackToPricing"
                        class="inline-flex items-center gap-1 mt-2 text-primary hover:underline"
                    >
                        <ArrowLeft class="h-3 w-3" />
                        {{ t('registration.change_plan') }}
                    </button>
                </div>
            </div>
            
            <!-- Selected Plan Summary -->
            <div v-if="selectedPlan" class="bg-muted/50 rounded-lg p-4 border">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-foreground">{{ selectedPlanName }}</h3>
                        <p class="text-sm text-muted-foreground">
                            {{ selectedPlan.description[locale] || selectedPlan.description.en }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-foreground">
                            {{ selectedPlan.price === 0 ? t('pricing.free') : selectedPlan.price_formatted }}
                        </div>
                        <div v-if="selectedPlan.price > 0" class="text-xs text-muted-foreground">
                            / {{ t('pricing.month') }}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Registration Form -->
            <form @submit.prevent="submitRegistration" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="name">{{ t('forms.fields.name.label') }}</Label>
                    <Input 
                        id="name" 
                        type="text" 
                        required 
                        autofocus 
                        :tabindex="1" 
                        autocomplete="name" 
                        v-model="form.name" 
                        :placeholder="t('forms.fields.name.placeholder')" 
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('forms.fields.email.label') }}</Label>
                    <Input 
                        id="email" 
                        type="email" 
                        required 
                        :tabindex="2" 
                        autocomplete="email" 
                        v-model="form.email" 
                        :placeholder="t('forms.fields.email.placeholder')" 
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="mobile_number">{{ t('forms.fields.mobile_number.label') }}</Label>
                    <Input 
                        id="mobile_number" 
                        type="tel" 
                        required 
                        :tabindex="3" 
                        autocomplete="tel" 
                        v-model="form.mobile_number" 
                        :placeholder="t('forms.fields.mobile_number.placeholder')" 
                    />
                    <InputError :message="form.errors.mobile_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('forms.fields.password.label') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password"
                        :placeholder="t('forms.fields.password.placeholder')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ t('forms.fields.password_confirmation.label') }}</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        :placeholder="t('forms.fields.password_confirmation.placeholder')"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :tabindex="6" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                    {{ t('auth.register.submit') }}
                </Button>
            </form>

            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.have_account') }}
                <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="7">
                    {{ t('auth.login.submit') }}
                </TextLink>
            </div>
        </div>
    </AuthLayout>
</template>