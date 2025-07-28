# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Laravel 12 Event Platform** built with a **modular monolithic architecture** following **Domain-Driven Design (DDD)** principles. The platform manages events, venues, ticketing, and user interactions with comprehensive multilingual support.

**Core Technologies:**
- **Backend:** Laravel 12, PHP 8.2+, SQLite/MySQL
- **Frontend:** Vue.js 3, Inertia.js, TypeScript, TailwindCSS 4
- **Testing:** Pest PHP with parallel execution
- **Key Libraries:** Spatie packages (data, translatable, permission, media-library)

## Common Development Commands

### Backend Commands
```bash
# Development server with all services
composer run dev

# Development with SSR
composer run dev:ssr

# Testing (ALWAYS use parallel for optimal performance)
./vendor/bin/pest --parallel

# Run specific test file
./vendor/bin/pest tests/Feature/EventServiceTest.php --parallel

# Run tests with coverage
./vendor/bin/pest --parallel --coverage

# Laravel Artisan commands
php artisan migrate
php artisan db:seed
php artisan queue:work
php artisan tinker
```

### Frontend Commands
```bash
# Development
npm run dev

# Production build
npm run build

# Build with SSR
npm run build:ssr

# Code formatting
npm run format
npm run format:check

# Linting
npm run lint
```

### Performance Testing
```bash
# Compare sequential vs parallel test performance
./test-performance.sh
```

## Architecture & Design Patterns

### Domain-Driven Design Structure

The application is organized into **six core domains** within a modular monolith:

1. **EventManagement** - Event lifecycle, publishing, promotions
2. **VenueManagement** - Physical/virtual locations and booking
3. **CategoryManagement** - Event classification and organization  
4. **Ticketing** - Ticket types, pricing, sales, inventory
5. **UserManagement** - Authentication, authorization, profiles
6. **Wishlist** - User preferences and recommendations

**Current Structure (transitioning to full DDD):**
```
app/
├── Actions/{Domain}/          # Single-responsibility business operations
├── Services/                  # Domain services and business logic
├── DataTransferObjects/       # Type-safe DTOs using spatie/laravel-data
├── Models/                    # Eloquent models with rich domain behavior
├── Modules/                   # Self-contained modules (CMS, Coupon, Membership, Wallet)
├── Http/Controllers/          # Thin HTTP layer
├── Policies/                  # Authorization and business rules
└── Enums/                     # Domain value objects and constants
```

### Key Architectural Patterns

**Action Pattern:** Single-responsibility classes for business operations
- Example: `UpsertEventAction`, `PublishEventAction`
- Located in `app/Actions/{Domain}/`

**Service Pattern:** Domain services orchestrating actions and business logic
- Example: `EventService`, `VenueService`
- Handle complex workflows and cross-cutting concerns

**Data Transfer Objects:** Type-safe data structures using `spatie/laravel-data`
- Example: `EventData`, `VenueData` 
- Provide validation, type safety, and clear contracts

**Repository Pattern:** Data access abstraction (planned)
- Will be implemented as interfaces in domain directories

## Test-Driven Development (TDD)

### Critical Testing Rules

**🚨 ALWAYS run tests immediately after ANY code change:**
```bash
./vendor/bin/pest --parallel
```

**Performance comparison:**
- Parallel execution: ~1.7 seconds (8 processes) 
- Sequential execution: ~4.7 seconds (single process)
- **Always use `--parallel` flag for 60%+ performance improvement**

### Test Organization
```
tests/
├── Feature/           # End-to-end workflows and HTTP tests
├── Unit/             # Isolated component tests 
├── Pest/             # Modern Pest syntax tests (preferred)
└── Support/          # Test utilities and datasets
```

### TDD Workflow
1. **Red:** Write failing test describing desired functionality
2. **Green:** Write minimal code to make test pass  
3. **Refactor:** Improve code while keeping tests green
4. **Always test immediately after changes**

### Test Execution Examples
```bash
# Run all tests in parallel (fastest)
./vendor/bin/pest --parallel

# Run specific domain tests
./vendor/bin/pest tests/Unit/Domains/EventManagement/ --parallel

# Run with pattern matching
./vendor/bin/pest --filter="can create event" --parallel

# Run with coverage
./vendor/bin/pest --parallel --coverage
```

## Multilingual & Translatable Content

### Spatie Translatable Integration

**Model Setup:**
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

**DTO Integration:**
```php
class EventData extends Data
{
    public function __construct(
        public readonly array $name,           // {'en': 'Name', 'zh-TW': '名稱'}
        public readonly array $description,    // {'en': 'Desc', 'zh-TW': '描述'}
    ) {}
    
    public static function rules(): array
    {
        return [
            'name.en' => 'required|string|max:255',
            'name.zh-TW' => 'nullable|string|max:255',
            'description.en' => 'required|string|min:50',
        ];
    }
}
```

**Frontend Form Structure:**
```javascript
// Inertia.js form data structure
form.transform(data => ({
    name: {
        en: data.name_en,
        'zh-TW': data.name_zh_tw
    },
    description: {
        en: data.description_en,
        'zh-TW': data.description_zh_tw
    }
}));
```

