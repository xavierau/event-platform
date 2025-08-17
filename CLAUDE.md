# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Laravel 12 Event Platform** that follows **Domain-Driven Design (DDD)** principles within a modular monolithic architecture. The application is built for event management with support for multiple organizers, venues, ticketing, bookings, and user interactions.

## Development Methodology

### Test-Driven Development (TDD)
This project follows **strict TDD methodology** with modern Pest PHP framework:

**⚠️ CRITICAL**: Always run tests immediately after ANY code change
```bash
# ALWAYS use parallel testing for optimal performance (60% faster)
./vendor/bin/pest --parallel

# Run specific tests during development
./vendor/bin/pest tests/Feature/EventServiceTest.php --parallel
./vendor/bin/pest --filter="can create event" --parallel
```

**Performance benchmarks:**
- Parallel execution: ~1.7 seconds
- Sequential execution: ~4.7 seconds
- Always use `--parallel` flag for development

### Code Protection Rules
**⚠️ NEVER modify existing test code unless explicitly requested**
- Preserve all files in `tests/` directory
- Only add new tests or fix intentional API changes
- Test files, factories, and seeders are protected

## Key Development Commands

### PHP/Laravel Commands
```bash
# Run application in development mode
composer run dev  # Starts server, queue, logs, and vite concurrently

# Run application with SSR
composer run dev:ssr

# Run tests (ALWAYS use parallel)
./vendor/bin/pest --parallel

# Run tests with coverage
./vendor/bin/pest --parallel --coverage

# Laravel commands
php artisan serve
php artisan queue:work
php artisan migrate
php artisan db:seed
```

### Frontend Commands
```bash
# Development
npm run dev

# Build for production
npm run build

# Build with SSR
npm run build:ssr

# Linting and formatting
npm run lint
npm run format
npm run format:check
```

## Architecture Overview

### Core Domain Entities
- **Event**: Main event entity with translatable fields, media support
- **EventOccurrence**: Specific instances of events with venues and timing
- **Venue**: Event locations (public or organizer-specific)
- **Category**: Hierarchical event categorization
- **Tag**: Event tagging system
- **TicketDefinition**: Ticket types with pricing and availability
- **Booking**: User ticket purchases with QR codes
- **Transaction**: Financial transaction records
- **Organizer**: Event organizer entities with user teams
- **User**: Platform users with role-based permissions

### Modular Structure
The application uses modules for specific features:
- **Wallet**: User points and transactions (`app/Modules/Wallet/`)
- **CMS**: Content management (`app/Modules/CMS/`)
- **Coupon**: Coupon system (`app/Modules/Coupon/`)
- **Membership**: User membership levels (`app/Modules/Membership/`)

### Architectural Patterns
- **Thin Controllers**: Delegate to Services and Actions
- **Actions**: Single-responsibility classes (`app/Actions/`)
- **Services**: Business logic orchestration (`app/Services/`)
- **DTOs**: Type-safe data transfer objects using `spatie/laravel-data`
- **Policies**: Authorization rules (`app/Policies/`)

## Key Technologies

### Backend Stack
- **Laravel 12**: PHP framework
- **Pest PHP**: Modern testing framework with parallel execution
- **Spatie Laravel Data**: Type-safe DTOs with validation
- **Spatie Laravel Translatable**: Multi-language content
- **Spatie Laravel Permission**: Role-based access control
- **Spatie Laravel Media Library**: File and media management
- **Laravel Sanctum**: API authentication
- **Laravel Cashier**: Stripe integration for payments

### Frontend Stack
- **Vue.js 3**: Progressive JavaScript framework
- **Inertia.js**: Modern monolithic SPA experience
- **TypeScript**: Type safety for frontend
- **Tailwind CSS**: Utility-first CSS framework
- **TipTap**: Rich text editor with image/YouTube support
- **Vite**: Build tool and dev server

### Development Tools
- **ESLint & Prettier**: Code formatting and linting
- **Vue TSC**: TypeScript checking for Vue components
- **Laravel Pint**: PHP code styling
- **Pest**: Testing framework with elegant syntax

