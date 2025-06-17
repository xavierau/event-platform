<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Eye, Pencil, Trash2, CheckCircle, XCircle } from 'lucide-vue-next';
import Pagination from '@/components/Shared/Pagination.vue'; // Assuming you have a pagination component

// Define the shape of a CmsPage, adjust as needed based on your controller's data
interface CmsPage {
    id: number;
    title: Record<string, string>;
    slug: string;
    is_published: boolean;
    published_at: string | null;
    author: { name: string } | null;
    created_at: string;
}

interface Props {
    pages: {
        data: CmsPage[];
        links: any[]; // Pagination links
    };
    search?: string;
}

const props = defineProps<Props>();

const deletePage = (pageId: number) => {
    if (confirm('Are you sure you want to delete this CMS page?')) {
        router.delete(route('admin.cms-pages.destroy', pageId), {
            preserveScroll: true,
        });
    }
};

const togglePublish = (pageId: number) => {
    router.patch(route('admin.cms-pages.toggle-publish', pageId), {}, {
        preserveScroll: true,
    });
};

const getTranslation = (translations: any, locale: string, fallbackLocale: string = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

</script>

<template>
    <Head title="Manage CMS Pages" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <div class="flex justify-between items-center">
                            <CardTitle>CMS Pages</CardTitle>
                            <Link :href="route('admin.cms-pages.create')">
                                <Button>Create Page</Button>
                            </Link>
                        </div>
                        <!-- TODO: Add search input -->
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Slug</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Author</TableHead>
                                        <TableHead>Created At</TableHead>
                                        <TableHead class="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="!props.pages.data.length">
                                        <TableCell colspan="6" class="text-center">No CMS pages found.</TableCell>
                                    </TableRow>
                                    <TableRow v-for="page in props.pages.data" :key="page.id">
                                        <TableCell class="font-medium">
                                            {{ getTranslation(page.title, 'en') }}
                                        </TableCell>
                                        <TableCell>{{ page.slug }}</TableCell>
                                        <TableCell>
                                            <Badge @click="togglePublish(page.id)"
                                                   :variant="page.is_published ? 'success' : 'destructive'"
                                                   class="cursor-pointer">
                                                <component :is="page.is_published ? CheckCircle : XCircle" class="w-4 h-4 mr-1"/>
                                                {{ page.is_published ? 'Published' : 'Draft' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{{ page.author?.name || 'N/A' }}</TableCell>
                                        <TableCell>{{ new Date(page.created_at).toLocaleDateString() }}</TableCell>
                                        <TableCell class="text-right space-x-2">
                                            <Link :href="route('admin.cms-pages.show', page.id)">
                                                <Button variant="outline" size="icon"><Eye class="w-4 h-4" /></Button>
                                            </Link>
                                            <Link :href="route('admin.cms-pages.edit', page.id)">
                                                <Button variant="outline" size="icon"><Pencil class="w-4 h-4" /></Button>
                                            </Link>
                                            <Button @click="deletePage(page.id)" variant="destructive" size="icon">
                                                <Trash2 class="w-4 h-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination :links="props.pages.links" class="mt-6" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