## Media Management

### Spatie Media Library Usage

**Model Configuration:**
```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use InteractsWithMedia;
    
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('portrait_poster')->singleFile();
        $this->addMediaCollection('gallery');
    }
}
```

**DTO and Action Integration:**
```php
class EventData extends Data
{
    public readonly ?UploadedFile $uploaded_portrait_poster;
    public readonly array $gallery_images;
    public readonly array $removed_gallery_ids;
}

// In Action
if ($eventData->uploaded_portrait_poster) {
    $event->addMedia($eventData->uploaded_portrait_poster)
        ->toMediaCollection('portrait_poster');
}
```

## Module System

### Self-Contained Modules

**Current Modules:**
- **CMS** - Content management and contact forms
- **Coupon** - Coupon generation, validation, redemption
- **Membership** - User membership levels and management  
- **Wallet** - Points system and transactions

**Module Structure:**
```
app/Modules/{ModuleName}/
├── Actions/           # Module-specific business operations
├── DataTransferObjects/ # Module DTOs
├── Models/           # Module entities
├── Services/         # Module business logic
├── Enums/           # Module-specific enums
├── Exceptions/       # Module-specific exceptions
└── README.md        # Module documentation
```

## Key Development Guidelines

### Code Quality Standards
- **SOLID Principles** - Single responsibility, dependency inversion
- **Clean Code** - Meaningful naming, small focused functions
- **DRY Principle** - Extract common functionality 
- **Test Coverage** - Comprehensive unit and feature tests

### Validation Strategy
1. **DTO Level** - Type safety and basic rules using `spatie/laravel-data`
2. **Domain Level** - Business rules in domain services
3. **HTTP Level** - Form request validation for user feedback

### Performance Guidelines
- Use eager loading to prevent N+1 queries
- Implement caching for expensive operations
- Always use `--parallel` for test execution
- Monitor query performance with Laravel Telescope

### Security Considerations
- Never commit secrets or API keys
- Use proper authorization policies
- Validate and sanitize all inputs
- Follow Laravel security best practices

## Environment Configuration

### PHP Configuration Notes
For complex forms with file uploads and translatable fields:
```ini
# php.ini settings for robust form handling
post_max_size = 64M
upload_max_filesize = 32M  
max_input_vars = 5000
memory_limit = 256M
```

### Laravel Configuration
- **Database:** SQLite for development, MySQL/PostgreSQL for production
- **Queue:** Uses database driver with `queue:listen` in development
- **Cache:** Array driver for testing, Redis recommended for production
- **Storage:** Local disk with Spatie Media Library for file management

## Common Development Patterns

### Creating New Entities (TDD Approach)
1. **Write Tests First** - Model, Service, and Controller tests
2. **Create Migration** - Database schema with translatable JSON fields
3. **Define Model** - With relationships, translatable fields, media collections
4. **Create DTO** - Type-safe data structure with validation rules
5. **Implement Action** - Single-responsibility business operation
6. **Build Service** - Orchestrate actions and provide clean API
7. **Add Controller** - Thin HTTP layer using service
8. **Create Frontend** - Vue components in `resources/js/Pages/Admin/{Entity}/`

### Working with Existing Code
- **Always read tests first** to understand expected behavior
- **Never modify existing tests** unless explicitly requested
- **Follow existing patterns** in the codebase
- **Use parallel testing** for fast feedback loops

### Git Workflow
The project follows **Git Flow** with:
- **main** - Production ready code
- **develop** - Integration branch for features  
- **feature/** - New feature development
- **hotfix/** - Critical production fixes

## AI Coding Assistance

### Zen MCP Server Tools Available
- `mcp_zen_codereview` - Professional code review and bug detection
- `mcp_zen_debug` - Expert debugging and root cause analysis
- `mcp_zen_precommit` - Pre-commit validation (ALWAYS use before commits)
- `mcp_zen_testgen` - Comprehensive test generation
- `mcp_zen_refactor` - Intelligent code refactoring suggestions
- `mcp_zen_analyze` - General code and file analysis

**Best Practice:** Use `mcp_zen_precommit` before every commit to catch issues early.

## Important Notes

### Configuration Files
- **composer.json** - PHP dependencies and dev scripts
- **package.json** - Node.js dependencies and build scripts  
- **vite.config.ts** - Frontend build configuration with alias support
- **Pest.json** - Parallel testing configuration
- **phpunit.xml** - Test environment settings

### Development Scripts
The `composer run dev` command starts all development services:
- Laravel development server  
- Queue worker with retry logic
- Laravel Pail for real-time logs
- Vite development server with HMR

### Domain Expertise Required
This is a complex event management platform with:
- Multi-tenant organizer system
- Complex ticketing with time-based availability
- Multilingual content management
- Media handling and image processing
- Real-time features and notifications

**Always refer to existing tests and documentation** before making changes to understand the business logic and requirements.