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
| VEN-001 | Create Venue Entity (Model, Migration) | High       | LOC-002      | Done | All fields as per project_overview.md, inc. translatable fields, organizer_id logic (public/private), lat/long, images (JSON). |
| VEN-002 | Implement Venue DTOs, Actions/Services for CRUD operations | Medium     | VEN-001      | Done       | Adhere to thin controllers.                                          |
| VEN-003 | Develop Admin/Organizer UI for Venue Management (CRUD) | High       | VEN-002      | Processing | Include handling for image uploads, map integration for lat/long.    |
| **CATEGORY** | | | | | |
| CAT-001 | Create Category Entity (Model, Migration) for events | Medium     | SU-001       | Done       | Name (translatable), slug, parent_id for hierarchy.                  |
| CAT-002 | Implement Category DTOs, Actions/Services for CRUD | Low        | CAT-001      | Done       |                                                                      |
| CAT-003 | Develop Admin UI for Category Management (CRUD) | Medium     | CAT-002      | Done | Include hierarchical management if subcategories are used.         |
| **TAG** | | | | | |
| TAG-001 | Create Tag Entity (Model, Migration) for events | Low        | SU-001       | Pending | Name (translatable), slug. Pivot table `event_tag`.                   |
| TAG-002 | Implement Tag DTOs, Actions/Services for CRUD | Low        | TAG-001      | Pending |                                                                      |
| TAG-003 | Develop Admin UI for Tag Management (CRUD)  | Medium     | TAG-002      | Pending |                                                                      |
| **EVENT** | | | | | |
| EVT-001 | Create Event Entity (Model, Migration) | High       | CAT-001, TAG-001, SU-002 | Pending | All fields as per overview. Translatable fields. Many-to-many with Tag. |
| EVT-002 | Create EventOccurrence Entity (Model, Migration) | Medium     | EVT-001, VEN-001 | Pending | Links to Event, Venue. Handles date, time, online status.            |
| EVT-003 | Implement Event & EventOccurrence DTOs, Actions/Services | High       | EVT-002      | Pending | Complex logic for creating/updating events with multiple occurrences. |
| EVT-004 | Develop Organizer/Admin UI for Event Creation/Management - Details Tab | Medium     | EVT-003      | Pending | Based on screenshot.                                                 |
| EVT-005 | Develop Organizer/Admin UI for Event Creation/Management - Timings & Location (Occurrences) Tab | High | EVT-003      | Pending | UI for adding multiple date/time/venue pairs. Date picker.         |
| EVT-006 | Develop Organizer/Admin UI for Event Creation/Management - Media Tab | Medium     | EVT-003      | Pending | Thumbnail, poster, YouTube ID.                                       |
| EVT-007 | Develop Organizer/Admin UI for Event Creation/Management - SEO Tab | Medium     | EVT-003      | Pending | Meta fields.                                                         |
| EVT-008 | Develop Organizer/Admin UI for Event Creation/Management - Publish Tab | Medium     | EVT-003      | Pending | Publish/unpublish logic. Event tags.                               |
| **TICKET DEFINITION** | | | | | |
| TCKD-001| Create TicketDefinition Entity (Model, Migration) | Medium     | EVT-001      | Pending | All fields as per overview. Translatable title/desc. Integer price. `default_max_check_ins`. |
| TCKD-002| Implement Tax Entity & `ticket_definition_tax` pivot (Model, Migration) (Placeholder for now) | Medium | TCKD-001     | Pending | Basic structure, full implementation later.                      |
| TCKD-003| Implement TicketDefinition DTOs, Actions/Services | Medium     | TCKD-001     | Pending |                                                                      |
| TCKD-004| Develop Organizer/Admin UI for TicketDefinition Management (CRUD within Event) | Medium | TCKD-003, EVT-004 | Pending | Based on "Create Ticket" modal.                                    |
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
| **FRONTEND VIEWS (Public)** | | | | | |
| FE-001  | Develop Event Listing Page (Public)         | Medium     | EVT-001      | Pending | Search, filter by category, date, etc.                             |
| FE-002  | Develop Event Detail Page (Public)          | Medium     | EVT-001, TCKD-001 | Pending | Show event info, occurrences, ticket types.                        |
| FE-003  | Implement Frontend Booking/Purchase Flow UI   | High       | ORD-004, FE-002 | Pending | User selects tickets, proceeds to (mock) payment.                  |
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
