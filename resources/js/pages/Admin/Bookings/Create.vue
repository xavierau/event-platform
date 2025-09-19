<template>
    <AppLayout>
        <Head :title="pageTitle" />

        <div class="container mx-auto px-4 py-6 max-w-7xl">
            <!-- Header Section -->
            <div class="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <div class="flex items-center space-x-3">
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="route('admin.bookings.index')">
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Back to Bookings
                            </Link>
                        </Button>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ pageTitle }}</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Create a manual booking for a user. All fields are required for audit purposes.
                    </p>
                </div>
            </div>

            <form @submit.prevent="submitForm">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- User Selection -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <User class="h-5 w-5 mr-2" />
                                    Customer Selection
                                </CardTitle>
                                <CardDescription>
                                    Search and select the customer for this booking
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="!form.user_id">
                                    <Label for="user-search">Search Customer</Label>
                                    <Input
                                        id="user-search"
                                        v-model="userSearchQuery"
                                        type="text"
                                        placeholder="Search by name or email..."
                                        class="w-full"
                                    />
                                    <div v-if="form.errors.user_id" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.user_id }}
                                    </div>
                                </div>

                                <!-- Search Results -->
                                <div v-if="userSearchQuery.length >= 2 && !form.user_id" class="space-y-2 max-h-60 overflow-y-auto">
                                    <div v-if="isSearchingUsers" class="text-center py-4">
                                        <Loader2 class="h-4 w-4 animate-spin mx-auto" />
                                        <p class="text-sm text-gray-500 mt-2">Searching users...</p>
                                    </div>
                                    <div v-else-if="userSearchResults.length === 0" class="text-center py-4 text-gray-500">
                                        No users found matching your search.
                                    </div>
                                    <div v-else class="space-y-2">
                                        <div
                                            v-for="user in userSearchResults"
                                            :key="user.id"
                                            @click="selectUser(user)"
                                            class="p-3 border border-gray-200 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                        >
                                            <div class="font-medium">{{ user.name }}</div>
                                            <div class="text-sm text-gray-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Selected User -->
                                <div v-if="form.user_id && selectedUser" class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-md">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium text-blue-900 dark:text-blue-100">{{ selectedUser.name }}</div>
                                            <div class="text-sm text-blue-700 dark:text-blue-300">{{ selectedUser.email }}</div>
                                        </div>
                                        <Button type="button" variant="ghost" size="sm" @click="clearUser">
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Event & Ticket Selection -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <Calendar class="h-5 w-5 mr-2" />
                                    Event & Ticket Selection
                                </CardTitle>
                                <CardDescription>
                                    Choose the event and ticket type for this booking
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Event Selection -->
                                <div>
                                    <Label for="event_id">Event</Label>
                                    <Select v-model="form.event_id" @update:model-value="onEventChange">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select an event" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="event in events"
                                                :key="event.id"
                                                :value="event.id"
                                            >
                                                <div class="flex items-center justify-between w-full">
                                                    <span>{{ getTranslation(event.name, currentLocale) }}</span>
                                                    <Badge
                                                        :class="getEventStatusClass(event.event_status)"
                                                        class="ml-2"
                                                    >
                                                        {{ formatEventStatus(event.event_status) }}
                                                    </Badge>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div v-if="form.errors.event_id" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.event_id }}
                                    </div>
                                </div>

                                <!-- Ticket Selection -->
                                <div v-if="form.event_id">
                                    <Label for="ticket_definition_id">Ticket Type</Label>
                                    <div v-if="isLoadingTickets" class="flex items-center justify-center py-8">
                                        <Loader2 class="h-6 w-6 animate-spin" />
                                        <span class="ml-2">Loading tickets...</span>
                                    </div>
                                    <Select v-else v-model="form.ticket_definition_id" @update:model-value="onTicketChange">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a ticket type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="ticket in availableTickets"
                                                :key="ticket.id"
                                                :value="ticket.id"
                                            >
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{ getTranslation(ticket.name, currentLocale) }}</span>
                                                    <span class="text-sm text-gray-500">
                                                        ${{ formatCurrency(ticket.price) }} {{ form.currency }}
                                                        <span v-if="ticket.remaining_quantity !== null">
                                                            • {{ ticket.remaining_quantity }} remaining
                                                        </span>
                                                    </span>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div v-if="form.errors.ticket_definition_id" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.ticket_definition_id }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Booking Details -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <CreditCard class="h-5 w-5 mr-2" />
                                    Booking Details
                                </CardTitle>
                                <CardDescription>
                                    Configure quantity, pricing, and special options
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Quantity -->
                                    <div>
                                        <Label for="quantity">Quantity</Label>
                                        <Input
                                            id="quantity"
                                            v-model.number="form.quantity"
                                            type="number"
                                            min="1"
                                            :max="maxAllowedQuantity"
                                        />
                                        <div v-if="maxAllowedQuantity" class="text-xs text-gray-500 mt-1">
                                            Maximum: {{ maxAllowedQuantity }}
                                        </div>
                                        <div v-if="form.errors.quantity" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.quantity }}
                                        </div>
                                    </div>

                                    <!-- Currency -->
                                    <div>
                                        <Label for="currency">Currency</Label>
                                        <Select v-model="form.currency">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select currency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="currency in currencies" :key="currency" :value="currency">
                                                    {{ currency }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <div v-if="form.errors.currency" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.currency }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Complimentary Toggle -->
                                <div class="flex items-center space-x-2">
                                    <Switch
                                        id="is_complimentary"
                                        v-model:checked="form.is_complimentary"
                                        @update:checked="onComplimentaryToggle"
                                    />
                                    <Label for="is_complimentary">Complimentary Booking (Free)</Label>
                                </div>

                                <!-- Price Override -->
                                <div v-if="!form.is_complimentary">
                                    <Label for="price_override">
                                        Price Override
                                        <span class="text-gray-500">(Leave empty to use standard price)</span>
                                    </Label>
                                    <Input
                                        id="price_override"
                                        v-model.number="form.price_override"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Enter custom price"
                                    />
                                    <div v-if="form.errors.price_override" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.price_override }}
                                    </div>
                                </div>

                                <!-- Reason (Required) -->
                                <div>
                                    <Label for="reason">Reason for Manual Booking <span class="text-red-500">*</span></Label>
                                    <Textarea
                                        id="reason"
                                        v-model="form.reason"
                                        placeholder="Explain why this booking is being created manually..."
                                        rows="3"
                                        required
                                    />
                                    <div v-if="form.errors.reason" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.reason }}
                                    </div>
                                </div>

                                <!-- Admin Notes -->
                                <div>
                                    <Label for="admin_notes">Admin Notes</Label>
                                    <Textarea
                                        id="admin_notes"
                                        v-model="form.admin_notes"
                                        placeholder="Additional notes for internal use..."
                                        rows="2"
                                    />
                                    <div v-if="form.errors.admin_notes" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.admin_notes }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar - Summary -->
                    <div class="space-y-6">
                        <!-- Booking Summary -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <FileText class="h-5 w-5 mr-2" />
                                    Booking Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="!isFormValid" class="text-center py-8 text-gray-500">
                                    <AlertCircle class="h-8 w-8 mx-auto mb-2" />
                                    <p>Complete the form to see booking summary</p>
                                </div>
                                <div v-else class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium">Customer:</span>
                                        <span class="text-sm">{{ selectedUser?.name }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium">Event:</span>
                                        <span class="text-sm">{{ selectedEventName }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium">Ticket:</span>
                                        <span class="text-sm">{{ selectedTicketName }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium">Quantity:</span>
                                        <span class="text-sm">{{ form.quantity }}</span>
                                    </div>
                                    <div class="border-t pt-3">
                                        <div class="flex justify-between items-center">
                                            <span class="font-semibold">Total:</span>
                                            <div class="text-right">
                                                <div v-if="form.is_complimentary" class="text-green-600 font-semibold">
                                                    FREE
                                                </div>
                                                <div v-else class="font-semibold">
                                                    ${{ formatCurrency(totalAmount) }} {{ form.currency }}
                                                </div>
                                                <div v-if="!form.is_complimentary && isUsingPriceOverride" class="text-xs text-orange-600">
                                                    (Custom price: ${{ formatCurrency(form.price_override) }})
                                                </div>
                                                <div v-else-if="!form.is_complimentary && selectedTicket" class="text-xs text-gray-500">
                                                    (Standard price: ${{ formatCurrency(selectedTicket.price) }})
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Form Actions -->
                        <Card>
                            <CardContent class="pt-6">
                                <div class="space-y-3">
                                    <Button
                                        type="submit"
                                        class="w-full"
                                        :disabled="!isFormValid || form.processing"
                                    >
                                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                                        <PlusCircle v-else class="h-4 w-4 mr-2" />
                                        Create Booking
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="w-full"
                                        as-child
                                    >
                                        <Link :href="route('admin.bookings.index')">
                                            Cancel
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import axios from 'axios'
import {
    ArrowLeft, User, Calendar, CreditCard, FileText, AlertCircle,
    Loader2, X, PlusCircle
} from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { getTranslation, currentLocale } from '@/Utils/i18n'

// Interfaces
interface User {
    id: number
    name: string
    email: string
}

interface Event {
    id: number
    name: Record<string, string> | string
    event_status: string
}

interface TicketDefinition {
    id: number
    name: Record<string, string> | string
    price: number
    remaining_quantity: number | null
    max_quantity_per_user: number | null
}

interface FormData {
    user_id: number | null
    event_id: number | null
    ticket_definition_id: number | null
    quantity: number
    price_override: number | null
    currency: string
    reason: string
    admin_notes: string
    is_complimentary: boolean
}

// Props
const props = defineProps<{
    users: User[]
    events: Event[]
    currencies: string[]
    pageTitle: string
    breadcrumbs: Array<{ title: string; href?: string }>
}>()

// Form state
const form = useForm<FormData>({
    user_id: null,
    event_id: null,
    ticket_definition_id: null,
    quantity: 1,
    price_override: null,
    currency: props.currencies[0] || 'USD',
    reason: '',
    admin_notes: '',
    is_complimentary: false,
})

// Component state
const userSearchQuery = ref('')
const userSearchResults = ref<User[]>([])
const isSearchingUsers = ref(false)
const selectedUser = ref<User | null>(null)
const availableTickets = ref<TicketDefinition[]>([])
const selectedTicket = ref<TicketDefinition | null>(null)
const isLoadingTickets = ref(false)

// Computed properties
const isFormValid = computed(() => {
    return form.user_id &&
           form.event_id &&
           form.ticket_definition_id &&
           form.quantity > 0 &&
           form.currency &&
           form.reason.trim().length > 0
})

const selectedEventName = computed(() => {
    const event = props.events.find(e => e.id === form.event_id)
    return event ? getTranslation(event.name, currentLocale.value) : ''
})

const selectedTicketName = computed(() => {
    return selectedTicket.value
        ? getTranslation(selectedTicket.value.name, currentLocale.value)
        : ''
})

const maxAllowedQuantity = computed(() => {
    if (!selectedTicket.value) return null

    const remaining = selectedTicket.value.remaining_quantity
    const maxPerUser = selectedTicket.value.max_quantity_per_user

    if (remaining !== null && maxPerUser !== null) {
        return Math.min(remaining, maxPerUser)
    }

    return remaining || maxPerUser || null
})

const isUsingPriceOverride = computed(() => {
    return !form.is_complimentary && form.price_override !== null && form.price_override > 0
})

const totalAmount = computed(() => {
    if (form.is_complimentary) return 0

    const unitPrice = isUsingPriceOverride.value
        ? form.price_override!
        : (selectedTicket.value?.price || 0)

    return unitPrice * form.quantity
})

// Methods
const debouncedUserSearch = debounce(async () => {
    if (userSearchQuery.value.length < 2) {
        userSearchResults.value = []
        return
    }

    isSearchingUsers.value = true

    try {
        // Use the dedicated booking user search route
        const response = await axios.post(route('admin.bookings.search-users'), {
            search: userSearchQuery.value,
            limit: 20,
        })

        userSearchResults.value = response.data.users || []
    } catch (error) {
        console.error('User search failed:', error)
        userSearchResults.value = []
    } finally {
        isSearchingUsers.value = false
    }
}, 300)

const selectUser = (user: User) => {
    selectedUser.value = user
    form.user_id = user.id
    userSearchQuery.value = ''
    userSearchResults.value = []
}

const clearUser = () => {
    selectedUser.value = null
    form.user_id = null
    userSearchQuery.value = ''
    userSearchResults.value = []
}

const onEventChange = async (eventId: number | null) => {
    // Clear dependent fields
    form.ticket_definition_id = null
    selectedTicket.value = null
    availableTickets.value = []

    if (!eventId) return

    // Load tickets for selected event
    isLoadingTickets.value = true

    try {
        const response = await axios.get(route('admin.events.ticket-definitions', eventId))
        availableTickets.value = response.data.ticket_definitions || []
    } catch (error) {
        console.error('Failed to load tickets:', error)
        availableTickets.value = []
    } finally {
        isLoadingTickets.value = false
    }
}

const onTicketChange = (ticketId: number | null) => {
    selectedTicket.value = availableTickets.value.find(t => t.id === ticketId) || null

    // Reset quantity if it exceeds the new ticket's limits
    if (maxAllowedQuantity.value && form.quantity > maxAllowedQuantity.value) {
        form.quantity = maxAllowedQuantity.value
    }
}

const onComplimentaryToggle = (isComplimentary: boolean) => {
    if (isComplimentary) {
        form.price_override = null
    }
}

const getEventStatusClass = (status: string) => {
    const statusClasses: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        postponed: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    }
    return statusClasses[status] || statusClasses.draft
}

const formatEventStatus = (status: string) => {
    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown'
}

const formatCurrency = (amount: number) => {
    return (amount / 100).toFixed(2)
}

const submitForm = () => {
    form.post(route('admin.bookings.store'), {
        onSuccess: () => {
            // Form will automatically redirect on success
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors)
        }
    })
}

// Watchers
watch(userSearchQuery, debouncedUserSearch)
</script>