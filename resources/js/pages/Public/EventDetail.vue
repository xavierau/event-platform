<script setup lang="ts">
import TicketPurchaseModal from '@/components/Modals/TicketPurchaseModal.vue';
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import SeoHead from '@/components/Shared/SeoHead.vue';
import WishlistButton from '@/components/Shared/WishlistButton.vue';
import SocialShareWrapper from '@/components/SocialShare/SocialShareWrapper.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import type { PublicTicketType } from '@/types/ticket';

import CommentForm from '@/components/Comments/CommentForm.vue';
import CommentList from '@/components/Comments/CommentList.vue';
import type { Comment } from '@/types/comment';
import ChatbotWidget from '@/components/chatbot/ChatbotWidget.vue';

const { t } = useI18n();

interface SeoData {
    meta_title?: string;
    meta_description?: string;
    keywords?: string;
    og_title?: string;
    og_description?: string;
    og_image_url?: string;
    canonical_url?: string;
}

interface EventOccurrence {
    id: string | number;
    name: string; // e.g., "上海站", "深圳站"
    date_short: string; // e.g., "06.14"
    full_date_time: string; // e.g., "2025.06.21 周六 19:00"
    status_tag?: string; // e.g., "预约"
    venue_name?: string; // Venue specific to this occurrence
    venue_address?: string; // Address specific to this occurrence
    tickets?: PublicTicketType[]; // Added tickets array
}

interface EventDetails {
    id: string | number;
    name: string;
    category_tag: string;
    duration_info: string;
    price_range: string;
    discount_info?: string;
    venue_name: string; // Default/main venue name
    venue_address: string; // Default/main venue address
    tags?: string[]; // Made optional as it was removed from user's last template for venue section
    rating?: number;
    rating_count?: number;
    reviews_summary?: string;
    review_highlight_link_text?: string;
    want_to_see_count?: number;
    main_poster_url?: string;
    thumbnail_url?: string;
    description_html?: string;
    occurrences?: EventOccurrence[];
    landscape_poster_url?: string;
    comments: Comment[];
    comment_config: string;

    // New membership and action fields
    action_type: string;
    redirect_url?: string;
    is_public: boolean;
    visible_to_membership_levels?: number[];
    required_membership_names: string[];
    user_has_access: boolean;
    user_membership?: {
        level_id: number;
        level_name: any;
        status: string;
        expires_at: string;
    };
    seo: SeoData;
}

// Props will be passed from the controller, containing the event details
const props = defineProps({
    event: {
        type: Object as () => EventDetails,
        required: true,
    },
    // It's good practice to explicitly define auth props if they are passed directly
    // However, $page.props.auth.user is generally available globally if middleware is set up.
});

const page = usePage(); // Get access to $page

// Accessing auth state for login check
// Ensure your HandleInertiaRequests middleware shares 'auth.user'
const isAuthenticated = computed(() => !!(page.props.auth as any)?.user);

const selectedOccurrence = ref<EventOccurrence | undefined>(
    props.event.occurrences && props.event.occurrences.length > 1
        ? props.event.occurrences[1] // Default to 2nd item if exists (e.g. Shenzhen)
        : props.event.occurrences && props.event.occurrences.length > 0
          ? props.event.occurrences[0]
          : undefined, // Else 1st or undefined
);

const selectOccurrence = (occurrence: EventOccurrence) => {
    selectedOccurrence.value = occurrence;
};

const formatPrice = (priceRange: string | null) => {
    if (!priceRange) {
        return { currency: '', amount: t('events.price.free'), suffix: '' };
    }

    // Try to extract currency symbol and amount from the formatted price range
    const parts = priceRange.match(/([¥€$£₩฿RM₱₫Rp₹]|HK\$|NT\$|S\$|A\$|C\$)?([0-9]+(?:\.[0-9]+)?)(.*)/);
    if (parts) {
        return {
            currency: parts[1] || '',
            amount: parts[2],
            suffix: parts[3] || '',
        };
    }
    return { currency: '', amount: priceRange, suffix: '' };
};

const eventPrice = formatPrice(props.event.price_range);

