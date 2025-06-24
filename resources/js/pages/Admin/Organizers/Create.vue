<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import RichTextEditor from '@/components/Form/RichTextEditor.vue';
import { ref, computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PageHeader from '@/components/Shared/PageHeader.vue';

// Interfaces based on data from OrganizerController
interface Country {
    id: number;
    name: Record<string, string>;
}

interface State {
    id: number;
    country_id: number;
    name: Record<string, string>;
}

interface OrganizerFormData {
    name: Record<string, string>;
    slug: string;
    description: Record<string, string>;
    contact_email: string;
    contact_phone: string;
    website_url: string;
    social_media_links: {
        facebook: string;
        twitter: string;
        instagram: string;
        linkedin: string;
        youtube: string;
    };
    address_line_1: string;
    address_line_2: string;
    city: string;
    state_id: number | null;
    postal_code: string;
    country_id: number | null;
    is_active: boolean;
    logo_upload: File | null;
    // contract_details might be complex, handle as simple text for now
    [key: string]: any;
}

const props = defineProps<{
    countries: Country[];
    states: State[];
    errors: Record<string, string>;
}>();

const locales = [
    { code: 'en', name: 'English' },
    { code: 'zh-TW', name: 'Traditional Chinese' },
    { code: 'zh-CN', name: 'Simplified Chinese' }
];

const form = useForm<OrganizerFormData>({
    name: { en: '', 'zh-TW': '', 'zh-CN': '' },
    slug: '',
    description: { en: '', 'zh-TW': '', 'zh-CN': '' },
    contact_email: '',
    contact_phone: '',
    website_url: '',
    social_media_links: {
        facebook: '',
        twitter: '',
        instagram: '',
        linkedin: '',
        youtube: '',
    },
    address_line_1: '',
    address_line_2: '',
    city: '',
    state_id: null,
    postal_code: '',
    country_id: null,
    is_active: true,
    logo_upload: null,
});

const currentTab = ref('details');
const currentLocale = ref('en');

const tabs = [
    { id: 'details', label: 'Details' },
    { id: 'address', label: 'Address' },
    { id: 'contact', label: 'Contact & Social' },
    { id: 'media', label: 'Media' },
];

const filteredStates = computed(() => {
    if (!form.country_id) {
        return [];
    }
    return props.states.filter(state => state.country_id === form.country_id);
});

function handleFileSelect(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.logo_upload = target.files[0];
    }
}

function submit() {
    form.post(route('admin.organizers.store'));
}
</script>

