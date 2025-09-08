export interface PromotionalModalData {
  id: number;
  title: string;
  content: string;
  type: 'modal' | 'banner';
  button_text?: string;
  button_url?: string;
  is_dismissible: boolean;
  banner_image_url?: string;
  background_image_url?: string;
  display_conditions?: Record<string, any>;
  pages?: string[] | null;
  membership_levels?: number[] | null;
  user_segments?: string[] | null;
  start_at?: string | null;
  end_at?: string | null;
  display_frequency: 'once' | 'daily' | 'weekly' | 'always';
  cooldown_hours: number;
  impressions_count: number;
  clicks_count: number;
  conversion_rate: number;
  is_active: boolean;
  priority: number;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export interface PromotionalModalImpression {
  id: number;
  promotional_modal_id: number;
  user_id: number | null;
  session_id: string | null;
  action: 'impression' | 'click' | 'dismiss';
  page_url: string | null;
  metadata: Record<string, any> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

export interface PromotionalModalAnalytics {
  total_impressions: number;
  total_clicks: number;
  total_dismissals: number;
  conversion_rate: number;
  dismissal_rate: number;
  daily_stats: Array<{
    date: string;
    impressions: number;
    clicks: number;
    dismissals: number;
  }>;
  top_pages: Array<{
    page_url: string;
    count: number;
  }>;
}

export interface SystemAnalytics {
  total_impressions: number;
  total_clicks: number;
  total_dismissals: number;
  conversion_rate: number;
  active_modals_count: number;
  total_modals_count: number;
  top_modals: Array<{
    id: number;
    title: string;
    impression_count: number;
    click_count: number;
    conversion_rate: number;
  }>;
}

export interface PromotionalModalFormData {
  title: Record<string, string>;
  content: Record<string, string>;
  type: 'modal' | 'banner';
  pages?: string[] | null;
  membership_levels?: number[] | null;
  user_segments?: string[] | null;
  start_at?: string | null;
  end_at?: string | null;
  display_frequency: 'once' | 'daily' | 'weekly' | 'always';
  cooldown_hours: number;
  is_active: boolean;
  priority: number;
  sort_order: number;
  button_text?: string;
  button_url?: string;
  is_dismissible: boolean;
  display_conditions?: Record<string, any> | null;
  banner_image?: File | null;
  background_image?: File | null;
}

// API Response types
export interface PromotionalModalApiResponse {
  data: PromotionalModalData[];
  meta: {
    count: number;
    page: string;
    type: string;
  };
}

export interface PromotionalModalPaginatedResponse {
  data: PromotionalModalData[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    filters?: Record<string, any>;
  };
}

export interface ImpressionResponse {
  message: string;
  data: {
    id: number;
    action: string;
    created_at: string;
  };
}

export interface BatchImpressionRequest {
  impressions: Array<{
    modal_id: number;
    action: 'impression' | 'click' | 'dismiss';
    page_url?: string;
    metadata?: Record<string, any>;
  }>;
}

export interface BatchImpressionResponse {
  message: string;
  data: {
    count: number;
    recorded_at: string;
  };
}