import type { User } from '@/types';

export interface Comment {
    id: number;
    content: string;
    status: 'PENDING' | 'APPROVED' | 'REJECTED';
    created_at: string;
    updated_at: string;
    user: User;
    parent_id: number | null;
    replies?: Comment[];
}
