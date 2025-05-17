# Event Management Platform Overview

## User Groups:
*   Platform Admin
*   Event Organizers
*   General Users

## Internationalization:
*   English (en)
*   Traditional Chinese (zh-TW)
*   Simplified Chinese (zh-CN)

## Core Entities:
*   Event
*   Category (for events)
*   Booking
*   Venue
*   Setting (site-wide configurations)
*   EventOccurrence
*   TicketDefinition
*   Tag
*   Country
*   State/Province

## Entity Details:

### Event
Represents the main event information.
*   `id` (Primary Key)
*   `organizer_id` (Foreign Key to User - Event Organizer)
*   `category_id` (Foreign Key to Category)
*   `name` (translatable)
*   `slug` (translatable, unique URL identifier)
*   `excerpt` (translatable, short description)
*   `description` (translatable, rich text)
*   `why_attend` (translatable, rich text)
*   `offline_payment_instructions` (translatable)
*   `is_featured` (Boolean, Admin controlled)
*   `status` (Enum: e.g., Draft, Published, Unpublished, Cancelled. Admin/Organizer controlled)
*   `thumbnail_image_path`
*   `poster_image_path`
*   `youtube_video_id` (Optional)
*   `meta_title` (translatable, for SEO)
*   `meta_description` (translatable, for SEO)
*   `meta_tags` (translatable, for SEO)
*   `created_at`
*   `updated_at`

### EventOccurrence
Represents a specific instance/session of an Event. An Event can have multiple occurrences.
*   `id` (Primary Key)
*   `event_id` (Foreign Key to Event)
*   `venue_id` (Foreign Key to Venue, nullable if online)
*   `is_online_event` (Boolean)
*   `online_event_url` (nullable)
*   `date` (Date)
*   `start_time` (Time)
*   `end_time` (Time)
*   `created_at`
*   `updated_at`

### Venue
Represents a physical location for an event occurrence. If `organizer_id` is NULL, the venue is considered public/common. If `organizer_id` is set, it's private to that organizer.
*   `id` (Primary Key)
*   `title` (translatable, e.g., "Grand Ballroom")
*   `slug` (translatable, unique URL identifier)
*   `description` (translatable, text)
*   `venue_type` (translatable, e.g., "Hotel", "Conference Center", "Outdoor")
*   `amenities` (translatable, text or JSON, e.g., ["WiFi", "Projector", "Parking"])
*   `seated_guest_number` (Integer, nullable)
*   `standing_guest_number` (Integer, nullable)
*   `neighborhoods` (translatable, text, e.g., "Downtown", "Waterfront")
*   `pricing_details` (translatable, text, describes pricing structure)
*   `availability_details` (translatable, text, describes general availability)
*   `food_options` (translatable, text, describes catering/food situation)
*   `show_quote_form` (Boolean, default false)
*   `contact_email` (varchar, nullable)
*   `address_line_1` (translatable)
*   `city` (translatable)
*   `state_province` (translatable, or FK to a State/Province table)
*   `zip_code` (varchar)
*   `country_id` (Foreign Key to Country table - *needs Country entity defined*)
*   `latitude` (Decimal/Float, nullable)
*   `longitude` (Decimal/Float, nullable)
*   `images` (JSON or text, stores paths or structured image data, nullable)
*   `organizer_id` (Foreign Key to User - Event Organizer, nullable)
*   `status` (Enum/varchar, e.g., "Active", "Inactive", "Pending Approval", default "Pending Approval")
*   `created_at`
*   `updated_at`
    *Note: Consider creating separate `Country` and `State/Province` entities.*

### Country
Represents countries, used for venue addresses and potentially user profiles. Data is typically pre-populated/seeded.
*   `id` (Primary Key, Integer)
*   `name` (translatable, e.g., "United States", "Canada", "China")
*   `iso_code_2` (char(2), unique, indexed, e.g., "US", "CA", "CN")
*   `iso_code_3` (char(3), unique, indexed, e.g., "USA", "CAN", "CHN")
*   `phone_code` (varchar, nullable, e.g., "+1", "+1", "+86")
*   `created_at`
*   `updated_at`
    *Example Data: `(1, 'United States', 'US', 'USA', '+1')`, `(2, 'Canada', 'CA', 'CAN', '+1')`, `(3, 'China', 'CN', 'CHN', '+86')`*

