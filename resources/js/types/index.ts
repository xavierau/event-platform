export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Promotion {
    id: number;
    title: string;
    subtitle: string;
    banner: string;
    url: string;
}

export interface EventItem {
    id: number;
    name: string;
    slug?: string;
    href: string;
    image_url: string;
    price_from: number | null;
    price_to?: number | null;
    currency: string;
    date_short?: string; // For upcoming events (e.g., "JUL 15")
    date_range?: string; // For more events (e.g., "2025.06.13-15")
    venue_name?: string | null;
    category_name?: string | null;
    start_time?: string; // For today's events (e.g., "14:30")
    // Additional fields from backend
    description?: string;
    event_status?: string;
    category?: {
        id: number;
        name: string;
        slug: string;
    };
    organizer?: {
        id: number;
        name: string;
    };
    venue?: {
        id: number;
        name: string;
    };
    created_at?: string;
    updated_at?: string;
}