// Computed properties for membership pricing
const hasMembershipPricing = computed(() => {
    if (!selectedOccurrence.value?.tickets) return false;
    return selectedOccurrence.value.tickets.some(ticket => ticket.has_membership_discount);
});

const membershipPricingData = computed(() => {
    if (!selectedOccurrence.value?.tickets || !hasMembershipPricing.value) return null;

    const ticketsWithDiscounts = selectedOccurrence.value.tickets.filter(ticket => ticket.has_membership_discount);
    if (ticketsWithDiscounts.length === 0) return null;

    // Find the ticket with the highest savings for display
    const bestDiscount = ticketsWithDiscounts.reduce((best, current) => {
        return (current.savings_percentage || 0) > (best.savings_percentage || 0) ? current : best;
    });

    return {
        memberPrice: bestDiscount.membership_price,
        regularPrice: bestDiscount.price,
        savingsAmount: bestDiscount.savings_amount,
        savingsPercentage: bestDiscount.savings_percentage,
        currency: bestDiscount.currency
    };
});

const showMembershipUpgradeHint = computed(() => {
    // Show hint for non-members when membership discounts are available
    if (isAuthenticated.value) return false; // Don't show to members
    if (!selectedOccurrence.value?.tickets) return false;

    return selectedOccurrence.value.tickets.some(ticket => {
        // Check if any tickets have potential membership discounts
        // This would require backend data about available discounts
        return false; // For now, we'll implement this when we have the data structure
    });
});

const formatCurrency = (amount: number, currency: string = 'HKD') => {
    const currencySymbols: Record<string, string> = {
        HKD: 'HK$',
        USD: '$',
        EUR: '€',
        GBP: '£',
        JPY: '¥',
        CNY: '¥',
        TWD: 'NT$',
        SGD: 'S$',
        AUD: 'A$',
        CAD: 'C$'
    };

    const symbol = currencySymbols[currency.toUpperCase()] || currency.toUpperCase() + ' ';
    return `${symbol}${amount.toFixed(2)}`;
};

// Computed property for the overall date range shown in the hero section
const heroDateRange = computed(() => {
    if (props.event.occurrences && props.event.occurrences.length > 0) {
        const firstDate = props.event.occurrences[0].full_date_time.split(' ')[0];
        const lastDate = props.event.occurrences[props.event.occurrences.length - 1].full_date_time.split(' ')[0];
        if (firstDate === lastDate) return firstDate;
        return `${firstDate} - ${lastDate}`;
    }
    return props.event.duration_info; // Fallback to duration_info if no occurrences for a date indication
});

const currentVenueName = computed(() => {
    return selectedOccurrence.value?.venue_name || props.event.venue_name;
});

const currentVenueAddress = computed(() => {
    return selectedOccurrence.value?.venue_address || props.event.venue_address;
});

// Added for modal visibility
const showPurchaseModal = ref(false);

// Member QR modal variables
const showMemberQrModal = ref(false);
const memberQrCodeUrl = ref('');

const openPurchaseModal = () => {
    // Check if user is logged in
    if (!isAuthenticated.value) {

        // If using Ziggy for named routes, route('login') is correct.
        // If not, replace with the actual path e.g., '/login'.
        router.visit(route('login'));
        return;
    }

    // Existing logic to open modal if tickets are available
    if (selectedOccurrence.value && selectedOcurrenceHasTickets.value) {
        showPurchaseModal.value = true;
    } else {
        // Optionally, handle the case where there are no tickets or no occurrence selected
        // For now, we can just prevent the modal from opening or show an alert.
        alert(t('events.alerts.no_tickets_available'));
    }
};

const closePurchaseModal = () => {
    showPurchaseModal.value = false;
};

// Wishlist functionality is now handled by the WishlistButton component
const handleWishlistChanged = (inWishlist: boolean) => {
    console.log(`Event ${props.event.id} wishlist status changed:`, inWishlist);
    // You can add additional logic here if needed, such as showing a toast notification
};

const handleWishlistError = (message: string) => {
    console.error('Wishlist error:', message);
    // You can show a toast notification or handle the error as needed
    alert(`${t('wishlist.error')}: ${message}`);
};

