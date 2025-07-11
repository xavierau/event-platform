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
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none space-x-3">
                    <Button variant="outline" as-child>
                        <Link :href="route('admin.bookings.edit', booking.id)">
                            <Edit class="h-4 w-4 mr-2" />
                            Edit Booking
                        </Link>
                    </Button>
                    <Button>
                        <Download class="h-4 w-4 mr-2" />
                        Download QR
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Booking Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <Ticket class="h-5 w-5 mr-2" />
                                Booking Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Booking Number</Label>
                                    <p class="text-lg font-semibold">{{ booking.booking_number }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Status</Label>
                                    <div class="mt-1">
                                        <span :class="getStatusBadgeClasses(booking.status)">
                                            {{ formatStatus(booking.status) }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">QR Code</Label>
                                    <p class="text-sm font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded">
                                        {{ booking.qr_code_identifier }}
                                    </p>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Quantity</Label>
                                    <p class="text-lg font-semibold">{{ booking.quantity }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Price per Unit</Label>
                                    <p class="text-lg font-semibold">${{ formatCurrency(booking.price_at_booking) }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Total Amount</Label>
                                    <p class="text-lg font-semibold">${{ formatCurrency(booking.price_at_booking * booking.quantity) }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Check-ins Allowed</Label>
                                    <p class="text-lg">{{ booking.max_allowed_check_ins || 1 }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Booking Date</Label>
                                    <p class="text-sm">{{ formatDate(booking.created_at) }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Event Information -->
                    <Card v-if="booking.event">
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <Calendar class="h-5 w-5 mr-2" />
                                Event Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Event Name</Label>
                                <p class="text-lg font-semibold">{{ getTranslation(booking.event.name, currentLocale) }}</p>
                            </div>
                            <div v-if="booking.event.description">
                                <Label class="text-sm font-medium text-gray-500">Description</Label>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ getTranslation(booking.event.description, currentLocale) }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Event Occurrence Information -->
                    <Card v-if="booking.ticket_definition?.event_occurrences && booking.ticket_definition.event_occurrences.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <Clock class="h-5 w-5 mr-2" />
                                Event Schedule
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-for="occurrence in booking.ticket_definition.event_occurrences"
                                :key="occurrence.id"
                                class="border rounded-lg p-4 space-y-3"
                            >
                                <div v-if="occurrence.name">
                                    <Label class="text-sm font-medium text-gray-500">Session Name</Label>
                                    <p class="font-semibold">{{ getTranslation(occurrence.name, currentLocale) }}</p>
                                </div>
                                <div v-if="occurrence.description">
                                    <Label class="text-sm font-medium text-gray-500">Session Description</Label>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ getTranslation(occurrence.description, currentLocale) }}</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <Label class="text-sm font-medium text-gray-500">Start Date & Time</Label>
                                        <p class="text-sm">{{ formatDateWithTimezone(occurrence.start_at_utc, occurrence.timezone) }}</p>
                                    </div>
                                    <div v-if="occurrence.end_at_utc">
                                        <Label class="text-sm font-medium text-gray-500">End Date & Time</Label>
                                        <p class="text-sm">{{ formatDateWithTimezone(occurrence.end_at_utc, occurrence.timezone) }}</p>
                                    </div>
                                    <div>
                                        <Label class="text-sm font-medium text-gray-500">Status</Label>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            {{ formatStatus(occurrence.status) }}
                                        </span>
                                    </div>
                                    <div v-if="occurrence.capacity">
                                        <Label class="text-sm font-medium text-gray-500">Capacity</Label>
                                        <p class="text-sm">{{ occurrence.capacity }} attendees</p>
                                    </div>
                                </div>

                                <!-- Venue Information -->
                                <div v-if="occurrence.venue" class="mt-4 pt-4 border-t">
                                    <div class="flex items-center mb-2">
                                        <MapPin class="h-4 w-4 mr-2 text-gray-500" />
                                        <Label class="text-sm font-medium text-gray-500">Venue</Label>
                                    </div>
                                    <div class="ml-6 space-y-1">
                                        <p class="font-medium">{{ getTranslation(occurrence.venue.name, currentLocale) }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ getTranslation(occurrence.venue.address_line_1, currentLocale) }}<br>
                                            {{ getTranslation(occurrence.venue.city, currentLocale) }} {{ occurrence.venue.postal_code }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Online Meeting Information -->
                                <div v-if="occurrence.is_online" class="mt-4 pt-4 border-t">
                                    <div class="flex items-center mb-2">
                                        <Calendar class="h-4 w-4 mr-2 text-gray-500" />
                                        <Label class="text-sm font-medium text-gray-500">Online Event</Label>
                                    </div>
                                    <div class="ml-6">
                                        <p class="text-sm text-blue-600 dark:text-blue-400" v-if="occurrence.online_meeting_link">
                                            <a :href="occurrence.online_meeting_link" target="_blank" class="underline">
                                                Join Online Meeting
                                            </a>
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400" v-else>
                                            Online meeting link will be provided closer to the event date.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Ticket Information -->
                    <Card v-if="booking.ticket_definition">
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <CreditCard class="h-5 w-5 mr-2" />
                                Ticket Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Ticket Type</Label>
                                <p class="text-lg font-semibold">{{ getTranslation(booking.ticket_definition.name, currentLocale) }}</p>
                            </div>
                            <div v-if="booking.ticket_definition.description">
                                <Label class="text-sm font-medium text-gray-500">Description</Label>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ getTranslation(booking.ticket_definition.description, currentLocale) }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label class="text-sm font-medium text-gray-500">Current Price</Label>
                                    <p class="text-sm">${{ formatCurrency(booking.ticket_definition.price) }}</p>
                                </div>
                                <div v-if="booking.ticket_definition.total_quantity">
                                    <Label class="text-sm font-medium text-gray-500">Total Available</Label>
                                    <p class="text-sm">{{ booking.ticket_definition.total_quantity }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Check-in History -->
                    <Card v-if="booking.check_in_logs && booking.check_in_logs.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <CheckCircle class="h-5 w-5 mr-2" />
                                Check-in History
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="log in booking.check_in_logs"
                                    :key="log.id"
                                    class="border-l-4 border-green-400 bg-green-50 dark:bg-green-900/20 p-4 rounded-r"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium">{{ log.method || 'Manual' }} Check-in</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ formatDate(log.check_in_timestamp) }}
                                            </p>
                                            <p v-if="log.operator?.name" class="text-sm text-gray-600 dark:text-gray-400">
                                                Operator: {{ log.operator.name }}
                                            </p>
                                        </div>
                                        <span class="text-xs bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200 px-2 py-1 rounded">
                                            {{ log.status || 'Success' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Customer Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <User class="h-5 w-5 mr-2" />
                                Customer Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Name</Label>
                                <p class="font-semibold">{{ booking.user?.name || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Email</Label>
                                <p class="text-sm">{{ booking.user?.email || 'N/A' }}</p>
                            </div>
                            <div v-if="booking.user?.created_at">
                                <Label class="text-sm font-medium text-gray-500">Customer Since</Label>
                                <p class="text-sm">{{ formatDate(booking.user.created_at) }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Transaction Information -->
                    <Card v-if="booking.transaction">
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <DollarSign class="h-5 w-5 mr-2" />
                                Transaction Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Transaction ID</Label>
                                <p class="text-sm font-mono">#{{ booking.transaction.id }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Total Amount</Label>
                                <p class="text-lg font-semibold">${{ formatCurrency(booking.transaction.total_amount) }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Status</Label>
                                <span :class="getTransactionStatusClasses(booking.transaction.status)">
                                    {{ formatStatus(booking.transaction.status) }}
                                </span>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Currency</Label>
                                <p class="text-sm uppercase">{{ booking.transaction.currency }}</p>
                            </div>
                            <div v-if="booking.transaction.payment_gateway">
                                <Label class="text-sm font-medium text-gray-500">Payment Gateway</Label>
                                <p class="text-sm capitalize">{{ booking.transaction.payment_gateway }}</p>
                            </div>
                            <div v-if="booking.transaction.payment_gateway_transaction_id">
                                <Label class="text-sm font-medium text-gray-500">Stripe Session ID</Label>
                                <p class="text-xs font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded break-all">
                                    {{ booking.transaction.payment_gateway_transaction_id }}
                                </p>
                            </div>
                            <div v-if="booking.transaction.payment_intent_id">
                                <Label class="text-sm font-medium text-gray-500">Stripe Payment Intent ID</Label>
                                <p class="text-xs font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded break-all">
                                    {{ booking.transaction.payment_intent_id }}
                                </p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-gray-500">Transaction Date</Label>
                                <p class="text-sm">{{ formatDate(booking.transaction.created_at) }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Quick Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <!-- <Button variant="outline" class="w-full" @click="resendConfirmation">
                                <Mail class="h-4 w-4 mr-2" />
                                Resend Confirmation
                            </Button> -->
                            <!-- <Button variant="outline" class="w-full" @click="printTicket">
                                <Printer class="h-4 w-4 mr-2" />
                                Print Ticket
                            </Button> -->
                            <Button variant="destructive" class="w-full" @click="cancelBooking">
                                <XCircle class="h-4 w-4 mr-2" />
                                Cancel Booking
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import {
    ArrowLeft, Edit, Download, Ticket, Calendar, CreditCard,
    CheckCircle, User, DollarSign, Mail, Printer, XCircle, MapPin, Clock
} from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { getTranslation, currentLocale } from '@/Utils/i18n'

// Define interfaces
interface EventOccurrence {
    id: number
    name: Record<string, string> | string
    description?: Record<string, string> | string
    start_at_utc: string
    end_at_utc?: string
    timezone: string
    status: string
    capacity?: number
    is_online: boolean
    online_meeting_link?: string
    venue?: {
        id: number
        name: Record<string, string> | string
        address_line_1: Record<string, string> | string
        city: Record<string, string> | string
        postal_code: string
    }
}

interface BookingDetails {
    id: number
    booking_number: string
    qr_code_identifier: string
    status: string
    quantity: number
    price_at_booking: number
    max_allowed_check_ins: number
    created_at: string
    event?: {
        id: number
        name: Record<string, string> | string
        description?: Record<string, string> | string
    }
    user?: {
        id: number
        name: string
        email: string
        created_at?: string
    }
    ticket_definition?: {
        id: number
        name: Record<string, string> | string
        description?: Record<string, string> | string
        price: number
        total_quantity?: number
        event_occurrences?: EventOccurrence[]
    }
    transaction?: {
        id: number
        total_amount: number
        currency: string
        status: string
        payment_gateway?: string
        payment_gateway_transaction_id?: string
        payment_intent_id?: string
        created_at: string
    }
    check_in_logs?: Array<{
        id: number
        method?: string
        check_in_timestamp: string
        operator?: { name: string }
        status?: string
    }>
}

// Props
defineProps<{
    booking: BookingDetails
    pageTitle: string
    breadcrumbs: Array<{ title: string; href?: string }>
}>()

// Methods
const getStatusBadgeClasses = (status: string) => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

    const statusClasses: Record<string, string> = {
        confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        pending_confirmation: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        used: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    }

    return `${baseClasses} ${statusClasses[status] || statusClasses.pending_confirmation}`
}

const getTransactionStatusClasses = (status: string) => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

    const statusClasses: Record<string, string> = {
        confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        pending_payment: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    }

    return `${baseClasses} ${statusClasses[status] || statusClasses.pending_payment}`
}

const formatStatus = (status: string) => {
    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown'
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatCurrency = (amount: number) => {
    return (amount / 100).toFixed(2)
}

const formatDateWithTimezone = (dateString: string, timezone: string) => {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone
    }) + ` (${timezone})`
}

const resendConfirmation = () => {
    // TODO: Implement resend confirmation
    alert('Resend confirmation functionality coming soon!')
}

const printTicket = () => {
    // TODO: Implement print ticket
    window.print()
}

const cancelBooking = () => {
    // TODO: Implement cancel booking
    if (confirm('Are you sure you want to cancel this booking?')) {
        alert('Cancel booking functionality coming soon!')
    }
}
</script>
