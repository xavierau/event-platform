<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AppLayout.vue';
import { Country, State } from '@/types'; // Assuming these types exist for props
import RichTextEditor from '@/components/Form/RichTextEditor.vue'; // Import the new component
import MediaUpload from '@/components/Form/MediaUpload.vue'; // Import MediaUpload

// TODO: Define props for countries and states if they will be passed from controller
interface Props {
    countries: Country[];
    states: State[]; // Or make this dependent on selected country
}
// const props = defineProps<Props>(); // Uncomment when ready to pass props

const form = useForm({
    name: { en: '', 'zh-TW': '', 'zh-CN': '' },
    slug: '',
    description: { en: '', 'zh-TW': '', 'zh-CN': '' }, // This will now hold HTML strings
    address_line_1: { en: '', 'zh-TW': '', 'zh-CN': '' },
    address_line_2: { en: '', 'zh-TW': '', 'zh-CN': '' },
    city: { en: '', 'zh-TW': '', 'zh-CN': '' },
    postal_code: '',
    country_id: null as number | null,
    state_id: null as number | null,
    latitude: null as number | null,
    longitude: null as number | null,
    contact_email: '',
    contact_phone: '',
    website_url: '',
    seating_capacity: null as number | null,
    is_active: true,
    organizer_id: null as number | null, // Assuming this might be set automatically or passed

    // Media fields
    uploaded_main_image: null as File | null,
    uploaded_gallery_images: [] as File[],
});

const submit = () => {
    // Ensure media fields are correctly prepared for submission if they are optional
    // and might not be part of the initial form data sent by useForm by default
    // if they are null/empty.
    // However, Inertia's useForm typically sends all defined fields.
    // We might need to explicitly set them to undefined if the backend expects them to be absent
    // when no files are uploaded, rather than null or empty array.
    // For now, let's assume sending null/empty array is fine.
    const dataToSubmit = {
        ...form.data(),
        uploaded_main_image: form.uploaded_main_image || undefined,
        uploaded_gallery_images: form.uploaded_gallery_images.length > 0 ? form.uploaded_gallery_images : undefined,
    };

    form.transform(data => dataToSubmit) // Use transform to ensure these are definitely part of the payload
        .post(route('admin.venues.store'), {
        // onSuccess: () => form.reset(), // Consider resetting form, including file inputs
    });
};

// Dummy data for dropdowns - replace with props later
const dummyCountries = [
    { id: 1, name: { en: 'Hong Kong SAR China' } },
    { id: 2, name: { en: 'Macau SAR China' } },
];
const dummyStates = [ // Filter these by country_id in a real scenario
    { id: 1, country_id: 1, name: { en: 'Hong Kong Island' } },
    { id: 2, country_id: 1, name: { en: 'Kowloon' } },
];

// Helper to get a specific translation for dropdowns, adjust as needed
// TODO: Centralize this helper
const getTranslation = (translations: any, locale: string = 'en', fallbackLocale: string = 'en') => {
    if (!translations) return 'N/A';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || 'Unnamed';
};

</script>

