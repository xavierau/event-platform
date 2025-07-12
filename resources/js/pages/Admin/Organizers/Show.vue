<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Globe, Mail, Phone, ExternalLink, UserPlus } from 'lucide-vue-next';
import { getTranslation } from '@/Utils/i18n';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

const { t } = useI18n();

const props = defineProps<{
    organizer: any;
}>();

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-TW' | 'zh-CN');

const isInviteUserModalOpen = ref(false);

const organizerRoles = [
    { value: 'owner', label: 'Owner' },
    { value: 'manager', label: 'Manager' },
    { value: 'staff', label: 'Staff' },
    { value: 'viewer', label: 'Viewer' },
];

const form = useForm({
    email: '',
    role_in_organizer: 'staff',
});

const submitInvite = () => {
    form.post(route('admin.organizers.invite', props.organizer.id), {
        onSuccess: () => {
            isInviteUserModalOpen.value = false;
            form.reset();
        },
    });
};

const organizerName = computed(() => {
    return getTranslation(props.organizer.name, currentLocale.value);
});

const organizerDescription = computed(() => {
    return getTranslation(props.organizer.description, currentLocale.value);
});

const fullAddress = computed(() => {
    const countryName = props.organizer.country?.name ? getTranslation(props.organizer.country.name, currentLocale.value) : '';
    const stateName = props.organizer.state?.name ? getTranslation(props.organizer.state.name, currentLocale.value) : '';
    const parts = [
        props.organizer.address_line_1,
        props.organizer.address_line_2,
        props.organizer.city,
        stateName,
        props.organizer.postal_code,
        countryName,
    ];
    return parts.filter(Boolean).join(', ');
});

const socialLinks = computed(() => {
    return Object.entries(props.organizer.social_media_links || {}).filter(
        ([, url]) => typeof url === 'string' && url.length > 0
    );
});

</script>