### State/Province
Represents states, provinces, or regions within a country. Data is typically pre-populated/seeded.
*   `id` (Primary Key, Integer)
*   `country_id` (Foreign Key to Country, indexed)
*   `name` (translatable, e.g., "California", "Ontario", "Guangdong Province")
*   `code` (varchar, nullable, indexed, e.g., "CA", "ON", "GD" - unique within a country)
*   `created_at`
*   `updated_at`
    *Example Data: `(1, 1, 'California', 'CA')`, `(2, 1, 'New York', 'NY')`, `(3, 3, 'Guangdong Province', 'GD')`*

### Order
Represents an overall customer transaction/purchase, which can contain multiple individual ticket bookings. This is the master record for a sale. Amounts are stored as integers in the smallest currency unit (e.g., cents). Tax amounts are always rounded up to the nearest smallest currency unit to prevent underpayment.
*   `id` (Primary Key, Integer, this would be the `transaction_id` referenced in the `Booking` table from the user schema)
*   `order_number` (Varchar, unique, human-readable identifier for the order, e.g., ORD-202407-12345. Shared by all Booking items in this order.)
*   `customer_id` (Foreign Key to User - General User who made the purchase)
*   `total_gross_amount` (Integer - sum of all Booking items' price + tax in this order, in smallest currency unit)
*   `total_net_amount` (Integer - sum of all Booking items' net_price, in smallest currency unit)
*   `total_tax_amount` (Integer - sum of all Booking items' tax, in smallest currency unit, always rounded up from calculations)
*   `currency` (Varchar(3), e.g., "HKD", "USD", "CNY" - ISO 4217 currency code)
*   `payment_type` (Varchar, e.g., "online", "offline", "free_checkout")
*   `payment_gateway_charge_id` (Varchar, nullable, ID from the payment processor like Stripe)
*   `payment_status` (Enum/Varchar, e.g., "Pending", "Paid", "Failed", "Refunded")
*   `order_status` (Enum/Varchar, e.g., "Pending Confirmation", "Confirmed", "Partially Cancelled", "Cancelled", "Completed")
*   `notes_internal` (Text, optional, for admin/organizer remarks)
*   `created_at`
*   `updated_at`

### Booking
Represents an individual ticket sold as part of an Order. Each row is one ticket instance. Based on the user-provided schema. Amounts are stored as integers in the smallest currency unit (e.g., cents). Tax amounts are always rounded up to the nearest smallest currency unit to prevent underpayment.
*   `id` (Primary Key, Integer)
*   `order_id` (Foreign Key to `Order.id` - this corresponds to `transaction_id` in the user's schema context for `bookings` table)
*   `order_number` (Varchar, Foreign Key/Reference to `Order.order_number` - denormalized, for grouping tickets of the same order)
*   `customer_id` (Foreign Key to User - denormalized from Order, but present in schema)
*   `organiser_id` (Foreign Key to User - Event Organizer, nullable, if applicable per ticket for commission/tracking)
*   `event_id` (Foreign Key to Event)
*   `ticket_definition_id` (Foreign Key to `TicketDefinition.id` - corresponds to `ticket_id` in user schema)
*   `quantity` (Integer - As per user: "3 tickets then that will create 3 bookings", so this is likely always 1 per row, each row being one ticket. Included as per schema.)
*   `price_at_purchase` (Integer - price of this single ticket at the time of booking, in smallest currency unit)
*   `tax_amount` (Integer - tax for this single ticket, in smallest currency unit, always rounded up from calculations)
*   `net_price` (Integer - net price for this single ticket, in smallest currency unit)
*   `status` (Enum/TinyInt - status of this specific ticket, e.g., 0:Active, 1:Cancelled, 2:CheckedIn, 3:Refunded. Based on `status` and `booking_cancel` from schema)
*   `item_sku` (Varchar, nullable - SKU for the ticket type, denormalized or generated)
*   `is_paid` (Boolean/TinyInt - Denormalized, status of this ticket's payment, often derived from Order payment status)
*   `checked_in` (Boolean/TinyInt - Flag indicating if the ticket holder has been checked in)
*   `checked_in_time` (Datetime, nullable - Timestamp of check-in)
*   `is_bulk_booking` (Boolean/TinyInt - Indicates if this booking was part of a bulk operation, from `is_bulk` in schema)
*   `common_order_reference` (Varchar, nullable - From `common_order` in schema, purpose to be clarified if different from `order_number`)
*   `qr_code_identifier` (Varchar, unique, indexed, nullable - The unique string embedded in the QR code for this ticket)
*   `max_allowed_check_ins` (Integer, default 1 - How many times this ticket can be used for entry)
*   `created_at`
*   `updated_at`
    *Denormalized Fields (present in user schema, useful for snapshots/reporting but data originates from linked entities like Event, TicketDefinition, User, Order): Amounts are stored as integers in the smallest currency unit (e.g., cents).*
    *   `event_title` (Varchar)
    *   `event_start_date` (Date)
    *   `event_end_date` (Date)
    *   `event_start_time` (Time)
    *   `event_end_time` (Time)
    *   `event_repetitive_schedule_id` (Integer, from `event_repetitive` - could link to an `EventOccurrence.id` or a master recurrence ID if that feature is added)
    *   `ticket_title` (Varchar)
    *   `original_ticket_price` (Integer - from `ticket_price` in schema, representing TicketDefinition price before discounts/at booking time, in smallest currency unit)
    *   `event_category_name` (Varchar - from `event_category` in schema)
    *   `customer_name` (Varchar)
    *   `customer_email` (Varchar)
    *   `currency_code` (Varchar(3) - from `currency` in schema, ISO 4217)
    *   `payment_method_used` (Varchar - from `payment_type` in schema)

### TicketDefinition
Defines a type of ticket that can be sold for an Event. The `default_max_check_ins` is copied to `Booking.max_allowed_check_ins` upon sale. Prices are stored as integers in the smallest currency unit (e.g., cents).
*   `id` (Primary Key)
*   `event_id` (Foreign Key to Event)
*   `title` (translatable, varchar(64) - corresponds to user schema `title`)
*   `description` (translatable, varchar(512), nullable - detailed info about the ticket)
*   `price` (Integer - price in smallest currency unit, e.g., cents)
*   `quantity` (Integer - total number of this ticket type available for sale)
*   `status` (Enum/TinyInt, e.g., 1:Active, 0:Inactive, 2:SoldOut - based on user schema `status`)
*   `booking_limit_per_customer` (Integer, nullable - max this ticket type one customer can buy, from user schema `customer_limit`)
*   `default_max_check_ins` (Integer, default 1 - default number of times a ticket of this type can be used for entry)
*   `created_at`
*   `updated_at`
    *Note: Tax information is mentioned in the UI ('Taxes -- Select --'). This would likely involve a separate `Tax` entity and a pivot table `ticket_definition_tax`.*

### Category
Used to categorize events.
*   `id` (Primary Key)
*   `name` (translatable)
*   `slug` (translatable)
*   `parent_id` (Foreign Key to self, for subcategories, optional)
*   `created_at`
*   `updated_at`

### CheckInLog
Records each attempt or successful instance of a ticket being checked in. Allows for multi-use tickets and detailed audit trails.
*   `id` (Primary Key, Integer)
*   `booking_id` (Foreign Key to `Booking.id`, indexed)
*   `check_in_timestamp` (Datetime, default current timestamp)
*   `method` (Enum/Varchar, e.g., "QR_SCAN", "MANUAL_ENTRY", "API_INTEGRATION")
*   `device_identifier` (Varchar, nullable - e.g., UUID or serial number of the scanning device)
*   `location_description` (Varchar, nullable - e.g., "Main Entrance - Gate A", "Workshop Room 3 Checkpoint")
*   `operator_user_id` (Foreign Key to `User.id`, nullable - The staff member or system that processed the check-in)
*   `status` (Enum/Varchar, e.g., "SUCCESSFUL", "FAILED_ALREADY_USED", "FAILED_MAX_USES_REACHED", "FAILED_INVALID_CODE", "FAILED_NOT_YET_VALID", "FAILED_EXPIRED")
*   `notes` (Text, nullable - Any additional remarks, e.g., manual override reason)
*   `created_at`

### Tag
For tagging events.
*   `id`