// Compute button configuration based on user status and event settings
const actionButtonConfig = computed(() => {
    // Not authenticated
    if (!isAuthenticated.value) {
        return {
            text: t('actions.purchase'),
            disabled: false,
            action: 'login',
            className:
                'px-3 sm:px-6 py-2 text-sm bg-pink-500 hover:bg-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700 text-white rounded-full font-semibold whitespace-nowrap',
        };
    }

    // Check membership requirements
    const userHasAccess = props.event.user_has_access;

    // User doesn't meet membership requirements
    if (!userHasAccess) {
        const requiredLevels = props.event.required_membership_names;
        const text =
            requiredLevels.length === 1 ? t('events.membership.single_required', { level: requiredLevels[0] }) : t('events.membership.required');

        return {
            text,
            disabled: true,
            action: 'none',
            className: 'px-3 sm:px-6 py-2 text-sm bg-gray-400 cursor-not-allowed text-white rounded-full font-semibold whitespace-nowrap',
            tooltip: t('events.membership.tooltip', { levels: requiredLevels.join(' or ') }),
        };
    }

    // User has access - check action type
    if (props.event.action_type === 'show_member_qr') {
        return {
            text: t('events.actions.show_qr'),
            disabled: false,
            action: 'showQr',
            className:
                'px-3 sm:px-6 py-2 text-sm bg-pink-500 hover:bg-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700 text-white rounded-full font-semibold whitespace-nowrap',
        };
    }

    // Default: purchase ticket
    return {
        text: t('actions.purchase'),
        disabled: false,
        action: 'purchase',
        className:
            'px-3 sm:px-6 py-2 text-sm bg-pink-500 hover:bg-pink-600 dark:bg-pink-600 dark:hover:bg-pink-700 text-white rounded-full font-semibold whitespace-nowrap',
    };
});

// Handle button click
async function handleActionButtonClick() {
    const config = actionButtonConfig.value;

    switch (config.action) {
        case 'login':
            router.visit(route('login'));
            break;

        case 'showQr':
            await generateAndShowMemberQr();
            break;

        case 'purchase':
            // Check if event has a redirect URL configured
            if (props.event.redirect_url) {
                window.open(props.event.redirect_url, '_blank');
            } else {
                openPurchaseModal();
            }
            break;

        case 'none':
            // Disabled button - do nothing
            break;
    }
}

// Generate member QR with event context
async function generateAndShowMemberQr() {
    const user = (page.props.auth as any)?.user;
    const membership = props.event.user_membership;

    const membershipData = {
        // Standard member data
        userId: user.id,
        userName: user.name,
        email: user.email,
        membershipLevel: membership?.level_name?.en || 'Member',
        membershipStatus: membership?.status || 'active',
        expiresAt: membership?.expires_at,
        timestamp: new Date().toISOString(),

        // Event context for analytics
        eventContext: {
            eventId: props.event.id,
            eventName: props.event.name,
            eventOccurrenceId: selectedOccurrence.value?.id || null,
            occurrenceName: selectedOccurrence.value?.name || null,
            occurrenceDate: selectedOccurrence.value?.full_date_time || null,
            venueName: currentVenueName.value,
            source: 'event_detail_page',
        },
    };

    try {
        memberQrCodeUrl.value = await QRCode.toDataURL(JSON.stringify(membershipData), {
            width: 300,
            margin: 2,
            color: { dark: '#000000', light: '#FFFFFF' },
        });

        showMemberQrModal.value = true;
    } catch (error) {
        console.error('Error generating QR code:', error);
        alert(t('events.qr.error_generating'));
    }
}

if (props.event.occurrences && props.event.occurrences.length > 0) {
    selectOccurrence(props.event.occurrences[0]);
}

const selectedOcurrenceHasTickets = computed(() => {
    return selectedOccurrence.value?.tickets && selectedOccurrence.value?.tickets?.length > 0;
});

onMounted(() => {});

const localComments = ref<Comment[]>(props.event.comments || []);
const showCommentForm = ref(false);

const handleCommentAdded = (newComment: Comment) => {
    localComments.value.unshift(newComment);
    showCommentForm.value = false;
};
</script>

