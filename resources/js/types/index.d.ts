import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export type Translatable = Record<string, string>;

export interface MediaItem {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    order_column?: number;
    original_url: string;
    preview_url?: string;
    thumbnail_url?: string;
    responsive_images?: any;
    custom_properties?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

export interface Country {
    id: number;
    name: Translatable | string;
}

export interface State {
    id: number;
    country_id: number;
    name: Translatable | string;
}

export interface VenueData {
    id?: number;
    name: Translatable;
    slug: string;
    description: Translatable;
    address_line_1: Translatable;
    address_line_2?: Translatable | null;
    city: Translatable;
    postal_code?: string | null;
    country_id: number | null;
    state_id?: number | null;
    latitude?: number | null;
    longitude?: number | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    website_url?: string | null;
    seating_capacity?: number | null;
    is_active: boolean;
    organizer_id?: number | null;

    uploaded_main_image?: File | null;
    existing_main_image?: MediaItem | null;
    removed_main_image_id?: number | null;

    uploaded_gallery_images?: File[];
    existing_gallery_images?: MediaItem[];
    removed_gallery_image_ids?: number[];

    media?: {
        main_image?: MediaItem[] | null;
        gallery_images?: MediaItem[] | null;
    };

    created_at?: string;
    updated_at?: string;
}

// Define a basic Event type for use in Booking
export interface Event {
    id: number;
    name: string;
    // Add other relevant event properties as needed
}

export interface Booking {
    id: number;
    event_id: number;
    user_id: number;
    status: string; // Consider using an enum for status if applicable
    quantity: number;
    price_per_unit: number;
    total_price: number;
    currency: string;
    created_at?: string;
    updated_at?: string;
    event?: Event; // Optional: for eager loaded event data
    user?: User; // Optional: for eager loaded user data
}
