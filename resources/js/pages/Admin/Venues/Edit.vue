<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AppLayout.vue';
import { VenueData, Country, State } from '@/types'; // Assuming these types exist
import RichTextEditor from '@/Components/Form/RichTextEditor.vue'; // Import the new component

interface Props {
    venue: VenueData;
    countries: Country[]; // For select dropdown, to be passed from controller
    states: State[];    // For select dropdown, to be passed from controller
}

const props = defineProps<Props>();

// Ensure all fields from VenueData are destructured and have defaults if not present in props.venue
// Translatable fields from DTO are arrays, but form expects objects with locales.
// The controller should ensure VenueData provides these in the correct format for the form if possible,
// or we transform here.
// For simplicity, assuming props.venue already has translatable fields as objects e.g. {en: '...'}
const form = useForm<VenueData>({
    id: props.venue.id,
    name: props.venue.name || { en: '', 'zh-TW': '', 'zh-CN': '' },
    slug: props.venue.slug || '',
    description: props.venue.description || { en: '', 'zh-TW': '', 'zh-CN': '' },
    address_line_1: props.venue.address_line_1 || { en: '', 'zh-TW': '', 'zh-CN': '' },
    address_line_2: props.venue.address_line_2 || { en: '', 'zh-TW': '', 'zh-CN': '' }, // Optional field
    city: props.venue.city || { en: '', 'zh-TW': '', 'zh-CN': '' },
    postal_code: props.venue.postal_code || '',
    country_id: props.venue.country_id || null,
    state_id: props.venue.state_id || null,
    latitude: props.venue.latitude || null,
    longitude: props.venue.longitude || null,
    contact_email: props.venue.contact_email || '',
    contact_phone: props.venue.contact_phone || '',
    website_url: props.venue.website_url || '',
    seating_capacity: props.venue.seating_capacity || null,
    is_active: props.venue.is_active === undefined ? true : props.venue.is_active, // Default to true if undefined
    organizer_id: props.venue.organizer_id || null,
    images: props.venue.images || null, // Keep existing images data if any
    thumbnail_image_path: props.venue.thumbnail_image_path || null,
});

const submit = () => {
    if (props.venue.id) {
        form.put(route('admin.venues.update', props.venue.id), {
            // onSuccess: () => { /* Notification or redirect */ },
        });
    }
};

// Helper to get a specific translation - TODO: Centralize this
const getTranslation = (translations: any, locale: string = 'en', fallbackLocale: string = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

// Dummy data for dropdowns - replace with actual props.countries and props.states
const dummyCountries = [
    { id: 1, name: { en: 'Hong Kong SAR China' } },
    { id: 2, name: { en: 'Macau SAR China' } },
];
const dummyStates = [
    { id: 1, country_id: 1, name: { en: 'Hong Kong Island' } },
    { id: 2, country_id: 1, name: { en: 'Kowloon' } },
];


</script>

<template>
    <Head :title="`Edit Venue: ${getTranslation(form.name)}`" />

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

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Country -->
                                    <div>
                                        <label for="country_id" class="label">Country</label>
                                        <select v-model="form.country_id" id="country_id" class="mt-1 block w-full input">
                                            <option :value="null">-- Select Country --</option>
                                            <option v-for="country in (props.countries || dummyCountries)" :key="country.id" :value="country.id">
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
                                            <option v-for="state in (props.states || dummyStates).filter(s => s.country_id === form.country_id)" :key="state.id" :value="state.id">
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
                                <!-- TODO: Add map integration here -->


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


                                <!-- TODO: Add Image Upload Section -->
                                <div v-if="form.images">
                                    <p class="label">Current Images:</p>
                                    <!-- Display current images - needs proper handling based on 'images' structure -->
                                    <pre class="text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded">{{ form.images }}</pre>
                                </div>
                                <div v-if="form.thumbnail_image_path">
                                    <p class="label">Current Thumbnail:</p>
                                    <img :src="form.thumbnail_image_path" alt="Thumbnail" class="w-32 h-32 object-cover rounded" /> <!-- Adjust path if it's not a direct URL -->
                                </div>


                                <!-- Is Active -->
                                <div class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" id="is_active" class="h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded" />
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Active</label>
                                </div>
                                <div v-if="form.errors.is_active" class="input-error">{{ form.errors.is_active }}</div>

                            </div>

                            <div class="mt-8 flex justify-end space-x-3">
                                <Link :href="route('admin.venues.index')" class="btn btn-secondary">Cancel</Link>
                                <button type="submit" :disabled="form.processing"
                                        class="btn btn-primary"
                                        :class="{ 'opacity-25': form.processing }">
                                    Update Venue
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


