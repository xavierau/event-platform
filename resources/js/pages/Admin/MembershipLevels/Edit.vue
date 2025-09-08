<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    slug: string;
    description: Record<string, string>;
    price: number;
    points_cost: number | null;
    duration_months: number | null;
    stripe_product_id: string | null;
    stripe_price_id: string | null;
    is_active: boolean;
    sort_order: number;
    benefits: Record<string, string>;
    max_users: number | null;
    metadata: Record<string, any>;
}

const props = defineProps<{
    membershipLevel: MembershipLevel;
}>();

const form = useForm({
    name: props.membershipLevel.name,
    slug: props.membershipLevel.slug,
    description: props.membershipLevel.description,
    price: props.membershipLevel.price,
    points_cost: props.membershipLevel.points_cost || 0,
    duration_months: props.membershipLevel.duration_months,
    stripe_product_id: props.membershipLevel.stripe_product_id || '',
    stripe_price_id: props.membershipLevel.stripe_price_id || '',
    benefits: props.membershipLevel.benefits,
    max_users: props.membershipLevel.max_users,
    is_active: props.membershipLevel.is_active,
    sort_order: props.membershipLevel.sort_order,
    metadata: props.membershipLevel.metadata,
});

const submit = () => {
    form.put(route('admin.membership-levels.update', props.membershipLevel.id));
};

</script>

<template>
    <Head :title="`Edit ${membershipLevel.name.en || membershipLevel.slug}`" />
    
    <AppLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                <!-- Translatable Name Inputs -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Name (Translatable) *</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="name_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input 
                                                type="text" 
                                                v-model="form.name.en" 
                                                id="name_en" 
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="Premium"
                                            />
                                        </div>
                                        <div>
                                            <label for="name_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input 
                                                type="text" 
                                                v-model="form.name['zh-TW']" 
                                                id="name_zh_TW" 
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="高級"
                                            />
                                        </div>
                                        <div>
                                            <label for="name_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input 
                                                type="text" 
                                                v-model="form.name['zh-CN']" 
                                                id="name_zh_CN" 
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="高级"
                                            />
                                        </div>
                                    </div>
                                    <div v-if="form.errors['name.en']" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors['name.en'] }}</div>
                                </fieldset>

                                <!-- Slug -->
                                <div>
                                    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Slug *</label>
                                    <input 
                                        type="text" 
                                        v-model="form.slug" 
                                        id="slug" 
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                        placeholder="premium"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Used in URLs and internal references</p>
                                    <div v-if="form.errors.slug" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.slug }}</div>
                                </div>

                                <!-- Translatable Description Inputs -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Description (Translatable)</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="desc_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <textarea 
                                                v-model="form.description.en" 
                                                id="desc_en" 
                                                rows="3"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="Enhanced features and priority support"
                                            ></textarea>
                                        </div>
                                        <div>
                                            <label for="desc_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <textarea 
                                                v-model="form.description['zh-TW']" 
                                                id="desc_zh_TW" 
                                                rows="3"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="增強功能和優先支援"
                                            ></textarea>
                                        </div>
                                        <div>
                                            <label for="desc_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <textarea 
                                                v-model="form.description['zh-CN']" 
                                                id="desc_zh_CN" 
                                                rows="3"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="增强功能和优先支持"
                                            ></textarea>
                                        </div>
                                    </div>
                                </fieldset>

                                <!-- Translatable Benefits Inputs -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Benefits (Translatable)</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="benefits_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <textarea 
                                                v-model="form.benefits.en" 
                                                id="benefits_en" 
                                                rows="4"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="• Premium event access&#10;• Priority booking&#10;• Exclusive content"
                                            ></textarea>
                                        </div>
                                        <div>
                                            <label for="benefits_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <textarea 
                                                v-model="form.benefits['zh-TW']" 
                                                id="benefits_zh_TW" 
                                                rows="4"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="• 高級活動權限&#10;• 優先預訂&#10;• 獨家內容"
                                            ></textarea>
                                        </div>
                                        <div>
                                            <label for="benefits_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <textarea 
                                                v-model="form.benefits['zh-CN']" 
                                                id="benefits_zh_CN" 
                                                rows="4"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                                placeholder="• 高级活动权限&#10;• 优先预订&#10;• 独家内容"
                                            ></textarea>
                                        </div>
                                    </div>
                                </fieldset>

                                <!-- Pricing Information -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                    <div>
                                        <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price (cents) *</label>
                                        <input 
                                            type="number" 
                                            v-model.number="form.price" 
                                            id="price" 
                                            min="0"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                            placeholder="2900"
                                        />
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ form.price ? `$${(form.price / 100).toFixed(2)}` : '$0.00' }}
                                        </p>
                                        <div v-if="form.errors.price" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.price }}</div>
                                    </div>
                                    <div>
                                        <label for="points_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Points Cost</label>
                                        <input 
                                            type="number" 
                                            v-model.number="form.points_cost" 
                                            id="points_cost" 
                                            min="0"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                            placeholder="2900"
                                        />
                                        <div v-if="form.errors.points_cost" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.points_cost }}</div>
                                    </div>
                                    <div>
                                        <label for="duration_months" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Duration (months)</label>
                                        <input 
                                            type="number" 
                                            v-model.number="form.duration_months" 
                                            id="duration_months" 
                                            min="1"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                            placeholder="1"
                                        />
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty for lifetime access</p>
                                        <div v-if="form.errors.duration_months" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.duration_months }}</div>
                                    </div>
                                </div>

                                <!-- Stripe Integration -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label for="stripe_product_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stripe Product ID</label>
                                        <input 
                                            type="text" 
                                            v-model="form.stripe_product_id" 
                                            id="stripe_product_id" 
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                            placeholder="prod_premium"
                                        />
                                        <div v-if="form.errors.stripe_product_id" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.stripe_product_id }}</div>
                                    </div>
                                    <div>
                                        <label for="stripe_price_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stripe Price ID</label>
                                        <input 
                                            type="text" 
                                            v-model="form.stripe_price_id" 
                                            id="stripe_price_id" 
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                            placeholder="price_premium_monthly"
                                        />
                                        <div v-if="form.errors.stripe_price_id" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.stripe_price_id }}</div>
                                    </div>
                                </div>

                                <!-- Settings -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                    <div>
                                        <label for="max_users" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Max Users</label>
                                        <input 
                                            type="number" 
                                            v-model.number="form.max_users" 
                                            id="max_users" 
                                            min="1"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                            placeholder="1000"
                                        />
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty for unlimited</p>
                                        <div v-if="form.errors.max_users" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.max_users }}</div>
                                    </div>
                                    <div>
                                        <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sort Order *</label>
                                        <input 
                                            type="number" 
                                            v-model.number="form.sort_order" 
                                            id="sort_order" 
                                            min="0"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                            placeholder="0"
                                        />
                                        <div v-if="form.errors.sort_order" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.sort_order }}</div>
                                    </div>
                                    <div class="flex items-center mt-6">
                                        <input 
                                            type="checkbox" 
                                            v-model="form.is_active" 
                                            id="is_active" 
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded"
                                        />
                                        <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Active</label>
                                        <div v-if="form.errors.is_active" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.is_active }}</div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center justify-between pt-6">
                                    <a 
                                        :href="route('admin.membership-levels.index')" 
                                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center"
                                    >
                                        Cancel
                                    </a>
                                    <button 
                                        type="submit" 
                                        :disabled="form.processing"
                                        class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold py-2 px-4 rounded inline-flex items-center"
                                    >
                                        {{ form.processing ? 'Updating...' : 'Update Membership Level' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>