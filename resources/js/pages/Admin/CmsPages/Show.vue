<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Globe, Pencil, Languages } from 'lucide-vue-next';

interface CmsPage {
    id: number;
    title: Record<string, string>;
    slug: string;
    content: Record<string, string>;
    is_published: boolean;
    published_at: string | null;
    author: { name: string; email: string } | null;
    created_at: string;
    updated_at: string;
}

defineProps<{
    page: CmsPage;
}>();

const getTranslation = (translations: any, locale: string, fallbackLocale: string = 'en') => {
    if (!translations) return 'N/A';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || 'N/A';
};

</script>

<template>
    <Head :title="`View Page: ${getTranslation(page.title, 'en')}`" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <div class="flex justify-between items-start">
                            <div>
                                <CardTitle class="text-2xl mb-1">{{ getTranslation(page.title, 'en') }}</CardTitle>
                                <CardDescription class="flex items-center">
                                    <Globe class="w-4 h-4 mr-2" /> {{ page.slug }}
                                </CardDescription>
                            </div>
                            <Link :href="route('admin.cms-pages.edit', page.id)">
                                <Button variant="outline">
                                    <Pencil class="w-4 h-4 mr-2" /> Edit Page
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex space-x-4">
                            <Badge :variant="page.is_published ? 'success' : 'secondary'">
                                {{ page.is_published ? 'Published' : 'Draft' }}
                            </Badge>
                             <div v-if="page.published_at" class="text-sm text-gray-500">
                                Published on: {{ new Date(page.published_at).toLocaleString() }}
                            </div>
                        </div>

                        <div class="prose dark:prose-invert max-w-none p-4 border rounded-md">
                            <h3 class="flex items-center"><Languages class="w-5 h-5 mr-2" />Content (English)</h3>
                            <div v-html="getTranslation(page.content, 'en')"></div>
                        </div>

                        <div class="prose dark:prose-invert max-w-none p-4 border rounded-md bg-gray-50 dark:bg-gray-900">
                             <h3 class="flex items-center"><Languages class="w-5 h-5 mr-2" />Content (Traditional Chinese)</h3>
                             <div v-html="getTranslation(page.content, 'zh-TW')"></div>
                        </div>

                        <div class="text-sm text-gray-500 dark:text-gray-400">
                           <p>Author: {{ page.author?.name || 'N/A' }}</p>
                           <p>Created: {{ new Date(page.created_at).toLocaleString() }}</p>
                           <p>Last Updated: {{ new Date(page.updated_at).toLocaleString() }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
