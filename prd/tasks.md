| Task ID | Description | Complexity | Dependencies | Status  | Remarks                                                              |
|---------|-------------|------------|--------------|---------|----------------------------------------------------------------------|
| **SETUP & CORE** | | | | | |
| SU-001  | Setup Laravel project, basic configuration, .env files | Medium     |              | Done    | Includes initial Vite setup for frontend if not already done.        |
| SU-002  | Implement User Model & Authentication (Platform Admin, Organizer, General User roles) | Medium     | SU-001       | Done    | Consider Spatie/laravel-permission or similar for roles.             |
| SU-003  | Setup multi-language support (middleware, translation files for en, zh-TW, zh-CN) | Medium     | SU-001       | Done    | Include helper functions for easy translation.                       |
| SU-004  | Define base DTOs, Actions, Service class structures as per SOLID. | Low        | SU-001       | Done    | Establish conventions early.                                         |
| SU-005  | Implement Site Setting Entity (Model, Migration, basic CRUD for Admin) | Medium     | SU-002       | Done    | Key-value store for site-wide configurations. Translatable values.   |
| **COUNTRY & STATE/PROVINCE** | | | | | |
| LOC-001 | Create Country Entity (Model, Migration) & Seeder for initial data | Medium     | SU-001       | Done       | Include name (translatable), ISO codes, phone code.                  |
| LOC-002 | Create State/Province Entity (Model, Migration) & Seeder for initial data | Medium     | LOC-001      | Done       | Linked to Country. Include name (translatable), code.                |
| **VENUE** | | | | | |
| VEN-001 | Create Venue Entity (Model, Migration) | High       | LOC-002      | Done | Fields: name (translatable), address components, country_id, lat/long, capacity, contacts, website, description (translatable). `organizer_id` logic (public/private). Media/images via Spatie/MediaLibrary. See `project_overview.md`. |
| VEN-002 | Implement Venue DTOs, Actions/Services for CRUD operations | Medium     | VEN-001      | Done       | Adhere to thin controllers.                                          |
| VEN-003 | Develop Admin/Organizer UI for Venue Management (CRUD) | High       | VEN-002      | Done | Spatie Media Library for image uploads integrated. Map integration moved to VEN-004. |
| VEN-004 | Implement Map Integration for Venue Management (Epic)                       | High       | VEN-001    | Decomposed | Integrate a map for selecting and displaying latitude/longitude for venues. Sub-tasks define the breakdown. |
| VEN-004.1 | Research and select map library/API for Venue form (e.g., Leaflet, Google Maps) | Low  | VEN-004    | Pending    | Decision needed for implementation.                                    |
| VEN-004.2 | Implement basic map display component in Vue                                 | Medium     | VEN-004.1  | Pending    | Generic map component.                                                 |
| VEN-004.3 | Integrate map display into Venue create/edit forms (Admin UI)                 | Medium     | VEN-003, VEN-004.2 | Pending    | Display map, initially centered or with existing coordinates.        |
| VEN-004.4 | Add marker to map, draggable for lat/long selection                           | Medium     | VEN-004.3  | Pending    | User can pick location via map.                                        |
| VEN-004.5 | Update form's lat/long fields from map marker position                        | Low        | VEN-004.4  | Pending    | Sync map selection to form.                                          |
| VEN-004.6 | Set map marker based on form's existing lat/long on load                      | Low        | VEN-004.3  | Pending    | Show existing location on map load.                                  |
| **CATEGORY** | | | | | |
| CAT-001 | Create Category Entity (Model, Migration) for events | Medium     | SU-001       | Done       | Name (translatable), slug, parent_id for hierarchy.                  |
| CAT-002 | Implement Category DTOs, Actions/Services for CRUD | Low        | CAT-001      | Done       |                                                                      |
| CAT-003 | Develop Admin UI for Category Management (CRUD) | Medium     | CAT-002      | Done | Include hierarchical management if subcategories are used.         |
| **TAG** | | | | | |
| TAG-001 | Create Tag Entity (Model, Migration) for events | Low        | SU-001       | Done | Name (translatable), slug. Pivot table `event_tag`.                   |
| TAG-002 | Implement Tag DTOs, Actions/Services for CRUD | Low        | TAG-001      | Done |                                                                      |
| TAG-003 | Develop Admin UI for Tag Management (CRUD)  | Medium     | TAG-002      | Done |                                                                      |
| **EVENT** | | | | | |
| EVT-001 | Create Event Entity (Model, Migration) | High       | CAT-001, TAG-001, SU-002 | Done       | All fields as per overview. Translatable fields. Many-to-many with Tag. |
| EVT-002 | Create EventOccurrence Entity (Model, Migration) | Medium     | EVT-001, VEN-001 | Done       | Model: `EventOccurrence`. Table: `event_occurrences`. Links to `Event` (`event_id`) and `Venue` (`venue_id`). Fields: `name` (translatable, optional), `description` (translatable, optional), `start_at` (datetime), `end_at` (datetime), `timezone` (string), `status` (e.g., 'scheduled'), `is_online` (boolean), `online_meeting_link` (string, nullable), `capacity` (int, nullable), `metadata` (JSON, optional). |
| EVT-003 | Implement Event & EventOccurrence DTOs, Actions/Services (Epic) | High       | EVT-002      | Decomposed | Complex logic for creating/updating events with multiple occurrences. Sub-tasks focus on remaining action/service logic. |
| EVT-003.1 | Verify/Finalize `EventData` DTO for main event details                        | Low        | EVT-002      | Processing | Ensure DTO aligns with form and model.                               |
| EVT-003.2 | Verify/Finalize `EventOccurrenceData` DTO for occurrence details              | Low        | EVT-002      | Processing | Ensure DTO aligns with form and model.                               |
| EVT-003.3 | Implement/Refine `UpsertEventAction` for core Event details (excluding occurrences) | Medium   | EVT-003.1    | Processing | Handles create/update of Event model.                                |
| EVT-003.4 | Implement `ManageEventOccurrencesAction` for an Event                         | High       | EVT-002, EVT-003.2 | Pending  | Handles batch create, update, delete of occurrences.                |
| EVT-003.4.1 | Sub-Action: Create new `EventOccurrence` records from DTO array             | Medium     | EVT-003.4    | Pending    | Part of `ManageEventOccurrencesAction`.                              |
| EVT-003.4.2 | Sub-Action: Update existing `EventOccurrence` records from DTO array        | Medium     | EVT-003.4    | Pending    | Part of `ManageEventOccurrencesAction`, match by ID.                 |
| EVT-003.4.3 | Sub-Action: Delete `EventOccurrences` not present in submitted DTO array    | Medium     | EVT-003.4    | Pending    | Part of `ManageEventOccurrencesAction`, for a given event.           |
| EVT-003.5 | Implement `EventService` methods orchestrating `UpsertEventAction` and `ManageEventOccurrencesAction` | Medium | EVT-003.3, EVT-003.4 | Pending | e.g., `createEventWithOccurrences`, `updateEventWithOccurrences`. Transaction handling. |
| EVT-004 | Develop Organizer/Admin UI for Event Creation/Management - Details Tab | Medium     | EVT-003      | Pending | Based on screenshot.                                                 |
| EVT-005 | Develop Organizer/Admin UI: Event Occurrences Tab (Epic)                    | High       | EVT-003      | Decomposed | UI for managing multiple EventOccurrences.                             |
| EVT-005.1 | Design UI layout for Event Occurrences tab                                  | Low        | EVT-005      | Pending    | List view, add/edit/delete controls for occurrences.                 |
| EVT-005.2 | Create Vue component: `EventOccurrenceFormRow.vue`                          | Medium     | EVT-005.1, VEN-001 | Processing | Inputs for start/end datetime, venue, capacity, status, online details. Includes date/time pickers, venue selector. |
| EVT-005.3 | Implement dynamic list management for `EventOccurrenceFormRow` components     | Medium     | EVT-005.2    | Pending    | Add new row, remove row, edit existing row in UI.                    |
| EVT-005.4 | Integrate `EventOccurrenceFormRow` list with main Event form data           | Medium     | EVT-004, EVT-005.3 | Pending    | Bind to `form.occurrences` array for submission.                   |
| EVT-005.5 | Ensure data from occurrences tab is correctly structured for submission to `EventService` | Medium   | EVT-005.4, EVT-003 | Pending    | Align with `EventOccurrenceData` DTO structure.                    |
| EVT-005.6 | Implement Ticket Association in Event Occurrence Edit View (Epic) | High | EVT-005.2, TCKD-004 | Decomposed | UI and logic for associating TicketDefinitions with an EventOccurrence. Sub-tasks define breakdown. |
| EVT-005.6.1 | Design UI for TicketDef selection/creation within EventOccurrenceFormRow | Low    | EVT-005.6 | Pending    | Modal or inline section in occurrence form to manage tickets.          |
| EVT-005.6.2 | Vue Component: `TicketDefinitionSelector.vue` for EventOccurrence form | Medium | EVT-005.6.1, TCKD-001 | Processing | Component to list, search, and select existing TicketDefinitions (event-wide or all). |
| EVT-005.6.3 | Vue Component: `TicketDefinitionMiniForm.vue` for quick creation | Medium | EVT-005.6.1, TCKD-003 | Processing | A small form (modal view preferred) to create a new TicketDefinition linked to the current Event. |
| EVT-005.6.4 | Integrate `TicketDefinitionSelector` & `TicketDefinitionMiniForm` into `EventOccurrenceFormRow.vue` | Medium | EVT-005.2, EVT-005.6.2, EVT-005.6.3 | Processing | Allow user to add/remove TicketDefinitions for the occurrence.          |
| EVT-005.6.5 | Data Handling: Manage `ticket_definitions` array in `EventOccurrenceData` DTO | Medium | EVT-003.2, TCKD-001.1 | Pending | Occurrence DTO should handle an array of associated ticket definition IDs with pivot data (qty, price_override). |
| EVT-005.6.6 | Backend: Update `ManageEventOccurrencesAction` to save ticket associations | Medium | EVT-003.4, TCKD-001.1, EVT-005.6.5 | Pending | Modify action to sync `event_occurrence_ticket_definition` pivot table based on DTO. |
| EVT-006 | Develop Organizer/Admin UI for Event Creation/Management - Media Tab | Medium     | EVT-003      | Pending | Thumbnail, poster, YouTube ID.                                       |
| EVT-007 | Develop Organizer/Admin UI for Event Creation/Management - SEO Tab | Medium     | EVT-003      | Done | Meta fields. (Integrated into Translatable Content tab)                                                        |
| EVT-008 | Develop Organizer/Admin UI for Event Creation/Management - Publish Tab | Medium     | EVT-003      | Pending | Publish/unpublish logic. Event tags.                               |
| **TICKET DEFINITION** | | | | | |
| TCKD-001| Create TicketDefinition Entity (Model, Migration) | Medium     | EVT-001      | Done       | Model: `TicketDefinition`. Table: `ticket_definitions`. Fields: `name` (translatable), `description` (translatable, optional), `price` (integer, smallest currency unit e.g., cents), `currency`, `total_quantity` (optional), `sale_starts_at` (datetime, optional), `sale_ends_at` (datetime, optional), `min_per_order` (int, default 1), `max_per_order` (int, optional), `status` (e.g., 'active', 'inactive'), `metadata` (JSON, optional). Note: `event_id` is removed; linked via EventOccurrences. |
| TCKD-001.1| Create `event_occurrence_ticket_definition` pivot table (Migration) | Medium     | EVT-002, TCKD-001 | Done | Links `EventOccurrence` with `TicketDefinition` (many-to-many). Fields: `event_occurrence_id`, `ticket_definition_id`, `quantity_for_occurrence` (int, optional), `price_override` (integer, smallest currency unit, optional), `availability_status` (string, e.g., 'available', 'sold_out', optional). |
| TCKD-002| Implement Tax Entity & `ticket_definition_tax` pivot (Model, Migration) (Placeholder for now) | Medium | TCKD-001     | Pending | Basic structure, full implementation later.                      |
| TCKD-003| Implement TicketDefinition DTOs, Actions/Services | Medium     | TCKD-001, TCKD-001.1 | Done | Include logic for managing associations with EventOccurrences.       |
| TCKD-004| Develop Organizer/Admin UI for TicketDefinition Management (CRUD within Event) | Medium | TCKD-003, EVT-004 | Pending | Based on "Create Ticket" modal. UI should allow associating TicketDefinitions with specific EventOccurrences (via `event_occurrence_ticket_definition` pivot) including per-occurrence quantity/price if applicable. |
| **ORDER & BOOKING** | | | | | |
| ORD-001 | Create Order Entity (Model, Migration) | Medium     | SU-002       | Pending | All fields as per overview. Integer amounts. Currency handling.      |
| ORD-002 | Create Booking Entity (Model, Migration) | High       | ORD-001, EVT-001, TCKD-001, SU-002 | Pending | All fields per overview. Integer amounts. `qr_code_identifier`, `max_allowed_check_ins`. Denormalized fields. |
| ORD-003 | Implement Monetary Helper Functions (Integer amounts, rounding, tax calc) | Medium     | SU-001       | Pending | Crucial for financial accuracy. Tax rounding up (ceiling).         |
| ORD-004 | Implement Order & Booking DTOs, Actions/Services for Booking Process | High       | ORD-003, ORD-002 | Pending | Core booking logic. Includes QR code generation.                     |
| ORD-005 | Implement "My Bookings" page for General Users | Medium     | ORD-004      | Pending | Display purchased tickets/bookings.                                  |
| ORD-006 | Develop Admin/Organizer UI for viewing Orders & Bookings | Medium     | ORD-004      | Pending | Search, filter, view details.                                        |
| **CHECK-IN** | | | | | |
| CHK-001 | Create CheckInLog Entity (Model, Migration) | Medium     | ORD-002      | Pending | All fields as per overview.                                          |
| CHK-002 | Implement Check-in Logic (Action/Service) | High       | CHK-001, ORD-002 | Pending | Validate QR, check `max_allowed_check_ins`, log to `CheckInLog`.     |
| CHK-003 | Develop Check-in Interface (Mobile-friendly Web App / API for native app) | High   | CHK-002      | Pending | For staff to scan QR codes.                                        |
| **FRONTEND - LANDING PAGE** | | | | | Based on reference image provided.                                                  |
| FE-LP-001 | Design & Implement Public Landing Page (Homepage) Structure                | High       | FE-001, CAT-001 | Processing | Overall page layout for `~/` route. Will aggregate multiple sections/components.         |
| FE-LP-002 | Implement Landing Page: Header Section                                      | Medium     | FE-LP-001    | Pending | Includes location selector and search bar.                                                  |
| FE-LP-003 | Implement Landing Page: Event Category Quick Links Section                  | Medium     | FE-LP-001, CAT-001 | Done | Display main event categories with icons. Reusable category link component.                |
| FE-LP-004 | Implement Landing Page: "Ticket Rush Information" Section                 | Medium     | FE-LP-001, EVT-001 | Pending | Displays a featured event for ticket sales.                                                 |
| FE-LP-005 | Implement Landing Page: "Upcoming Events" Section                         | High       | FE-LP-001, EVT-001 | Processing | Includes date filter and horizontally scrollable event cards. Uses Reusable Event Card.    |
| FE-LP-006 | Create Reusable Component: Event Card (for "Upcoming Events")               | Medium     | FE-LP-001    | Done | Component for displaying event image, title, price, tags. Style as in "Upcoming Events". |
| FE-LP-007 | Implement Landing Page: "More Events" Section (Listing Teaser)              | High       | FE-LP-001, EVT-001 | Processing | Includes filters and a list of events. Uses Reusable Event List Item.                    |
| FE-LP-008 | Create Reusable Component: Event List Item (for "More Events")              | Medium     | FE-LP-001    | Done | Component for displaying event image, category, title, date, venue, price. Style as in "More Events". |
| **FRONTEND VIEWS (Public)** | | | | | |
| FE-001  | Develop Event Listing Page (Public)         | Medium     | EVT-001      | Pending | Search, filter by category, date, etc.                             |
| FE-002  | Develop Event Detail Page (Public)          | Medium     | EVT-001, TCKD-001 | Processing | Show event info, occurrences, ticket types.                        |
| FE-003  | Implement Frontend Booking/Purchase Flow UI   | High       | ORD-004, FE-002 | Processing | User selects tickets, proceeds to (mock) payment.                  |
| **GENERAL** | | | | | |
| GEN-001 | Implement comprehensive Seeding for all relevant entities for testing | Medium   | ALL OTHERS   | Pending | Essential for development and testing.                               |
| GEN-002 | Setup basic API documentation (e.g., Swagger/OpenAPI) if APIs are planned | Medium |              | Pending |                                                                      |
| GEN-003 | Write Unit & Feature Tests for critical components (Actions, Services, Models) | High   | ALL OTHERS   | Pending | Ongoing task.                                                        |
| GEN-004 | Configure Linter (ESLint, Prettier already in package.json) and code formatting hooks | Low    | SU-001       | Pending | Ensure code quality.                                                 |
| **FEATURES & ENHANCEMENTS** | | | | | |
| FEAT-001| Integrate Rich Text Editor (TipTap) for Venue Description | Medium-High | VEN-003    | Done | Use TipTap. Support image upload/insert & YouTube embed. Consider other entities. |
| FEAT-001.1 | TipTap Core Setup & Basic Component | Medium      | -          | Done | Install @tiptap/vue-3, @tiptap/pm, @tiptap/starter-kit. Create RichTextEditor.vue. Basic v-model. |
| FEAT-001.2 | Custom Toolbar & Styling for TipTap | Medium-High | FEAT-001.1 | Done | Tailwind CSS toolbar with icon-based buttons for StarterKit actions. Add controls for font family, font size (headings/custom), and color. Style editor content. |
| FEAT-001.3 | YouTube Video Embedding for TipTap | Medium      | FEAT-001.2 | Done | Use @tiptap/extension-youtube. Add toolbar button for URL input. |
| FEAT-001.4 | Image Upload & Insertion for TipTap | High        | FEAT-001.2 | Done | Backend API endpoint for uploads. Frontend @tiptap/extension-image integration. |
| FEAT-001.5 | Integrate TipTap into Venue Forms | Medium      | VEN-003, FEAT-001.2 | Done | Replace textarea in Venue Create/Edit. Bind to form.description.{locale}. Update VenueData/Action if needed. |
| FEAT-001.6 | Server-Side Content Sanitization | Medium      | FEAT-001.5 | Done | Use HTMLPurifier in UpsertVenueAction for TipTap HTML output. |
| FEAT-002| Implement Role-Specific Dashboards | Medium-High | SU-002, admin.dashboard route | Pending | Design and implement different dashboard views/widgets based on user roles (e.g., Platform Admin, Organizer). |
| **FRONTEND - HOME PAGE CONTROLLERS** | | | | | Fetching data for the public landing page (Home.vue)                       |
| FE-HPC-001 | Create `CategoryController` to fetch event categories for landing page | Medium | CAT-001 | Superseded by FE-HPC-005 | Endpoint to serve categories for FE-LP-003. API route still exists but not used by Home.vue. |
| FE-HPC-002 | Create `FeaturedEventController` to fetch featured event for landing page | Medium | EVT-001 | On Hold | Logic to be part of `HomeController` (FE-HPC-005). Endpoint for "Ticket Rush" section (FE-LP-004). |
| FE-HPC-003 | Create `UpcomingEventsController` to fetch upcoming events for landing page | Medium | EVT-001 | On Hold | Logic to be part of `HomeController` (FE-HPC-005). Endpoint for "Upcoming Events" section (FE-LP-005). |
| FE-HPC-004 | Create `MoreEventsController` to fetch additional events for landing page | Medium | EVT-001 | On Hold | Logic to be part of `HomeController` (FE-HPC-005). Endpoint for "More Events" section (FE-LP-007), consider pagination. |
| FE-HPC-005 | Implement `HomeController` for landing page data via Inertia props | Medium | CAT-001, EVT-001 | Processing | Controller for `/` route to provide categories, featured event, upcoming events, more events to `Public/Home.vue`. |