<template>
    <Head :title="t('organizers.show_title')" />
    <AppLayout>
        <div class="container mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <PageHeader
                :title="organizerName"
                :subtitle="t('organizers.show_subtitle')"
            >
                 <Link :href="route('admin.organizers.edit', organizer.id)" class="btn btn-primary">
                    {{ t('actions.edit') }}
                </Link>
            </PageHeader>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Left Column -->
                <div class="md:col-span-1 space-y-8">
                     <Card>
                        <CardHeader>
                            <CardTitle>{{ t('organizers.organizer_details') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex justify-center">
                                <img v-if="organizer.logo_url" :src="organizer.logo_url" :alt="organizerName" class="h-32 w-32 rounded-full object-cover border-4 border-gray-200">
                                <div v-else class="h-32 w-32 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-4xl font-bold">
                                    {{ (organizerName || '').charAt(0) }}
                                </div>
                            </div>

                             <div class="text-center">
                                <h2 class="text-2xl font-bold">{{ organizerName }}</h2>
                                <p class="text-muted-foreground">{{ organizer.slug }}</p>
                            </div>

                            <div class="flex justify-center">
                                <Badge :variant="organizer.is_active ? 'success' : 'destructive'">
                                    {{ organizer.is_active ? t('status.active') : t('status.inactive') }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('organizers.contact_info') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                             <div v-if="organizer.contact_email" class="flex items-center space-x-3">
                                <Mail class="h-5 w-5 text-muted-foreground" />
                                <a :href="`mailto:${organizer.contact_email}`" class="text-primary hover:underline">{{ organizer.contact_email }}</a>
                            </div>
                             <div v-if="organizer.contact_phone" class="flex items-center space-x-3">
                                <Phone class="h-5 w-5 text-muted-foreground" />
                                <span>{{ organizer.contact_phone }}</span>
                            </div>
                             <div v-if="organizer.website_url" class="flex items-center space-x-3">
                                <Globe class="h-5 w-5 text-muted-foreground" />
                                <a :href="organizer.website_url" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">{{ organizer.website_url }}</a>
                            </div>
                             <div v-if="!organizer.contact_email && !organizer.contact_phone && !organizer.website_url">
                                <p class="text-muted-foreground">{{ t('messages.no_contact_info') }}</p>
                            </div>
                        </CardContent>
                    </Card>

                     <Card v-if="socialLinks.length > 0">
                        <CardHeader>
                            <CardTitle>{{ t('organizers.social_media') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-for="[platform, url] in socialLinks" :key="platform" class="flex items-center space-x-3">
                                <ExternalLink class="h-5 w-5 text-muted-foreground" />
                                <a :href="url" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline capitalize">{{ platform }}</a>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column -->
                <div class="md:col-span-2 space-y-8">
                     <Card>
                        <CardHeader>
                            <CardTitle>{{ t('common.description') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="organizerDescription" v-html="organizerDescription" class="prose max-w-none"></div>
                            <p v-else class="text-muted-foreground">{{ t('messages.no_description') }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('addresses.address') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p v-if="fullAddress">{{ fullAddress }}</p>
                            <p v-else class="text-muted-foreground">{{ t('messages.no_address') }}</p>
                        </CardContent>
                    </Card>

                     <Card>
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div class="space-y-1.5">
                                <CardTitle>{{ t('organizers.team_members') }}</CardTitle>
                                <CardDescription>
                                    {{ t('organizers.team_members_subtitle') }}
                                </CardDescription>
                            </div>
                            <Dialog v-model:open="isInviteUserModalOpen">
                                <DialogTrigger as-child>
                                    <Button size="sm">
                                        <UserPlus class="mr-2 h-4 w-4" />
                                        {{ t('organizers.invite_user') }}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-[425px]">
                                    <DialogHeader>
                                        <DialogTitle>{{ t('organizers.invite_new_member') }}</DialogTitle>
                                        <DialogDescription>
                                            {{ t('organizers.invite_new_member_desc') }}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="submitInvite">
                                        <div class="grid gap-4 py-4">
                                            <div class="grid grid-cols-4 items-center gap-4">
                                                <Label for="email" class="text-right">
                                                    {{ t('fields.email') }}
                                                </Label>
                                                <Input id="email" v-model="form.email" type="email" class="col-span-3" required />
                                            </div>
                                             <div class="grid grid-cols-4 items-center gap-4">
                                                <Label for="role" class="text-right">
                                                    {{ t('fields.role') }}
                                                </Label>
                                                 <Select v-model="form.role_in_organizer" required>
                                                    <SelectTrigger class="col-span-3">
                                                        <SelectValue placeholder="Select a role" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="role in organizerRoles" :key="role.value" :value="role.value">
                                                            {{ role.label }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div v-if="form.errors.email" class="col-span-4 text-sm text-red-500 text-right">{{ form.errors.email }}</div>
                                            <div v-if="form.errors.role_in_organizer" class="col-span-4 text-sm text-red-500 text-right">{{ form.errors.role_in_organizer }}</div>
                                        </div>
                                         <DialogFooter>
                                            <Button type="submit" :disabled="form.processing">
                                                {{ t('actions.send_invitation') }}
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </CardHeader>
                        <CardContent>
                             <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{{ t('fields.name') }}</TableHead>
                                        <TableHead>{{ t('fields.email') }}</TableHead>
                                        <TableHead>{{ t('fields.role') }}</TableHead>
                                        <TableHead>{{ t('fields.status') }}</TableHead>
                                        <TableHead class="text-right">{{ t('fields.actions') }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="member in organizer.team_members" :key="member.id">
                                        <TableCell>{{ member.name }}</TableCell>
                                        <TableCell>{{ member.email }}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{{ member.role_in_organizer }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="member.is_active ? 'success' : 'secondary'">
                                                {{ member.is_active ? t('status.active') : t('status.pending') }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <!-- Actions (Edit role, Remove) will go here -->
                                            <Button variant="ghost" size="sm">...</Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="!organizer.team_members || organizer.team_members.length === 0">
                                         <TableCell colspan="5" class="text-center text-muted-foreground">
                                            {{ t('messages.no_team_members') }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                     <Card>
                        <CardHeader>
                            <CardTitle>{{ t('organizers.contract_details') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2" v-if="organizer.contract_details">
                            <p><strong>{{ t('organizers.terms') }}:</strong> {{ organizer.contract_details.terms || 'N/A' }}</p>
                            <p><strong>{{ t('organizers.rate_structure') }}:</strong> {{ organizer.contract_details.rate_structure || 'N/A' }}</p>
                            <p><strong>{{ t('organizers.payment_terms') }}:</strong> {{ organizer.contract_details.payment_terms || 'N/A' }}</p>
                            <p><strong>{{ t('organizers.cancellation_policy') }}:</strong> {{ organizer.contract_details.cancellation_policy || 'N/A' }}</p>
                             <p><strong>{{ t('organizers.effective_date') }}:</strong> {{ organizer.contract_details.effective_date || 'N/A' }}</p>
                            <p><strong>{{ t('organizers.expiry_date') }}:</strong> {{ organizer.contract_details.expiry_date || 'N/A' }}</p>
                        </CardContent>
                        <CardContent v-else>
                            <p class="text-muted-foreground">{{ t('messages.no_contract_details') }}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
