CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "stripe_id" varchar,
  "pm_type" varchar,
  "pm_last_four" varchar,
  "trial_ends_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE IF NOT EXISTS "site_settings"(
  "id" integer primary key autoincrement not null,
  "key" varchar not null,
  "value" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "site_settings_key_unique" on "site_settings"("key");
CREATE TABLE IF NOT EXISTS "countries"(
  "id" integer primary key autoincrement not null,
  "name" text not null,
  "iso_code_2" varchar not null,
  "iso_code_3" varchar not null,
  "phone_code" varchar,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "countries_iso_code_2_unique" on "countries"("iso_code_2");
CREATE UNIQUE INDEX "countries_iso_code_3_unique" on "countries"("iso_code_3");
CREATE TABLE IF NOT EXISTS "states"(
  "id" integer primary key autoincrement not null,
  "country_id" integer not null,
  "name" text not null,
  "code" varchar,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("country_id") references "countries"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "venues"(
  "id" integer primary key autoincrement not null,
  "name" text not null,
  "description" text,
  "slug" varchar not null,
  "organizer_id" integer,
  "address_line_1" text not null,
  "address_line_2" text,
  "city" text not null,
  "postal_code" varchar,
  "state_id" integer,
  "country_id" integer not null,
  "latitude" numeric,
  "longitude" numeric,
  "contact_email" varchar,
  "contact_phone" varchar,
  "website_url" varchar,
  "seating_capacity" integer,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organizer_id") references "users"("id") on delete set null,
  foreign key("state_id") references "states"("id") on delete set null,
  foreign key("country_id") references "countries"("id") on delete cascade
);
CREATE UNIQUE INDEX "venues_slug_unique" on "venues"("slug");
CREATE TABLE IF NOT EXISTS "categories"(
  "id" integer primary key autoincrement not null,
  "name" text not null,
  "slug" varchar not null,
  "parent_id" integer,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("parent_id") references "categories"("id") on delete cascade
);
CREATE UNIQUE INDEX "categories_slug_unique" on "categories"("slug");
CREATE TABLE IF NOT EXISTS "media"(
  "id" integer primary key autoincrement not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "uuid" varchar,
  "collection_name" varchar not null,
  "name" varchar not null,
  "file_name" varchar not null,
  "mime_type" varchar,
  "disk" varchar not null,
  "conversions_disk" varchar,
  "size" integer not null,
  "manipulations" text not null,
  "custom_properties" text not null,
  "generated_conversions" text not null,
  "responsive_images" text not null,
  "order_column" integer,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "media_model_type_model_id_index" on "media"(
  "model_type",
  "model_id"
);
CREATE UNIQUE INDEX "media_uuid_unique" on "media"("uuid");
CREATE INDEX "media_order_column_index" on "media"("order_column");
CREATE TABLE IF NOT EXISTS "tags"(
  "id" integer primary key autoincrement not null,
  "name" text not null,
  "slug" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "tags_slug_unique" on "tags"("slug");
CREATE TABLE IF NOT EXISTS "events"(
  "id" integer primary key autoincrement not null,
  "organizer_id" integer not null,
  "category_id" integer not null,
  "name" text not null,
  "slug" text not null,
  "description" text not null,
  "short_summary" text,
  "event_status" varchar not null default 'draft',
  "visibility" varchar not null default 'private',
  "is_featured" tinyint(1) not null default '0',
  "contact_email" varchar,
  "contact_phone" varchar,
  "website_url" varchar,
  "social_media_links" text,
  "youtube_video_id" varchar,
  "cancellation_policy" text,
  "meta_title" text,
  "meta_description" text,
  "meta_keywords" text,
  "published_at" datetime,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organizer_id") references "users"("id") on delete cascade,
  foreign key("category_id") references "categories"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "events_event_status_index" on "events"("event_status");
CREATE INDEX "events_visibility_index" on "events"("visibility");
CREATE INDEX "events_is_featured_index" on "events"("is_featured");
CREATE TABLE IF NOT EXISTS "event_occurrences"(
  "id" integer primary key autoincrement not null,
  "event_id" integer not null,
  "venue_id" integer,
  "name" text,
  "description" text,
  "start_at" varchar,
  "end_at" varchar,
  "start_at_utc" datetime,
  "end_at_utc" datetime,
  "timezone" varchar not null default 'Asia/Hong_Kong',
  "is_online" tinyint(1) not null default '0',
  "online_meeting_link" varchar,
  "status" varchar not null default 'scheduled',
  "capacity" integer,
  "max_tickets_per_user" integer default '10',
  "parent_occurrence_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("event_id") references "events"("id") on delete cascade,
  foreign key("venue_id") references "venues"("id") on delete set null,
  foreign key("parent_occurrence_id") references "event_occurrences"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "event_occurrences_start_at_utc_index" on "event_occurrences"(
  "start_at_utc"
);
CREATE INDEX "event_occurrences_end_at_utc_index" on "event_occurrences"(
  "end_at_utc"
);
CREATE TABLE IF NOT EXISTS "ticket_definitions"(
  "id" integer primary key autoincrement not null,
  "name" text not null,
  "description" text,
  "price" integer not null,
  "total_quantity" integer,
  "availability_window_start" varchar,
  "availability_window_end" varchar,
  "availability_window_start_utc" datetime,
  "availability_window_end_utc" datetime,
  "min_per_order" integer not null default '1',
  "max_per_order" integer,
  "status" varchar not null default 'active',
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "currency" varchar not null default 'USD'
);
CREATE INDEX "ticket_definitions_availability_window_start_utc_index" on "ticket_definitions"(
  "availability_window_start_utc"
);
CREATE INDEX "ticket_definitions_availability_window_end_utc_index" on "ticket_definitions"(
  "availability_window_end_utc"
);
CREATE TABLE IF NOT EXISTS "event_occurrence_ticket_definition"(
  "event_occurrence_id" integer not null,
  "ticket_definition_id" integer not null,
  "quantity_for_occurrence" integer,
  "price_override" integer,
  "availability_status" varchar default 'available',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("event_occurrence_id") references "event_occurrences"("id") on delete cascade,
  foreign key("ticket_definition_id") references "ticket_definitions"("id") on delete cascade,
  primary key("event_occurrence_id", "ticket_definition_id")
);
CREATE TABLE IF NOT EXISTS "event_tag"(
  "event_id" integer not null,
  "tag_id" integer not null,
  foreign key("event_id") references "events"("id") on delete cascade,
  foreign key("tag_id") references "tags"("id") on delete cascade,
  primary key("event_id", "tag_id")
);
CREATE TABLE IF NOT EXISTS "transactions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer,
  "total_amount" integer not null,
  "currency" varchar not null,
  "status" varchar not null default 'pending',
  "payment_gateway" varchar,
  "payment_gateway_transaction_id" varchar,
  "payment_intent_id" varchar,
  "notes" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "transactions_payment_gateway_transaction_id_unique" on "transactions"(
  "payment_gateway_transaction_id"
);
CREATE INDEX "transactions_payment_intent_id_index" on "transactions"(
  "payment_intent_id"
);
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" varchar not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "users_stripe_id_index" on "users"("stripe_id");
CREATE TABLE IF NOT EXISTS "subscriptions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "type" varchar not null,
  "stripe_id" varchar not null,
  "stripe_status" varchar not null,
  "stripe_price" varchar,
  "quantity" integer,
  "trial_ends_at" datetime,
  "ends_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "subscriptions_user_id_stripe_status_index" on "subscriptions"(
  "user_id",
  "stripe_status"
);
CREATE UNIQUE INDEX "subscriptions_stripe_id_unique" on "subscriptions"(
  "stripe_id"
);
CREATE TABLE IF NOT EXISTS "subscription_items"(
  "id" integer primary key autoincrement not null,
  "subscription_id" integer not null,
  "stripe_id" varchar not null,
  "stripe_product" varchar not null,
  "stripe_price" varchar not null,
  "quantity" integer,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "subscription_items_subscription_id_stripe_price_index" on "subscription_items"(
  "subscription_id",
  "stripe_price"
);
CREATE UNIQUE INDEX "subscription_items_stripe_id_unique" on "subscription_items"(
  "stripe_id"
);
CREATE TABLE IF NOT EXISTS "check_in_logs"(
  "id" integer primary key autoincrement not null,
  "booking_id" integer not null,
  "check_in_timestamp" datetime not null default CURRENT_TIMESTAMP,
  "method" varchar not null default 'QR_SCAN',
  "device_identifier" varchar,
  "location_description" varchar,
  "operator_user_id" integer,
  "status" varchar not null,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("booking_id") references "bookings"("id") on delete cascade,
  foreign key("operator_user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "bookings"(
  "id" integer primary key autoincrement not null,
  "booking_number" varchar not null,
  "transaction_id" integer not null,
  "ticket_definition_id" integer not null,
  "quantity" integer not null default('1'),
  "price_at_booking" integer not null,
  "currency_at_booking" varchar not null,
  "status" varchar not null default('confirmed'),
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "qr_code_identifier" varchar,
  "max_allowed_check_ins" integer not null default('1'),
  "event_id" integer,
  foreign key("ticket_definition_id") references ticket_definitions("id") on delete cascade on update no action,
  foreign key("transaction_id") references transactions("id") on delete cascade on update no action
);
CREATE UNIQUE INDEX "bookings_booking_number_unique" on "bookings"(
  "booking_number"
);
CREATE UNIQUE INDEX "bookings_qr_code_identifier_unique" on "bookings"(
  "qr_code_identifier"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_05_17_025458_create_permission_tables',1);
INSERT INTO migrations VALUES(5,'2025_05_17_033917_create_site_settings_table',1);
INSERT INTO migrations VALUES(6,'2025_05_17_063459_create_countries_table',1);
INSERT INTO migrations VALUES(7,'2025_05_17_063729_create_states_table',1);
INSERT INTO migrations VALUES(8,'2025_05_17_064009_create_venues_table',1);
INSERT INTO migrations VALUES(9,'2025_05_17_065551_create_categories_table',1);
INSERT INTO migrations VALUES(10,'2025_05_17_134954_create_media_table',1);
INSERT INTO migrations VALUES(11,'2025_05_18_015646_create_tags_table',1);
INSERT INTO migrations VALUES(12,'2025_05_18_022422_create_events_table',1);
INSERT INTO migrations VALUES(13,'2025_05_18_022631_create_event_occurrences_table',1);
INSERT INTO migrations VALUES(14,'2025_05_18_022728_create_ticket_definitions_table',1);
INSERT INTO migrations VALUES(15,'2025_05_18_043609_create_event_occurrence_ticket_definition_pivot_table',1);
INSERT INTO migrations VALUES(16,'2025_05_18_045420_modify_venues_table_remove_old_image_columns',1);
INSERT INTO migrations VALUES(17,'2025_05_18_051348_create_event_tag_pivot_table',1);
INSERT INTO migrations VALUES(18,'2025_05_19_014520_add_currency_to_ticket_definitions_table',1);
INSERT INTO migrations VALUES(19,'2025_05_22_074129_create_transactions_table',1);
INSERT INTO migrations VALUES(20,'2025_05_22_074135_create_bookings_table',1);
INSERT INTO migrations VALUES(21,'2025_05_22_112631_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(22,'2025_05_22_115650_create_customer_columns',1);
INSERT INTO migrations VALUES(23,'2025_05_22_115651_create_subscriptions_table',1);
INSERT INTO migrations VALUES(24,'2025_05_22_115652_create_subscription_items_table',1);
INSERT INTO migrations VALUES(25,'2025_05_26_025403_create_check_in_logs_table',2);
INSERT INTO migrations VALUES(26,'2025_05_26_030136_add_checkin_fields_to_bookings_table',3);
