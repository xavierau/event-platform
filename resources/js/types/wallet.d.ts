export interface WalletBalance {
    points_balance: number;
    kill_points_balance: number;
    total_points_earned: number;
    total_points_spent: number;
    total_kill_points_earned: number;
    total_kill_points_spent: number;
}

export interface WalletTransaction {
    id: number | string; // Assuming ID can be number or string from DB/API
    transaction_type: string; // Corresponds to WalletTransactionType enum values
    amount: number;
    description: string;
    reference_type?: string | null;
    reference_id?: number | string | null;
    metadata?: Record<string, any> | null;
    created_at: string; // ISO date string
    // Optional relations, if eager-loaded
    user?: { id: number | string; name: string };
    wallet?: { id: number | string; user_id: number | string };
}

// Interface for paginated transaction data if you have a standard structure
export interface PaginatedWalletTransactions {
    data: WalletTransaction[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}