<template>
    <Head title="Create Organizer" />
    <AppLayout>
        <template #header>
            <PageHeader :title="'Create New Organizer'" :breadcrumbs="[
                { name: 'Admin', route: 'admin.dashboard' },
                { name: 'Organizers', route: 'admin.organizers.index' },
                { name: 'Create' }
            ]" />
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>New Organizer</CardTitle>
                            <CardDescription>Fill out the form to create a new organizer.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="border-b border-gray-200 mb-6">
                                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                    <button v-for="tab in tabs" :key="tab.id" type="button" @click="currentTab = tab.id"
                                        :class="[currentTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                                        {{ tab.label }}
                                    </button>
                                </nav>
                            </div>

                            <!-- Translatable Fields Tabs -->
                            <div class="mb-4 border-b border-gray-200">
                                <nav class="-mb-px flex space-x-4" aria-label="Languages">
                                    <button v-for="locale in locales" :key="locale.code" type="button" @click="currentLocale = locale.code"
                                        :class="[currentLocale === locale.code ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap pb-2 px-1 border-b-2 font-medium text-sm']">
                                        {{ locale.name }}
                                    </button>
                                </nav>
                            </div>

                            <!-- Details Tab -->
                            <div v-show="currentTab === 'details'" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <Label :for="`name-${currentLocale}`">Name ({{ currentLocale.toUpperCase() }})</Label>
                                        <Input v-model="form.name[currentLocale]" :id="`name-${currentLocale}`" type="text" />
                                        <div v-if="errors[`name.${currentLocale}`]" class="text-red-500 text-sm mt-1">{{ errors[`name.${currentLocale}`] }}</div>
                                    </div>
                                    <div>
                                        <Label for="slug">Slug</Label>
                                        <Input v-model="form.slug" id="slug" type="text" />
                                        <div v-if="errors.slug" class="text-red-500 text-sm mt-1">{{ errors.slug }}</div>
                                    </div>
                                </div>
                                <div>
                                    <Label :for="`description-${currentLocale}`">Description ({{ currentLocale.toUpperCase() }})</Label>
                                    <RichTextEditor v-model="form.description[currentLocale]" />
                                    <div v-if="errors[`description.${currentLocale}`]" class="text-red-500 text-sm mt-1">{{ errors[`description.${currentLocale}`] }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <Switch id="is_active" v-model:checked="form.is_active" />
                                    <Label for="is_active">Active</Label>
                                </div>
                            </div>

                            <!-- Address Tab -->
                            <div v-show="currentTab === 'address'" class="space-y-6">
                               <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <Label for="address_line_1">Address Line 1</Label>
                                        <Input v-model="form.address_line_1" id="address_line_1" type="text" />
                                    </div>
                                     <div>
                                        <Label for="address_line_2">Address Line 2</Label>
                                        <Input v-model="form.address_line_2" id="address_line_2" type="text" />
                                    </div>
                                    <div>
                                        <Label for="city">City</Label>
                                        <Input v-model="form.city" id="city" type="text" />
                                    </div>
                                    <div>
                                        <Label for="postal_code">Postal Code</Label>
                                        <Input v-model="form.postal_code" id="postal_code" type="text" />
                                    </div>
                                    <div>
                                        <Label for="country_id">Country</Label>
                                        <Select v-model="form.country_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a country" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="country in countries" :key="country.id" :value="country.id">
                                                    {{ country.name[currentLocale] || country.name.en }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label for="state_id">State</Label>
                                         <Select v-model="form.state_id" :disabled="!form.country_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a state" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="state in filteredStates" :key="state.id" :value="state.id">
                                                    {{ state.name[currentLocale] || state.name.en }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                               </div>
                            </div>

                            <!-- Contact & Social Tab -->
                            <div v-show="currentTab === 'contact'" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <Label for="contact_email">Contact Email</Label>
                                        <Input v-model="form.contact_email" id="contact_email" type="email" />
                                    </div>
                                    <div>
                                        <Label for="contact_phone">Contact Phone</Label>
                                        <Input v-model="form.contact_phone" id="contact_phone" type="text" />
                                    </div>
                                    <div>
                                        <Label for="website_url">Website URL</Label>
                                        <Input v-model="form.website_url" id="website_url" type="url" />
                                    </div>
                                </div>
                                <h4 class="text-md font-medium">Social Media</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <Label for="social_facebook">Facebook</Label>
                                        <Input v-model="form.social_media_links.facebook" id="social_facebook" type="url" />
                                    </div>
                                    <div>
                                        <Label for="social_twitter">Twitter</Label>
                                        <Input v-model="form.social_media_links.twitter" id="social_twitter" type="url" />
                                    </div>
                                    <div>
                                        <Label for="social_instagram">Instagram</Label>
                                        <Input v-model="form.social_media_links.instagram" id="social_instagram" type="url" />
                                    </div>
                                     <div>
                                        <Label for="social_linkedin">LinkedIn</Label>
                                        <Input v-model="form.social_media_links.linkedin" id="social_linkedin" type="url" />
                                    </div>
                                     <div>
                                        <Label for="social_youtube">YouTube</Label>
                                        <Input v-model="form.social_media_links.youtube" id="social_youtube" type="url" />
                                    </div>
                                </div>
                            </div>

                            <!-- Media Tab -->
                            <div v-show="currentTab === 'media'" class="space-y-6">
                                <div>
                                    <Label for="logo_upload">Logo</Label>
                                    <Input id="logo_upload" type="file" @change="handleFileSelect" />
                                    <div v-if="errors.logo_upload" class="text-red-500 text-sm mt-1">{{ errors.logo_upload }}</div>
                                </div>
                            </div>
                        </CardContent>
                        <div class="px-6 py-4 border-t">
                            <Button :disabled="form.processing" type="submit">
                                Create Organizer
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>
        </div>
    </AppLayout>
</template>