## Database Design

### Multi-language Support
Translatable fields use JSON columns with locale keys:
```json
{
  "en": "English content",
  "zh-TW": "繁體中文內容",
  "zh-CN": "简体中文内容"
}
```

### Key Relationships
- Events belong to Organizers and Categories
- EventOccurrences belong to Events and Venues
- TicketDefinitions connect to EventOccurrences via pivot table
- Bookings link Users to TicketDefinitions and EventOccurrences
- Users can be members of multiple Organizers with different roles

## Important Validation Patterns

### DTO Validation
```php
// For translatable fields in DTOs
public readonly array $name; // e.g., ['en' => 'Name', 'zh-TW' => '名稱']

// Validation rules for translatable fields
public static function rules(): array
{
    return [
        'name.en' => 'required|string|max:255',
        'name.zh-TW' => 'nullable|string|max:255',
    ];
}
```

### Model Configuration for Translatable Fields
```php
use Spatie\Translatable\HasTranslations;

class Event extends Model
{
    use HasTranslations;
    
    public array $translatable = ['name', 'description'];
    
    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];
}
```

## Common Development Workflows

### Creating a New Entity
1. **Write Tests First**: Create unit and feature tests
2. **Create Migration**: Database schema with proper indexes
3. **Create Model**: With relationships, scopes, and casts
4. **Create Factory**: For test data generation
5. **Create DTO**: Using `spatie/laravel-data`
6. **Create Action**: Single-responsibility class (e.g., `UpsertEntityAction`)
7. **Create Service**: Orchestrate actions and provide API
8. **Create Controller**: Thin controllers using services
9. **Create Policy**: Authorization rules
10. **Create Frontend**: Vue components for CRUD operations

### Working with Translatable Content
Always structure translatable data as associative arrays with locale keys when passing to DTOs:
```php
$eventData = EventData::from([
    'name' => ['en' => 'Event Name', 'zh-TW' => '活動名稱'],
    'description' => ['en' => 'Description', 'zh-TW' => '描述'],
    // other fields...
]);
```

### Frontend Form Handling
Use Inertia's `form.transform()` to structure data properly before submission:
```javascript
form.transform((data) => ({
    ...data,
    name: {
        en: data.name_en,
        'zh-TW': data.name_zh_tw,
    }
}))
```

## Security Considerations

- All user input is validated through DTOs
- Rich text content is sanitized using HTMLPurifier
- QR codes use secure random identifiers
- Role-based authorization throughout the application
- CSRF protection on all forms
- SQL injection protection through Eloquent ORM

## File Upload Handling

Images and media are handled through Spatie Media Library:
```php
// In actions
$model->addMediaFromRequest('image')
    ->toMediaCollection('collection_name');

// In DTOs
public readonly ?UploadedFile $uploaded_image;
```

## Task Management

Current development tasks are tracked in `prd/tasks.md`. When working on tasks:
- Update task status to "Processing" when starting
- Update to "Done" when completed
- Always run full test suite before marking complete

## Git Workflow

The project follows **Git Flow** branching strategy:
- `main`: Production-ready code
- `develop`: Primary development branch
- `feature/*`: New features from develop
- `hotfix/*`: Urgent fixes from main
- `release/*`: Release preparation

## Performance Considerations

- Use eager loading to prevent N+1 queries
- Implement caching for frequently accessed data
- Optimize database queries with proper indexes
- Use parallel test execution for faster feedback
- Minimize bundle size with code splitting

## Debugging and Troubleshooting

- Laravel Telescope is configured for debugging
- Use `php artisan pail` for real-time log monitoring
- Pest provides detailed test failure information
- Vue DevTools for frontend debugging
- Check `storage/logs/laravel.log` for errors

This EventPlatform is designed for scalability, maintainability, and developer productivity through consistent patterns, comprehensive testing, and modern development practices.
- there is a new mcp for the project which is Laravel Boost it accelerates AI-assisted development by providing the essential context and structure that AI needs to generate high-quality, Laravel-specific code. Use it proactively in this project