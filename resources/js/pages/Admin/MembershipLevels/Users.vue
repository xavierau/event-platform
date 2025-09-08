<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AppLayout.vue';
import { ArrowLeft, ExternalLink } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

interface UserMembership {
    id: number;
    user: User;
    status: string;
    started_at: string;
    expires_at: string | null;
    payment_method: string;
    auto_renew: boolean;
    stripe_subscription_id: string | null;
    subscription_metadata: Record<string, any> | null;
}

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    slug: string;
    price_formatted: string;
}

interface PaginationData {
    data: UserMembership[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    membershipLevel: MembershipLevel;
    users: PaginationData;
}>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const getStatusBadge = (status: string) => {
    const variants = {
        active: 'default',
        cancelled: 'secondary', 
        expired: 'destructive',
        pending: 'outline'
    } as const;
    
    return variants[status as keyof typeof variants] || 'secondary';
};

const getLevelName = (level: MembershipLevel) => {
    return level.name.en || level.slug;
};
</script>

<template>
    <AdminLayout>
        <Head :title="`${getLevelName(membershipLevel)} Users`" />
        
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" :href="route('admin.membership-levels.show', membershipLevel.id)" class="gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Back to {{ getLevelName(membershipLevel) }}
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold">{{ getLevelName(membershipLevel) }} Users</h1>
                        <p class="text-muted-foreground">
                            {{ users.total.toLocaleString() }} users subscribed to {{ membershipLevel.price_formatted }}/month plan
                        </p>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Subscribers</CardTitle>
                    <CardDescription>
                        Users currently or previously subscribed to this membership level
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="users.data.length === 0" class="text-center py-12">
                        <div class="text-muted-foreground">
                            <h3 class="text-lg font-medium">No subscribers yet</h3>
                            <p class="text-sm">Users who subscribe to this plan will appear here.</p>
                        </div>
                    </div>
                    
                    <div v-else>
                        <!-- Summary -->
                        <div class="mb-4 text-sm text-muted-foreground">
                            Showing {{ users.from?.toLocaleString() || 0 }} to {{ users.to?.toLocaleString() || 0 }} 
                            of {{ users.total.toLocaleString() }} users
                        </div>

                        <!-- Table -->
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Started</TableHead>
                                        <TableHead>Expires</TableHead>
                                        <TableHead>Payment Method</TableHead>
                                        <TableHead>Auto Renew</TableHead>
                                        <TableHead>Stripe ID</TableHead>
                                        <TableHead class="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="membership in users.data" :key="membership.id">
                                        <TableCell>
                                            <div>
                                                <div class="font-medium">{{ membership.user.name }}</div>
                                                <div class="text-sm text-muted-foreground">{{ membership.user.email }}</div>
                                                <div class="text-xs text-muted-foreground">
                                                    Joined {{ formatDate(membership.user.created_at) }}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="getStatusBadge(membership.status)">
                                                {{ membership.status }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{{ formatDate(membership.started_at) }}</TableCell>
                                        <TableCell>
                                            <span v-if="membership.expires_at">{{ formatDate(membership.expires_at) }}</span>
                                            <span v-else class="text-muted-foreground">Never</span>
                                        </TableCell>
                                        <TableCell>
                                            <span class="capitalize">{{ membership.payment_method }}</span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="membership.auto_renew ? 'default' : 'secondary'" class="text-xs">
                                                {{ membership.auto_renew ? 'Yes' : 'No' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <code v-if="membership.stripe_subscription_id" class="text-xs bg-muted px-2 py-1 rounded">
                                                {{ membership.stripe_subscription_id }}
                                            </code>
                                            <span v-else class="text-xs text-muted-foreground">N/A</span>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                :href="route('admin.users.show', membership.user.id)"
                                                class="gap-1"
                                            >
                                                View
                                                <ExternalLink class="h-3 w-3" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="users.last_page > 1" class="mt-6">
                            <nav class="flex items-center justify-center">
                                <div class="flex items-center gap-2">
                                    <template v-for="link in users.links" :key="link.label">
                                        <Button
                                            v-if="link.url"
                                            variant="outline"
                                            size="sm"
                                            :class="{ 'bg-primary text-primary-foreground': link.active }"
                                            :href="link.url"
                                            v-html="link.label"
                                        />
                                        <span
                                            v-else
                                            class="px-3 py-2 text-sm text-muted-foreground"
                                            v-html="link.label"
                                        />
                                    </template>
                                </div>
                            </nav>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>