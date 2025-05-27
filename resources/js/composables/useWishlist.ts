import type { EventItem } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface WishlistResponse {
    success: boolean;
    message?: string;
    data: {
        wishlist?: EventItem[];
        count?: number;
        in_wishlist?: boolean;
        added?: boolean;
    };
}

export function useWishlist() {
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const wishlistItems = ref<EventItem[]>([]);
    const wishlistCount = ref(0);

    // Track individual event wishlist status
    const eventWishlistStatus = ref<Record<number, boolean>>({});

    const clearError = () => {
        error.value = null;
    };

    const checkAuthentication = (): boolean => {
        // Check if user is authenticated by looking for auth data in page props
        // Note: This is a fallback check. The component should handle auth checks primarily.
        try {
            const page = (window as any).$page;
            const isAuthenticated = page?.props?.auth?.user;

            console.log('isAuthenticated', isAuthenticated, page);

            if (!isAuthenticated) {
                router.visit(route('login'));
                return false;
            }

            return true;
        } catch {
            // If we can't access page props, assume not authenticated
            router.visit(route('login'));
            return false;
        }
    };

    const handleApiError = (err: any) => {
        console.error('Wishlist API error:', err);
        if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else if (err.message) {
            error.value = err.message;
        } else {
            error.value = 'An unexpected error occurred';
        }
    };

    const addToWishlist = async (eventId: number): Promise<boolean> => {
        // Check authentication before making API call
        if (!checkAuthentication()) {
            return false;
        }

        isLoading.value = true;
        clearError();

        try {
            const response = await fetch('/wishlist', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ event_id: eventId }),
            });

            const data: WishlistResponse = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    // Redirect to login if not authenticated
                    router.visit(route('login'));
                    return false;
                }
                throw new Error(data.message || 'Failed to add to wishlist');
            }

            // Update local state
            eventWishlistStatus.value[eventId] = true;

            return true;
        } catch (err) {
            handleApiError(err);
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const removeFromWishlist = async (eventId: number): Promise<boolean> => {
        // Check authentication before making API call
        if (!checkAuthentication()) {
            return false;
        }

        isLoading.value = true;
        clearError();

        try {
            const response = await fetch(`/wishlist/${eventId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data: WishlistResponse = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    router.visit(route('login'));
                    return false;
                }
                throw new Error(data.message || 'Failed to remove from wishlist');
            }

            // Update local state
            eventWishlistStatus.value[eventId] = false;

            // Remove from wishlist items if present
            wishlistItems.value = wishlistItems.value.filter((item) => item.id !== eventId);
            wishlistCount.value = Math.max(0, wishlistCount.value - 1);

            return true;
        } catch (err) {
            handleApiError(err);
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const toggleWishlist = async (eventId: number): Promise<{ added: boolean; inWishlist: boolean } | null> => {
        isLoading.value = true;
        clearError();

        try {
            const response = await fetch(`/wishlist/${eventId}/toggle`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data: WishlistResponse = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    router.visit(route('login'));
                    return null;
                }
                throw new Error(data.message || 'Failed to toggle wishlist');
            }

            // Update local state
            const { added, in_wishlist } = data.data;
            eventWishlistStatus.value[eventId] = in_wishlist || false;

            if (!added) {
                // Removed from wishlist
                wishlistItems.value = wishlistItems.value.filter((item) => item.id !== eventId);
                wishlistCount.value = Math.max(0, wishlistCount.value - 1);
            }

            return { added: added || false, inWishlist: in_wishlist || false };
        } catch (err) {
            handleApiError(err);
            return null;
        } finally {
            isLoading.value = false;
        }
    };

    const checkWishlistStatus = async (eventId: number): Promise<boolean> => {
        try {
            const response = await fetch(`/wishlist/${eventId}/check`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data: WishlistResponse = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    return false; // Not authenticated, so not in wishlist
                }
                throw new Error(data.message || 'Failed to check wishlist status');
            }

            const inWishlist = data.data.in_wishlist || false;
            eventWishlistStatus.value[eventId] = inWishlist;

            return inWishlist;
        } catch (err) {
            console.warn('Failed to check wishlist status:', err);
            return false;
        }
    };

    const getUserWishlist = async (): Promise<EventItem[]> => {
        isLoading.value = true;
        clearError();

        try {
            const response = await fetch('/wishlist', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data: WishlistResponse = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    router.visit(route('login'));
                    return [];
                }
                throw new Error(data.message || 'Failed to get wishlist');
            }

            wishlistItems.value = data.data.wishlist || [];
            wishlistCount.value = data.data.count || 0;

            // Update individual event status
            wishlistItems.value.forEach((item) => {
                eventWishlistStatus.value[item.id] = true;
            });

            return wishlistItems.value;
        } catch (err) {
            handleApiError(err);
            return [];
        } finally {
            isLoading.value = false;
        }
    };

    const clearWishlist = async (): Promise<boolean> => {
        isLoading.value = true;
        clearError();

        try {
            const response = await fetch('/wishlist', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data: WishlistResponse = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    router.visit(route('login'));
                    return false;
                }
                throw new Error(data.message || 'Failed to clear wishlist');
            }

            // Clear local state
            wishlistItems.value = [];
            wishlistCount.value = 0;
            eventWishlistStatus.value = {};

            return true;
        } catch (err) {
            handleApiError(err);
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const isInWishlist = (eventId: number): boolean => {
        return eventWishlistStatus.value[eventId] || false;
    };

    return {
        // State
        isLoading: computed(() => isLoading.value),
        error: computed(() => error.value),
        wishlistItems: computed(() => wishlistItems.value),
        wishlistCount: computed(() => wishlistCount.value),

        // Methods
        addToWishlist,
        removeFromWishlist,
        toggleWishlist,
        checkWishlistStatus,
        getUserWishlist,
        clearWishlist,
        isInWishlist,
        clearError,
    };
}
