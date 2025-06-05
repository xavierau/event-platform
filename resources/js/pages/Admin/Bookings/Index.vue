<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Plus, Download, Search, Filter, Eye, Edit, X,
    Ticket, CheckCircle, Clock, DollarSign, XCircle, QrCode
} from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select'
import { getTranslation, currentLocale } from '@/Utils/i18n'

// Define interfaces for type safety
interface BookingItem {
    id: number
    booking_number: string
    qr_code_identifier: string
    status: string
    quantity: number
    price_at_booking: number
    created_at: string
    event?: { id: number; name: Record<string, string> | string }
    user?: { id: number; name: string; email: string }
    ticket_definition?: { id: number; name: Record<string, string> | string }
    transaction?: { id: number; payment_gateway_transaction_id?: string }
}

interface BookingsData {
    data: BookingItem[]
    total: number
    from: number
    to: number
    links: Array<{ url?: string; label: string; active: boolean }>
    prev_page_url?: string
    next_page_url?: string
}

interface EventOption {
    id: number
    name: string
}

interface StatusOption {
    value: string
    label: string
}

interface Statistics {
    total_bookings: number
    confirmed_bookings: number
    pending_bookings: number
    total_revenue: number
}

interface FilterOptions {
    search?: string
    status?: string
    event_id?: string
    date_from?: string
    date_to?: string
    sort_by?: string
    sort_order?: string
    per_page?: string
}

// Props
const props = defineProps<{
    bookings: BookingsData
    events: EventOption[]
    statuses: StatusOption[]
    statistics: Statistics
    filters: FilterOptions
    pageTitle: string
    breadcrumbs: Array<{ title: string; href?: string }>
}>()

// Reactive state
const processing = ref(false)
const selectedBookings = ref<number[]>([])

// Filter form
const filterForm = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    event_id: props.filters.event_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    sort_by: props.filters.sort_by || 'created_at',
    sort_order: props.filters.sort_order || 'desc',
    per_page: props.filters.per_page || '15',
})

// Methods
const applyFilters = () => {
    router.get(route('admin.bookings.index'), filterForm, {
        preserveState: true,
        replace: true,
    })
}

const clearFilters = () => {
    Object.keys(filterForm).forEach(key => {
        if (key === 'sort_by') (filterForm as any)[key] = 'created_at'
        else if (key === 'sort_order') (filterForm as any)[key] = 'desc'
        else if (key === 'per_page') (filterForm as any)[key] = '15'
        else (filterForm as any)[key] = ''
    })
    applyFilters()
}

const exportBookings = () => {
    // TODO: Implement export functionality
    alert('Export functionality coming soon!')
}

const toggleSelectAll = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.checked) {
        selectedBookings.value = props.bookings.data.map(booking => booking.id)
    } else {
        selectedBookings.value = []
    }
}

const bulkAction = (action: string) => {
    if (selectedBookings.value.length === 0) return

    processing.value = true
    // TODO: Implement bulk actions
    console.log(`Bulk ${action} for bookings:`, selectedBookings.value)

    setTimeout(() => {
        processing.value = false
        selectedBookings.value = []
    }, 1000)
}

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

