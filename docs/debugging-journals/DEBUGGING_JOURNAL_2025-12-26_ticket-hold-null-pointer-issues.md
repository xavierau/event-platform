# Debugging Journal: TicketHold Module Production Issues

**Date:** 2025-12-26
**Module:** TicketHold, PromotionalModals
**Severity:** Production Outage
**Time to Resolution:** ~60 minutes across 6 hotfixes

---

## 1. Problem Description

Three consecutive production errors occurred after deploying the TicketHold feature:

### Error 1: Missing Vue Pages
```
Page not found: ./pages/Admin/PromotionalModals/ShowAnalytics.vue
```

### Error 2: Null Event Relationship
```
Call to a member function getTranslations() on null
at TicketHoldController.php:222
```

### Error 3: Null start_at Field
```
Call to a member function toIso8601String() on null
at TicketHoldController.php:228
```

### Error 4: Prop Name Mismatch (per_page undefined)
```
TypeError: Cannot read properties of undefined (reading 'per_page')
at Index-B9mSfVht.js
```

### Error 5: Organizer Dropdown Empty
Organizer dropdown only showed "No organizer (Admin hold)" - actual organizers not displayed.

### Error 6: Event Occurrence Dropdown Empty
Event Occurrence dropdown only showed "Select an event occurrence" - no occurrences listed.

---

## 2. Root Cause Analysis

### Systemic Issues Identified

| Issue | Root Cause | Category |
|-------|------------|----------|
| Missing Vue pages | Routes/controllers created without corresponding Vue files | **Incomplete Implementation** |
| Null event relationships | Events deleted without cascade deleting occurrences | **Missing Database Constraints** |
| Null start_at values | EventOccurrence allows null start_at | **Missing Data Validation** |
| No defensive coding | Controller assumes all relationships exist | **Missing Null Safety** |
| Prop name mismatch | Controller sends `holds`, Vue expects `ticketHolds` | **Backend-Frontend Contract Violation** |
| Translatable field issue | `->get(['id', 'name'])` returns raw JSON, not translated | **ORM Misuse** |
| Data structure mismatch | Controller sends `value/label`, Vue expects `id/event.name` | **Backend-Frontend Contract Violation** |

### Why This Kept Happening

1. **No Pre-Deployment Checklist**: No verification that all Inertia routes have corresponding Vue pages
2. **Missing Foreign Key Constraints**: Database allows orphaned records
3. **No Null Safety in Queries**: Code assumes relationships always exist
4. **Insufficient Test Coverage**: Tests use factories that always create valid data
5. **No Backend-Frontend Contract Validation**: Prop names and data structures not verified between controller and Vue
6. **ORM Misuse**: Using `->get(['columns'])` with translatable fields breaks Spatie's translation trait
7. **Copy-Paste Errors**: Similar code patterns copied without verifying Vue component expectations

---

## 3. The Solutions Applied

### Hotfix 1: Missing Vue Pages
```bash
# Created placeholder pages
resources/js/pages/Admin/PromotionalModals/Analytics.vue
resources/js/pages/Admin/PromotionalModals/ShowAnalytics.vue
```

### Hotfix 2: Null Event Relationship
```php
// Before (UNSAFE)
private function buildOccurrencesQuery()
{
    $query = EventOccurrence::with('event');
    // ...
}

// After (SAFE)
private function buildOccurrencesQuery()
{
    $query = EventOccurrence::with('event')
        ->whereHas('event'); // Filter out orphaned occurrences
    // ...
}
```

### Hotfix 3: Null start_at Field
```php
// Before (UNSAFE)
$query = EventOccurrence::with('event')
    ->whereHas('event');

// After (SAFE)
$query = EventOccurrence::with('event')
    ->whereHas('event')
    ->whereNotNull('start_at');
```

---

## 4. Prevention Strategies

### A. Pre-Merge Checklist for Inertia Features

Before merging any feature that adds Inertia routes:

- [ ] Every `Inertia::render('Path/To/Page')` has a corresponding `.vue` file
- [ ] Run `grep -r "Inertia::render" app/ | cut -d"'" -f2 | sort -u` and verify each path exists
- [ ] Build frontend: `npm run build` succeeds without missing page errors

### B. Database Integrity Rules