<template>
    <!-- SEO Meta Tags -->
    <SeoHead
        :seo="event.seo"
        :fallback-title="event.name"
        :fallback-description="event.description_html ? event.description_html.replace(/<[^>]*>/g, '').substring(0, 160) : ''"
    />

    <CustomContainer :title="event.name" :poster_url="event.landscape_poster_url">
        <div class="min-h-screen bg-gray-100 pb-20 dark:bg-gray-900">
            <!-- padding-bottom for fixed footer -->

            <!-- Hero/Header Section -->
            <section class="bg-white p-4 shadow-sm dark:bg-gray-800">
                <div class="container mx-auto flex">
                    <div class="w-1/4 flex-shrink-0 md:w-1/5">
                        <img
                            :src="event.thumbnail_url || 'https://via.placeholder.com/150x200.png?text=Event'"
                            :alt="event.name"
                            class="h-auto w-full rounded object-cover"
                        />
                    </div>
                    <div class="w-3/4 pl-4 md:w-4/5">
                        <span
                            class="mb-1 inline-block rounded bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >{{ event.category_tag }}</span
                        >
                        <h1 class="mb-1 text-lg leading-tight font-bold text-gray-900 md:text-xl dark:text-gray-100">
                            {{ event.name }}
                        </h1>
                        <p class="mb-1 text-sm text-gray-600 dark:text-gray-400">{{ heroDateRange }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ event.duration_info }}</p>
                    </div>
                </div>
            </section>

            <!-- Enhanced Price Section with Membership Pricing -->
            <section class="mt-3 bg-white p-4 shadow-sm dark:bg-gray-800" v-if="selectedOcurrenceHasTickets">
                <div class="container mx-auto">
                    <!-- Member Pricing Display -->
                    <div v-if="hasMembershipPricing && membershipPricingData" class="space-y-2">
                        <!-- Primary Member Price -->
                        <div class="flex items-center space-x-3">
                            <div>
                                <span class="text-2xl font-bold text-pink-500 dark:text-pink-400">
                                    {{ formatCurrency(membershipPricingData.memberPrice, membershipPricingData.currency) }}
                                </span>
                                <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('events.pricing.member_price') }}
                                </span>
                            </div>
                            <!-- Savings Badge -->
                            <div class="rounded-full bg-green-100 px-3 py-1 dark:bg-green-800">
                                <span class="text-sm font-semibold text-green-700 dark:text-green-200">
                                    {{ t('events.pricing.save') }} {{ formatCurrency(membershipPricingData.savingsAmount, membershipPricingData.currency) }}
                                </span>
                            </div>
                        </div>

                        <!-- Regular Price Reference -->
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500 line-through dark:text-gray-400">
                                {{ t('events.pricing.regular') }}: {{ formatCurrency(membershipPricingData.regularPrice, membershipPricingData.currency) }}
                            </span>
                            <span class="text-xs text-green-600 dark:text-green-400">
                                ({{ membershipPricingData.savingsPercentage }}% {{ t('events.pricing.off') }})
                            </span>
                        </div>

                        <!-- Member Status Indicator -->
                        <div class="flex items-center space-x-2">
                            <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ t('events.pricing.member_benefits_applied') }}
                            </span>
                        </div>
                    </div>

                    <!-- Regular Pricing Display (for non-members or members without discounts) -->
                    <div v-else>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-2xl font-bold text-red-500 dark:text-red-400">
                                    <span class="text-base">{{ eventPrice.currency }}</span>{{ eventPrice.amount }}
                                </span>
                                <span class="text-2xl font-bold text-red-500 dark:text-red-400">{{ eventPrice.suffix }}</span>

                                <!-- Member Price Indicator for members without discounts -->
                                <span v-if="isAuthenticated && event.user_membership" class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                                    ({{ t('events.pricing.member_price_applied') }})
                                </span>
                            </div>
                        </div>

                        <!-- Membership Upgrade Hint for Non-Members -->
                        <div v-if="showMembershipUpgradeHint" class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ t('events.pricing.members_save_hint') }}
                                <a href="#" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    {{ t('events.pricing.learn_more') }}
                                </a>
                            </p>
                        </div>
                    </div>

                    <!-- Legacy discount info display -->
                    <div v-if="event.discount_info" class="mt-2">
                        <span class="inline-block rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600 dark:bg-red-700 dark:text-red-300">
                            {{ event.discount_info }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- Occurrences Section -->
            <section v-if="event.occurrences && event.occurrences.length > 1" class="mt-3 bg-white pt-4 pb-2 shadow-sm dark:bg-gray-800">
                <div class="container mx-auto">
                    <div
                        class="scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600 scrollbar-track-gray-100 dark:scrollbar-track-gray-700 scrollbar-thumb-rounded -mb-2 flex flex-col overflow-x-auto pb-2 whitespace-nowrap"
                    >
                        <button
                            v-for="occurrence in event.occurrences"
                            :key="occurrence.id"
                            @click="selectOccurrence(occurrence)"
                            :class="[
                                'relative mr-1 flex-shrink-0 rounded-t-md px-4 py-2 text-center focus:outline-none',
                                selectedOccurrence?.id === occurrence.id
                                    ? 'bg-pink-500 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                            ]"
                        >
                            <span class="block text-sm font-medium">{{ occurrence.name }}</span>
                            <span class="block text-xs">{{ occurrence.date_short }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Selected Occurrence Date/Time and Duration -->
            <section
                v-if="selectedOccurrence"
                class="bg-white p-3 pb-4 shadow-sm dark:bg-gray-800"
                :class="{ 'mt-0': event.occurrences && event.occurrences.length > 0, 'mt-3': !event.occurrences || event.occurrences.length === 0 }"
            >
                <div class="container mx-auto">
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ selectedOccurrence.full_date_time }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ event.duration_info }}</p>
                </div>
            </section>

            <!-- Venue Information Section -->
            <section class="mt-3 bg-white p-4 shadow-sm dark:bg-gray-800" :class="{ 'mt-3': selectedOccurrence }">
                <div class="container mx-auto">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-md mb-1 font-semibold text-gray-900 dark:text-gray-100">{{ currentVenueName }}</h2>
                            <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">{{ currentVenueAddress }}</p>
                        </div>
                        <a
                            :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(currentVenueAddress)}`"
                            target="_blank"
                            class="w-1/5 text-right text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            {{ t('events.venue.view_map') }} >
                        </a>
                    </div>
                </div>
            </section>

            <!-- Event Description Section  -->
            <section class="mt-1 bg-white p-4 shadow-sm dark:bg-gray-800">
                <div class="container mx-auto max-w-full">
                    <h2 class="text-md mb-3 font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('events.description.title') }}
                    </h2>
                    <div
                        class="prose dark:prose-invert prose-img:max-w-full prose-img:h-auto event-description max-w-full break-words"
                        v-html="event.description_html"
                    ></div>
                    <!-- Placeholder for more images/media -->
                </div>
            </section>

            <!-- Comments Section -->
            <section class="mt-3 bg-white p-4 shadow-sm dark:bg-gray-800" v-if="event.comment_config !== 'disabled'">
                <div class="container mx-auto">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('comments.title') }}</h2>
                        <button @click="showCommentForm = !showCommentForm" class="text-indigo-600 hover:text-indigo-800">
                            {{ showCommentForm ? t('actions.cancel') : t('comments.leave_comment') }}
                        </button>
                    </div>
                    <CommentForm
                        v-if="showCommentForm"
                        :commentable-type="'App\\Models\\Event'"
                        :commentable-id="Number(event.id)"
                        @comment-added="handleCommentAdded"
                    />
                    <CommentList
                        :comments="localComments"
                        :commentable-type="'App\\Models\\Event'"
                        :commentable-id="Number(event.id)"
                        :can-comment="true"
                    />
                </div>
            </section>

            <!-- Fixed Footer/Bottom Bar -->
            <footer
                class="shadow-top-lg fixed right-0 bottom-0 left-0 z-50 max-w-[100vw] overflow-hidden border-t border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="container mx-auto flex min-w-0 items-center justify-between">
                    <div class="flex flex-shrink-0 space-x-2 text-center sm:space-x-4">
                        <Link href="/" class="text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400">
                            <!-- Placeholder for Home Icon -->
                            <span class="block text-xl">🏠</span>
                            <span class="hidden sm:inline">{{ t('navigation.home') }}</span>
                        </Link>
                        <Link href="/my-bookings" class="text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400">
                            <!-- Placeholder for My Orders Icon -->
                            <span class="block text-xl">🎫</span>
                            <span class="hidden sm:inline">{{ t('navigation.my_bookings') }}</span>
                        </Link>
                    </div>
                    <div class="flex min-w-0 flex-shrink-0 space-x-1 sm:space-x-2">
                        <WishlistButton
                            :event-id="Number(event.id)"
                            variant="button"
                            size="sm"
                            :show-text="false"
                            @wishlist-changed="handleWishlistChanged"
                            @error="handleWishlistError"
                        />
                        <SocialShareWrapper
                            shareable-type="App\Models\Event"
                            :shareable-id="Number(event.id)"
                            class="flex-shrink-0"
                        />
                        <button
                            :class="actionButtonConfig.className"
                            :disabled="actionButtonConfig.disabled"
                            :title="actionButtonConfig.tooltip"
                            @click="handleActionButtonClick"
                        >
                            {{ actionButtonConfig.text }}
                        </button>
                    </div>
                </div>
            </footer>

            <!-- Ticket Purchase Modal -->
            <TicketPurchaseModal :show-modal="showPurchaseModal" :occurrence="selectedOccurrence" @close="closePurchaseModal" />

            <!-- Member QR Modal -->
            <Teleport to="body">
                <div
                    v-if="showMemberQrModal"
                    class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black"
                    @click.self="showMemberQrModal = false"
                >
                    <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('events.qr.modal.title') }}</h3>
                            <button
                                @click="showMemberQrModal = false"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="text-center">
                            <img :src="memberQrCodeUrl" :alt="t('events.qr.modal.alt_text')" class="mx-auto mb-4 rounded-lg" />

                            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ event.name }}</p>
                                <p v-if="selectedOccurrence" class="text-gray-700 dark:text-gray-200">
                                    {{ selectedOccurrence.full_date_time }}
                                </p>
                                <p class="text-gray-600 dark:text-gray-300">{{ currentVenueName }}</p>
                                <div class="mt-3 border-t pt-2">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ (page.props.auth as any)?.user?.name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ event.user_membership?.level_name?.en || t('membership.member_default') }}
                                        {{ t('membership.member') }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('events.qr.modal.instruction') }}
                            </p>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>

        <ChatbotWidget v-if="page.props.chatbot_enabled" />
    </CustomContainer>
</template>

<style scoped>
.prose :where(img):not(:where([class~='not-prose'] *)) {
    margin-top: 0;
    margin-bottom: 0;
}

/* Responsive YouTube and video embeds */
.event-description :deep(iframe) {
    max-width: 100%;
    height: auto;
    aspect-ratio: 16/9;
}

.event-description :deep(iframe[src*='youtube.com']),
.event-description :deep(iframe[src*='youtu.be']),
.event-description :deep(iframe[src*='vimeo.com']) {
    width: 100%;
    max-width: 100%;
    height: auto;
    aspect-ratio: 16/9;
}

/* Responsive video containers */
.event-description :deep(.video-container),
.event-description :deep(.embed-responsive) {
    position: relative;
    width: 100%;
    max-width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
}

.event-description :deep(.video-container iframe),
.event-description :deep(.embed-responsive iframe) {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.shadow-top-lg {
    box-shadow:
        0 -4px 6px -1px rgb(0 0 0 / 0.05),
        0 -2px 4px -2px rgb(0 0 0 / 0.05);
}

/* Custom scrollbar styles */
.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #e2e8f0; /* gray-300 gray-100 */
}

.dark .scrollbar-thin {
    /* Target for dark mode */
    scrollbar-color: #4b5563 #374151; /* dark:gray-600 dark:gray-700 */
}

.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
    width: 6px; /* Added for vertical scrollbars if any */
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #e2e8f0; /* gray-100 */
    border-radius: 3px;
}

.dark .scrollbar-thin::-webkit-scrollbar-track {
    background: #374151; /* dark:gray-700 */
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: #cbd5e1; /* gray-300 */
    border-radius: 3px;
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: #4b5563; /* dark:gray-600 */
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8; /* gray-400 */
}

.dark .scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: #6b7280; /* dark:gray-500 */
}
</style>