const formatStatus = (status: string) => {
    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown'
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatCurrency = (amount: number) => {
    return (amount / 100).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

// Watch for filter changes and apply them automatically for some fields
watch(() => filterForm.per_page, () => {
    applyFilters()
})
</script>

<template>
    <Head :title="pageTitle || 'Bookings'" />

    <AppLayout :page-title="pageTitle || 'Manage Bookings'" :breadcrumbs="breadcrumbs || []">
        <div class="container mx-auto px-4 py-6 max-w-7xl">
            <!-- Header Section -->
            <div class="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ pageTitle }}</h1>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Manage and view all bookings across events
                    </p>
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none space-x-3">
                    <Button variant="outline" @click="exportBookings">
                        <Download class="h-4 w-4 mr-2" />
                        Export
                    </Button>
                    <Button variant="secondary" as-child>
                        <Link :href="route('admin.qr-scanner.index')">
                            <QrCode class="h-4 w-4 mr-2" />
                            Scan QR
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('admin.bookings.create')">
                            <Plus class="h-4 w-4 mr-2" />
                            Create Booking
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <Ticket class="h-8 w-8 text-blue-600" />
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Bookings</dt>
                                    <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ statistics.total_bookings }}</dd>
                                </dl>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <CheckCircle class="h-8 w-8 text-green-600" />
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Confirmed</dt>
                                    <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ statistics.confirmed_bookings }}</dd>
                                </dl>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <Clock class="h-8 w-8 text-yellow-600" />
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending</dt>
                                    <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ statistics.pending_bookings }}</dd>
                                </dl>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <DollarSign class="h-8 w-8 text-green-600" />
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Revenue</dt>
                                    <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">${{ formatCurrency(statistics.total_revenue) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters and Search Section -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center">
                        <Filter class="h-5 w-5 mr-2" />
                        Filters
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Search Input -->
                        <div>
                            <Label for="search">Search</Label>
                            <Input
                                id="search"
                                v-model="filterForm.search"
                                placeholder="Booking #, user name, email, event..."
                                class="mt-1"
                            />
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <Label for="status">Status</Label>
                            <Select v-model="filterForm.status">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="All Statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All Statuses</SelectItem>
                                    <SelectItem
                                        v-for="status in statuses"
                                        :key="status.value"
                                        :value="status.value"
                                    >
                                        {{ status.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Event Filter -->
                        <div>
                            <Label for="event_id">Event</Label>
                            <Select v-model="filterForm.event_id">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="All Events" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All Events</SelectItem>
                                    <SelectItem
                                        v-for="event in events"
                                        :key="event.id"
                                        :value="event.id.toString()"
                                    >
                                        {{ event.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Date Range -->
                        <div>
                            <Label for="date_from">Date From</Label>
                            <Input
                                id="date_from"
                                v-model="filterForm.date_from"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="date_to">Date To</Label>
                            <Input
                                id="date_to"
                                v-model="filterForm.date_to"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <!-- Sort Options -->
                        <div>
                            <Label for="sort_by">Sort By</Label>
                            <Select v-model="filterForm.sort_by">
                                <SelectTrigger class="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="created_at">Date Created</SelectItem>
                                    <SelectItem value="booking_number">Booking Number</SelectItem>
                                    <SelectItem value="status">Status</SelectItem>
                                    <SelectItem value="price_at_booking">Price</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="sort_order">Sort Order</Label>
                            <Select v-model="filterForm.sort_order">
                                <SelectTrigger class="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="desc">Descending</SelectItem>
                                    <SelectItem value="asc">Ascending</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex space-x-2 items-end">
                            <Button type="submit" class="flex-1">
                                <Search class="h-4 w-4 mr-2" />
                                Apply
                            </Button>
                            <Button type="button" variant="outline" @click="clearFilters">
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Bookings Table -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span>Bookings ({{ bookings.total }} total)</span>
                        <div class="flex items-center space-x-2">
                            <Label for="per_page" class="text-sm">Show:</Label>
                            <Select v-model="filterForm.per_page" @update:model-value="applyFilters">
                                <SelectTrigger class="w-20">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="10">10</SelectItem>
                                    <SelectItem value="15">15</SelectItem>
                                    <SelectItem value="25">25</SelectItem>
                                    <SelectItem value="50">50</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" @change="toggleSelectAll" class="rounded border-gray-300" />
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Booking
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Event
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Transaction
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200">
                                <tr v-if="!bookings.data || bookings.data.length === 0">
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <Ticket class="h-12 w-12 text-gray-400 mb-4" />
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No bookings found</h3>
                                            <p class="text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="booking in bookings.data" :key="booking.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input
                                            type="checkbox"
                                            :value="booking.id"
                                            v-model="selectedBookings"
                                            class="rounded border-gray-300"
                                        />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            #{{ booking.qr_code_identifier || booking.id }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ booking.event ? getTranslation(booking.event.name, currentLocale) : 'N/A' }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ booking.ticket_definition ? getTranslation(booking.ticket_definition.name, currentLocale) : 'General Admission' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ booking.user?.name || 'N/A' }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ booking.user?.email || 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="getStatusBadgeClasses(booking.status)">
                                            {{ formatStatus(booking.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <div class="font-medium">
                                            ${{ formatCurrency(booking.price_at_booking * booking.quantity) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div v-if="booking.transaction">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                #{{ booking.transaction.id }}
                                            </div>
                                            <div v-if="booking.transaction.payment_gateway_transaction_id" class="text-xs font-mono truncate max-w-32" :title="booking.transaction.payment_gateway_transaction_id">
                                                {{ booking.transaction.payment_gateway_transaction_id }}
                                            </div>
                                        </div>
                                        <div v-else class="text-gray-400">
                                            N/A
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(booking.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <Button variant="ghost" size="sm" as-child>
                                            <Link :href="route('admin.bookings.show', booking.id)">
                                                <Eye class="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" size="sm" as-child>
                                            <Link :href="route('admin.bookings.edit', booking.id)">
                                                <Edit class="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="bookings.links && bookings.links.length > 3" class="px-6 py-4 border-t border-gray-200">
                        <nav class="flex items-center justify-between">
                            <div class="flex justify-between flex-1 sm:hidden">
                                <Link
                                    v-if="bookings.prev_page_url"
                                    :href="bookings.prev_page_url"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    Previous
                                </Link>
                                <Link
                                    v-if="bookings.next_page_url"
                                    :href="bookings.next_page_url"
                                    class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    Next
                                </Link>
                            </div>
                            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing {{ bookings.from }} to {{ bookings.to }} of {{ bookings.total }} results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <Link
                                            v-for="(link, index) in bookings.links"
                                            :key="index"
                                            :href="link.url || ''"
                                            class="relative inline-flex items-center px-2 py-2 text-sm font-medium border"
                                            :class="{
                                                'bg-blue-50 border-blue-500 text-blue-600': link.active,
                                                'bg-white border-gray-300 text-gray-500 hover:bg-gray-50': !link.active && link.url,
                                                'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed': !link.url
                                            }"
                                            :disabled="!link.url"
                                        >
                                            {{ link.label }}
                                        </Link>
                                    </nav>
                                </div>
                            </div>
                        </nav>
                    </div>
                </CardContent>
            </Card>

            <!-- Bulk Actions (when bookings are selected) -->
            <div v-if="selectedBookings.length > 0" class="fixed bottom-4 right-4 bg-white shadow-lg rounded-lg p-4 border">
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">{{ selectedBookings.length }} selected</span>
                    <Button variant="outline" size="sm" @click="bulkAction('confirm')" :disabled="processing">
                        <CheckCircle class="h-4 w-4 mr-1" />
                        Confirm
                    </Button>
                    <Button variant="outline" size="sm" @click="bulkAction('cancel')" :disabled="processing">
                        <XCircle class="h-4 w-4 mr-1" />
                        Cancel
                    </Button>
                    <Button variant="ghost" size="sm" @click="selectedBookings = []">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