<template>
    <Head title="Create Venue" />

    <AuthenticatedLayout>
        <!-- The pageTitle and breadcrumbs are now handled by AppLayout via props passed from the controller -->
        <!-- The #header slot below is removed as AppLayout/AppSidebarHeader handles the title -->

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">

                                <!-- Translatable Name -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Venue Name</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="name_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.name.en" id="name_en" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="name_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.name['zh-TW']" id="name_zh_TW" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="name_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.name['zh-CN']" id="name_zh_CN" class="mt-1 block w-full input-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.name" class="input-error">{{ form.errors.name }}</div>
                                </fieldset>

                                <!-- Slug -->
                                <div>
                                    <label for="slug" class="label">Slug</label>
                                    <input type="text" v-model="form.slug" id="slug" class="mt-1 block w-full input" />
                                    <div v-if="form.errors.slug" class="input-error">{{ form.errors.slug }}</div>
                                </div>

                                 <!-- Translatable Description -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Description</legend>
                                    <div class="space-y-4 mt-2">
                                        <div>
                                            <label for="description_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">English</label>
                                            <RichTextEditor v-model="form.description.en" id="description_en" />
                                        </div>
                                        <div>
                                            <label for="description_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Traditional Chinese</label>
                                            <RichTextEditor v-model="form.description['zh-TW']" id="description_zh_TW" />
                                        </div>
                                        <div>
                                            <label for="description_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Simplified Chinese</label>
                                            <RichTextEditor v-model="form.description['zh-CN']" id="description_zh_CN" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.description" class="input-error">{{ form.errors.description }}</div>
                                </fieldset>

                                <MediaUpload
                                    v-model="form.uploaded_main_image"
                                    collectionName="main_image"
                                    label="Main Image"
                                    :multiple="false"
                                />
                                <div v-if="form.errors.uploaded_main_image" class="input-error mt-1">{{ form.errors.uploaded_main_image }}</div>

                                <MediaUpload
                                    v-model="form.uploaded_gallery_images"
                                    collectionName="gallery_images"
                                    label="Gallery Images"
                                    :multiple="true"
                                    :maxFiles="10"
                                />
                                <div v-if="form.errors.uploaded_gallery_images" class="input-error mt-1">{{ form.errors.uploaded_gallery_images }}</div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Country -->
                                    <div>
                                        <label for="country_id" class="label">Country</label>
                                        <select v-model="form.country_id" id="country_id" class="mt-1 block w-full input">
                                            <option :value="null">-- Select Country --</option>
                                            <option v-for="country in dummyCountries" :key="country.id" :value="country.id">
                                                {{ getTranslation(country.name) }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.country_id" class="input-error">{{ form.errors.country_id }}</div>
                                    </div>

                                    <!-- State/Province -->
                                    <div>
                                        <label for="state_id" class="label">State/Province</label>
                                        <select v-model="form.state_id" id="state_id" class="mt-1 block w-full input" :disabled="!form.country_id">
                                            <option :value="null">-- Select State/Province --</option>
                                            <!-- TODO: Filter states based on selected country_id -->
                                            <option v-for="state in dummyStates.filter(s => s.country_id === form.country_id)" :key="state.id" :value="state.id">
                                                 {{ getTranslation(state.name) }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.state_id" class="input-error">{{ form.errors.state_id }}</div>
                                    </div>
                                </div>

                                <!-- Translatable Address Line 1 -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Address Line 1</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="address_line_1_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.address_line_1.en" id="address_line_1_en" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_1_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.address_line_1['zh-TW']" id="address_line_1_zh_TW" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_1_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.address_line_1['zh-CN']" id="address_line_1_zh_CN" class="mt-1 block w-full input-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.address_line_1" class="input-error">{{ form.errors.address_line_1 }}</div>
                                </fieldset>

                                <!-- Translatable Address Line 2 -->
                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Address Line 2 (Optional)</legend>
                                     <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="address_line_2_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.address_line_2.en" id="address_line_2_en" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_2_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.address_line_2['zh-TW']" id="address_line_2_zh_TW" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_2_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.address_line_2['zh-CN']" id="address_line_2_zh_CN" class="mt-1 block w-full input-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.address_line_2" class="input-error">{{ form.errors.address_line_2 }}</div>
                                </fieldset>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Translatable City -->
                                    <fieldset class="border dark:border-gray-700 p-4 rounded-md md:col-span-2">
                                        <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">City</legend>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                            <div>
                                                <label for="city_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                                <input type="text" v-model="form.city.en" id="city_en" class="mt-1 block w-full input-sm" />
                                            </div>
                                            <div>
                                                <label for="city_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                                <input type="text" v-model="form.city['zh-TW']" id="city_zh_TW" class="mt-1 block w-full input-sm" />
                                            </div>
                                            <div>
                                                <label for="city_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                                <input type="text" v-model="form.city['zh-CN']" id="city_zh_CN" class="mt-1 block w-full input-sm" />
                                            </div>
                                        </div>
                                        <div v-if="form.errors.city" class="input-error">{{ form.errors.city }}</div>
                                    </fieldset>

                                    <!-- Postal Code -->
                                    <div>
                                        <label for="postal_code" class="label">Postal Code</label>
                                        <input type="text" v-model="form.postal_code" id="postal_code" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.postal_code" class="input-error">{{ form.errors.postal_code }}</div>
                                    </div>

                                     <!-- Seating Capacity -->
                                    <div>
                                        <label for="seating_capacity" class="label">Seating Capacity</label>
                                        <input type="number" v-model.number="form.seating_capacity" id="seating_capacity" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.seating_capacity" class="input-error">{{ form.errors.seating_capacity }}</div>
                                    </div>
                                </div>


                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Latitude -->
                                    <div>
                                        <label for="latitude" class="label">Latitude</label>
                                        <input type="number" step="any" v-model.number="form.latitude" id="latitude" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.latitude" class="input-error">{{ form.errors.latitude }}</div>
                                    </div>

                                    <!-- Longitude -->
                                    <div>
                                        <label for="longitude" class="label">Longitude</label>
                                        <input type="number" step="any" v-model.number="form.longitude" id="longitude" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.longitude" class="input-error">{{ form.errors.longitude }}</div>
                                    </div>
                                </div>
                                <!-- TODO: Add map integration here to auto-fill lat/long or pick from map -->


                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Contact Email -->
                                    <div>
                                        <label for="contact_email" class="label">Contact Email</label>
                                        <input type="email" v-model="form.contact_email" id="contact_email" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.contact_email" class="input-error">{{ form.errors.contact_email }}</div>
                                    </div>

                                    <!-- Contact Phone -->
                                    <div>
                                        <label for="contact_phone" class="label">Contact Phone</label>
                                        <input type="tel" v-model="form.contact_phone" id="contact_phone" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.contact_phone" class="input-error">{{ form.errors.contact_phone }}</div>
                                    </div>
                                </div>

                                <!-- Website URL -->
                                <div>
                                    <label for="website_url" class="label">Website URL</label>
                                    <input type="url" v-model="form.website_url" id="website_url" class="mt-1 block w-full input" placeholder="https://example.com" />
                                    <div v-if="form.errors.website_url" class="input-error">{{ form.errors.website_url }}</div>
                                </div>


                                <!-- Is Active -->
                                <div class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" id="is_active" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" />
                                    <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</label>
                                </div>
                                <div v-if="form.errors.is_active" class="input-error">{{ form.errors.is_active }}</div>

                                <!-- Organizer ID (hidden or display only if needed) -->
                                <!-- <input type="hidden" v-model="form.organizer_id" /> -->
                                <div v-if="form.errors.organizer_id" class="input-error">Organizer ID Error: {{ form.errors.organizer_id }}</div>


                                <div class="flex items-center justify-end gap-4 mt-8">
                                    <Link :href="route('admin.venues.index')" class="btn btn-secondary">Cancel</Link>
                                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Create Venue</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
