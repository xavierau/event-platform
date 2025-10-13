# GEMINI.md

**Laravel 12 Event Platform** - DDD modular monolithic architecture for event management with organizers, venues, ticketing, bookings, and multilingual support.

## Development Rules

### TDD with Pest PHP
```bash
# CRITICAL: Always run tests after ANY code change
./vendor/bin/pest --parallel  # 60% faster than sequential

# Run specific tests
./vendor/bin/pest tests/Feature/EventServiceTest.php --parallel
./vendor/bin/pest --filter="can create event" --parallel
```

**⚠️ NEVER modify existing test code unless explicitly requested**

### Key Commands
```bash
# Development
composer run dev          # Server + queue + logs + vite
npm run dev              # Frontend development
./vendor/bin/pest --parallel  # Run tests

# Production
npm run build
./vendor/bin/pint --dirty    # Format code before commits
```

## Architecture

**Stack**: Laravel 12 + Vue 3 + Inertia.js + TypeScript + Tailwind v4 + Pest PHP

**Patterns**: Thin Controllers → Services → Actions → DTOs (spatie/laravel-data)

**Modules**: `app/Modules/` (Wallet, CMS, Coupon, Membership, PromotionalModal)

## Multilingual Support

**Backend Model**:
```php
use Spatie\Translatable\HasTranslations;

class Event extends Model {
    use HasTranslations;
    public array $translatable = ['name', 'description'];
    protected $casts = ['name' => 'array', 'description' => 'array'];
}
```

**DTO Patterns**:
```php
$eventData = EventData::from([
    'name' => ['en' => 'Event Name', 'zh-TW' => '活動名稱'],
]);
```

**Frontend Forms**:
```javascript
// Use watchEffect() for reactive multilingual form initialization
watchEffect(() => {
    if (props.model) {
        form.defaults(props.model);
        form.reset();
    }
});
```

## Critical Debugging Lessons

### Data Flow Issues (Laravel + Inertia + Vue)
**Problem**: Frontend route/framework errors → actually missing data in DTO transformation
**Solution**: Always include essential fields manually:
```php
return Inertia::render('Page', [
    'model' => [
        'id' => $model->id,  // Always include ID for route parameters
        ...DTOData::from($model->toArray())->toArray(),
    ]
]);
```

### Debug Process
1. Console errors → trace backwards through data pipeline
2. Study existing patterns (Events module for multilingual forms)
3. Test complete flow: edit → submit → persist → display
4. **NEVER hardcode** - fix the data pipeline

**Route errors usually = missing data, not bad routes. Check `props.model.id` exists.**

## Laravel Boost Guidelines

### Package Versions
- PHP 8.3.23, Laravel 12, Inertia v2, Vue 3, Tailwind v4, Pest v3, Ziggy v2

### Core Rules
- Follow existing code conventions - check sibling files
- Use `php artisan make:*` commands for new files
- Use Laravel Boost MCP tools: `search-docs`, `tinker`, `database-query`
- Prefer Eloquent over raw queries, use eager loading
- Create Form Requests for validation, not inline validation
- Use `route()` function for URL generation
- Never use `env()` outside config files

### Frontend Rules
- Inertia components in `resources/js/Pages/`
- Use `<Link>` and `router.post()` for navigation/forms
- Single root elements in Vue components
- Use gap utilities for spacing (not margins)
- Support dark mode with `dark:` prefix

### Testing Rules
- Write Pest tests for all changes
- Use factories for test data
- Use specific assertions: `assertForbidden()` not `assertStatus(403)`
- Run minimal tests during development

### Git Flow
- `main` → `develop` → `feature/*`, `hotfix/*`, `release/*`

## Debugging Journal Requirement

**MANDATORY**: After fixing any bug or resolving a complex issue, create a debugging journal in `docs/debugging-journals/`:

### Journal Naming Convention
```
DEBUGGING_JOURNAL_YYYY-MM-DD_issue-description.md
```

### Required Sections
1. **🔍 Problem Description** - Symptoms, expected behavior, files involved
2. **🛠️ Root Cause Analysis** - Exact issue with code examples
3. **✅ The Solution** - Fixed code with before/after
4. **🔬 Troubleshooting Strategy** - Step-by-step investigation workflow
5. **🚫 Prevention Strategies** - Best practices, testing, code review guidelines
6. **📊 Time Investment** - Breakdown of resolution time and lessons learned
7. **🎯 Key Takeaways** - Critical lessons for future developers

### Purpose
- Help future developers identify similar problems
- Document troubleshooting strategies and workflows
- Build institutional knowledge for the development team
- Prevent recurring issues through documented prevention strategies

**Example**: `docs/debugging-journals/DEBUGGING_JOURNAL_2025-01-19_vue-input-binding.md`

**⚠️ Key Principles**: Study existing patterns, trace data flow systematically, never hardcode solutions, always test changes, **document debugging intelligence**.
- The development server is https://eventplatform.test and it's always runing with frontend hotreload