1. **Always use foreign key constraints with CASCADE**:
```php
// In migrations
$table->foreignId('event_id')
    ->constrained()
    ->cascadeOnDelete(); // DELETE event → DELETE occurrences
```

2. **Create a data integrity check command**:
```php
// app/Console/Commands/CheckDataIntegrity.php
public function handle()
{
    // Check for orphaned occurrences
    $orphaned = EventOccurrence::whereDoesntHave('event')->count();
    if ($orphaned > 0) {
        $this->error("Found {$orphaned} orphaned event occurrences!");
    }

    // Check for null required fields
    $nullStartAt = EventOccurrence::whereNull('start_at')->count();
    if ($nullStartAt > 0) {
        $this->error("Found {$nullStartAt} occurrences with null start_at!");
    }
}
```

### C. Defensive Programming Standards

**Rule: Never assume relationships exist. Always use one of these patterns:**

```php
// Pattern 1: Filter in query (PREFERRED for lists)
EventOccurrence::whereHas('event')
    ->whereNotNull('start_at')
    ->get();

// Pattern 2: Null-safe operator (for single items)
$eventName = $occurrence->event?->getTranslation('name', $locale) ?? 'Unknown';

// Pattern 3: Early return with guard clause
if (!$occ || !$occ->event || !$occ->start_at) {
    return [];
}
```

### D. Test Coverage Requirements

Add tests for edge cases with missing/null data:

```php
// tests/Feature/TicketHold/TicketHoldControllerTest.php

it('handles orphaned event occurrences gracefully', function () {
    // Create occurrence, then delete event directly
    $occurrence = EventOccurrence::factory()->create();
    $occurrence->event->forceDelete();

    actingAs(adminUser())
        ->get(route('admin.ticket-holds.index'))
        ->assertOk(); // Should not crash
});

it('handles null start_at gracefully', function () {
    EventOccurrence::factory()->create(['start_at' => null]);

    actingAs(adminUser())
        ->get(route('admin.ticket-holds.index'))
        ->assertOk();
});
```

### E. CI/CD Pipeline Additions

Add these checks to the deployment pipeline:

```yaml
# .github/workflows/deploy.yml
- name: Verify Inertia Pages Exist
  run: |
    php artisan route:list --json | \
    jq -r '.[].action' | \
    grep -oP "Inertia::render\('\K[^']+" | \
    while read page; do
      if [ ! -f "resources/js/pages/${page}.vue" ]; then
        echo "ERROR: Missing Vue page: ${page}"
        exit 1
      fi
    done

- name: Check Data Integrity
  run: php artisan app:check-data-integrity --strict
```

---

## 5. Immediate Actions Taken

1. ✅ Created missing Vue pages (Analytics.vue, ShowAnalytics.vue)
2. ✅ Added `whereHas('event')` filter to exclude orphaned occurrences
3. ✅ Added `whereNotNull('start_at')` filter
4. ✅ Added null-safe operators where appropriate
5. ✅ Created this debugging journal

---

## 6. Recommended Follow-Up Tasks

| Priority | Task | Owner |
|----------|------|-------|
| HIGH | Add foreign key constraints to event_occurrences table | Backend Dev |
| HIGH | Create `app:check-data-integrity` Artisan command | Backend Dev |
| MEDIUM | Add Inertia page verification to CI pipeline | DevOps |
| MEDIUM | Clean up orphaned data in production | DBA |
| LOW | Add defensive coding to code review checklist | Tech Lead |

---

## 7. Key Takeaways

1. **Frontend-Backend Sync**: When adding Inertia routes, ALWAYS create the Vue page first
2. **Database Constraints**: Use foreign keys with CASCADE - they prevent orphaned data
3. **Defensive Programming**: Never trust that relationships exist - always verify
4. **Production Data is Messy**: Test data from factories is clean; production has edge cases
5. **Data Integrity Commands**: Create commands to detect issues BEFORE they cause errors

---

## 8. Related Files

- `app/Modules/TicketHold/Controllers/Admin/TicketHoldController.php`
- `app/Modules/PromotionalModal/Controllers/WebPromotionalModalController.php`
- `resources/js/pages/Admin/PromotionalModals/Analytics.vue`
- `resources/js/pages/Admin/PromotionalModals/ShowAnalytics.vue`
- `database/migrations/2025_12_24_073647_create_ticket_holds_tables.php`
