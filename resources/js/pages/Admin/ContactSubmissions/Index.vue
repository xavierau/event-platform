<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Pagination from '@/components/Shared/Pagination.vue';
import { ContactSubmission } from '@/types';
import { Button } from '@/components/ui/button';
import { Eye } from 'lucide-vue-next';

defineProps<{
    submissions: {
        data: ContactSubmission[];
        links: any[];
    };
}>();
</script>

<template>
    <Head title="Contact Submissions" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <div class="flex justify-between items-center">
                            <CardTitle>Contact Submissions</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Subject</TableHead>
                                    <TableHead>Submitted At</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="!submissions.data.length">
                                    <TableCell colspan="5" class="text-center">
                                        No contact submissions found.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="submission in submissions.data" :key="submission.id">
                                    <TableCell>{{ submission.name }}</TableCell>
                                    <TableCell>{{ submission.email }}</TableCell>
                                    <TableCell>{{ submission.subject }}</TableCell>
                                    <TableCell>{{ new Date(submission.created_at).toLocaleString() }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('admin.contact-submissions.show', submission.id)">
                                            <Button variant="outline" size="icon">
                                                <Eye class="w-4 h-4" />
                                            </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <Pagination class="mt-6" :links="submissions.links" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
