import type { User } from '@/types';

export interface Comment {
    id: number;
    user_id: number;
    commentable_type: string;
    commentable_id: number;
    content: string;
    content_type: 'plain' | 'rich';
    status: 'pending' | 'approved' | 'rejected' | 'flagged';
    parent_id: number | null;
    votes_enabled: boolean;
    votes_up_count: number;
    votes_down_count: number;
    created_at: string;
    updated_at: string;
    user: User;
    replies?: Comment[];
    user_vote?: CommentVote | null;
}

export interface CommentVote {
    id: number;
    user_id: number;
    comment_id: number;
    vote_type: 'up' | 'down';
    created_at: string;
    updated_at: string;
}

export interface CommentFormData {
    commentable_type: string;
    commentable_id: number;
    content: string;
    content_type?: 'plain' | 'rich';
    parent_id?: number | null;
}
