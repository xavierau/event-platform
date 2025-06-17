<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ContactSubmission } from '@/types';

defineProps<{
    submission: ContactSubmission;
}>();
</script>

<template>
    <Head :title="`Submission from ${submission.name}`" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Contact Submission</CardTitle>
                        <CardDescription>
                            From: {{ submission.name }} ({{ submission.email }}) on {{ new Date(submission.created_at).toLocaleString() }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-lg">Subject</h3>
                                <p>{{ submission.subject }}</p>
                            </div>
                            <div class="prose max-w-none">
                                <h3 class="font-semibold text-lg">Message</h3>
                                <p>{{ submission.message }}</p>
                            </div>
                        </div>
                    </CardContent>
                    <div class="p-6 border-t">
                         <Link :href="route('admin.contact-submissions.index')">
                            <Button variant="outline">Back to Submissions</Button>
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
