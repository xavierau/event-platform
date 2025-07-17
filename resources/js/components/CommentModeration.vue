<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { Event } from '@/types/index.d';

interface User {
    id: number;
    name: string;
}

interface Comment {
    id: number;
    content: string;
    status: 'pending' | 'approved' | 'rejected';
    created_at: string;
    user: User;
}

const props = defineProps<{
    event: Event;
}>();

const comments = ref<Comment[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const fetchComments = async () => {
    loading.value = true;
    error.value = null;
    try {
        // Use Inertia visit to fetch moderation comments from web route
        // (Assume a web route exists for moderation, e.g. /admin/events/{event}/comments/moderation)
        // For now, fallback to API route for fetching, but all actions will use Inertia forms
        const response = await fetch(`/admin/events/${props.event.id}/comments/moderation`);
        const data = await response.json();
        comments.value = data.data || data;
    } catch (err) {
        error.value = 'Failed to load comments.';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const approveForm = useForm({});
const rejectForm = useForm({});
const deleteForm = useForm({});

const approveComment = (comment: Comment) => {
    console.log('approveComment', comment);
    approveForm.post(`/admin/comments/${comment.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            comment.status = 'approved';
        },
        onError: (errors) => {
            console.error('Failed to approve comment', errors);
        },
    });
};

const rejectComment = (comment: Comment) => {
    rejectForm.put(`/admin/comments/${comment.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            comment.status = 'rejected';
        },
        onError: (errors) => {
            console.error('Failed to reject comment', errors);
        },
    });
};

const deleteComment = (comment: Comment) => {
    if (confirm('Are you sure you want to delete this comment?')) {
        deleteForm.delete(`/admin/comments/${comment.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                comments.value = comments.value.filter(c => c.id !== comment.id);
            },
            onError: (errors) => {
                console.error('Failed to delete comment', errors);
            },
        });
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};

const statusClass = (status: string) => {
    switch (status) {
        case 'approved': return 'bg-green-50 border-green-200';
        case 'rejected': return 'bg-yellow-50 border-yellow-200';
        case 'pending': return 'bg-gray-50 border-gray-200';
        default: return 'bg-white border-gray-200';
    }
};

const statusPillClass = (status: string) => {
    switch (status) {
        case 'approved': return 'bg-green-100 text-green-800';
        case 'rejected': return 'bg-yellow-100 text-yellow-800';
        case 'pending': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

onMounted(() => {
    fetchComments();
});
</script>


<template>
    <div>
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Comment Moderation</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Approve, reject, or delete comments for this event.</p>

        <div class="mt-6">
            <div v-if="loading" class="text-center">Loading comments...</div>
            <div v-if="error" class="text-center text-red-500">{{ error }}</div>
            <div v-if="comments.length === 0 && !loading" class="text-center text-gray-500">No comments yet.</div>

            <div v-else class="space-y-4">
                <div v-for="comment in comments" :key="comment.id" class="p-4 border rounded-lg bg-white dark:bg-gray-900" :class="statusClass(comment.status)">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold">{{ comment.user.name }}</p>
                            <p class="text-sm text-gray-600">{{ comment.content }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ formatDate(comment.created_at) }}</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="statusPillClass(comment.status)">
                                {{ comment.status }}
                            </span>
                            <button  @click.prevent="approveComment(comment)" v-if="comment.status !== 'approved'" class="text-green-600 hover:text-green-900">Approve</button>
                            <button  @click.prevent="rejectComment(comment)" v-if="comment.status !== 'rejected'" class="text-yellow-600 hover:text-yellow-900">Reject</button>
                            <button  @click.prevent="deleteComment(comment)" class="text-red-600 hover:text-red-900">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
