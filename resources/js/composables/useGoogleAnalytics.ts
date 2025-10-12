import { usePage } from '@inertiajs/vue3'

declare global {
  interface Window {
    gtag?: (...args: any[]) => void
    dataLayer?: any[]
  }
}

interface GA4Item {
  item_id: string
  item_name: string
  item_category?: string
  item_category2?: string
  item_brand?: string
  price: number
  quantity: number
  item_variant?: string
  custom_parameters?: Record<string, any>
}

interface GA4PurchaseEvent {
  transaction_id: string
  value: number
  currency: string
  affiliation?: string
  tax?: number
  shipping?: number
  user_id?: string
  items: GA4Item[]
}

interface GA4UserProperties {
  user_type?: string
  membership_tier?: string
  membership_status?: string
  customer_since?: string
  total_bookings?: number
  customer_segment?: string
}

export function useGoogleAnalytics() {
  const page = usePage()

  /**
   * Check if Google Analytics is available and configured
   */
  const isGAAvailable = (): boolean => {
    return typeof window !== 'undefined' &&
           typeof window.gtag === 'function' &&
           !!window.dataLayer
  }

  /**
   * Log a message with GA4 context
   */
  const logGA4 = (message: string, data?: any): void => {
    if (import.meta.env.DEV) {
      console.log(`[GA4] ${message}`, data || '')
    }
  }

  /**
   * Track a purchase event in GA4
   */
  const trackPurchase = (purchaseData: GA4PurchaseEvent): void => {
    if (!isGAAvailable()) {
      logGA4('gtag not available, purchase event not sent')
      return
    }

    try {
      // Validate required fields
      if (!purchaseData.transaction_id || !purchaseData.value || !purchaseData.currency) {
        throw new Error('Missing required purchase data: transaction_id, value, or currency')
      }

      if (!purchaseData.items || purchaseData.items.length === 0) {
        throw new Error('Purchase must include at least one item')
      }

      // Send the purchase event
      window.gtag!('event', 'purchase', purchaseData)

      logGA4('Purchase event sent', purchaseData)
    } catch (error) {
      console.error('[GA4] Error tracking purchase:', error)
      logGA4('Purchase tracking failed', { error, purchaseData })
    }
  }

  /**
   * Set user ID for tracking
   */
  const setUserId = (userId: string | number): void => {
    if (!isGAAvailable()) {
      logGA4('gtag not available, user ID not set')
      return
    }

    try {
      window.gtag!('set', { user_id: String(userId) })
      logGA4(`User ID set to ${userId}`)
    } catch (error) {
      console.error('[GA4] Error setting user ID:', error)
    }
  }

  /**
   * Set user properties for segmentation
   */
  const setUserProperties = (properties: GA4UserProperties): void => {
    if (!isGAAvailable()) {
      logGA4('gtag not available, user properties not set')
      return
    }

    try {
      window.gtag!('set', 'user_properties', properties)
      logGA4('User properties set', properties)
    } catch (error) {
      console.error('[GA4] Error setting user properties:', error)
    }
  }

  /**
   * Track a custom event
   */
  const trackEvent = (eventName: string, parameters: Record<string, any> = {}): void => {
    if (!isGAAvailable()) {
      logGA4(`gtag not available, event '${eventName}' not sent`)
      return
    }

    try {
      window.gtag!('event', eventName, parameters)
      logGA4(`Event '${eventName}' sent`, parameters)
    } catch (error) {
      console.error(`[GA4] Error tracking event '${eventName}':`, error)
    }
  }

  /**
   * Track when user begins checkout process
   */
  const trackBeginCheckout = (value: number, currency: string, items: GA4Item[]): void => {
    trackEvent('begin_checkout', {
      value,
      currency,
      items
    })
  }

  /**
   * Track when user adds item to cart (selects tickets)
   */
  const trackAddToCart = (value: number, currency: string, items: GA4Item[]): void => {
    trackEvent('add_to_cart', {
      value,
      currency,
      items
    })
  }

  /**
   * Track when user views an event
   */
  const trackViewItem = (item: GA4Item): void => {
    trackEvent('view_item', {
      value: item.price,
      currency: 'USD', // or get from item
      items: [item]
    })
  }

  /**
   * Track user login
   */
  const trackLogin = (method: string = 'email'): void => {
    trackEvent('login', { method })
  }

  /**
   * Track user registration
   */
  const trackSignUp = (method: string = 'email'): void => {
    trackEvent('sign_up', { method })
  }

  /**
   * Track search events
   */
  const trackSearch = (searchTerm: string, category?: string): void => {
    trackEvent('search', {
      search_term: searchTerm,
      ...(category && { search_category: category })
    })
  }

  /**
   * Get current user ID from page props if available
   */
  const getCurrentUserId = (): string | null => {
    const auth = page.props.auth as any
    return auth?.user?.id ? String(auth.user.id) : null
  }

  /**
   * Check if user is currently authenticated
   */
  const isAuthenticated = (): boolean => {
    const auth = page.props.auth as any
    return !!auth?.user
  }

  /**
   * Update user properties when user state changes
   */
  const updateUserContext = (userProperties?: GA4UserProperties): void => {
    const userId = getCurrentUserId()

    if (userId) {
      setUserId(userId)

      if (userProperties) {
        setUserProperties(userProperties)
      }
    }
  }

  /**
   * Track page view manually (useful for SPAs)
   */
  const trackPageView = (pageTitle?: string, pagePath?: string): void => {
    if (!isGAAvailable()) {
      logGA4('gtag not available, page view not sent')
      return
    }

    try {
      const parameters: Record<string, any> = {}

      if (pageTitle) parameters.page_title = pageTitle
      if (pagePath) parameters.page_location = pagePath

      window.gtag!('event', 'page_view', parameters)
      logGA4('Page view sent', parameters)
    } catch (error) {
      console.error('[GA4] Error tracking page view:', error)
    }
  }

  return {
    // Core tracking
    trackPurchase,
    trackEvent,
    trackPageView,

    // User management
    setUserId,
    setUserProperties,
    updateUserContext,
    getCurrentUserId,
    isAuthenticated,

    // E-commerce events
    trackBeginCheckout,
    trackAddToCart,
    trackViewItem,

    // User lifecycle events
    trackLogin,
    trackSignUp,
    trackSearch,

    // Utilities
    isGAAvailable,
    logGA4
  }
}