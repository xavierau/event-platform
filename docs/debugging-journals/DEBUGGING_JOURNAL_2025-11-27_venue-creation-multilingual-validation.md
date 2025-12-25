# Debugging Journal: Venue Creation Fails Due to Multilingual Data Transformation

**Date**: 2025-11-27
**Issue**: Cannot create Venue - validation errors on required locale fields
**Severity**: High
**Resolution Time**: ~15 minutes

---

## Problem Description

Users reported being unable to create venues in the admin panel. The form would fail with validation errors, particularly when translatable fields (name, city, address) had data in non-primary locales (zh-TW, zh-CN) but empty primary locale (en) fields.

### Symptoms
- Form submission fails silently or with cryptic validation errors
- Backend receives incomplete data structure for translatable fields
- Validation errors like "The name.en field is required" even when users expected the field to be optional

### Files Involved
| File | Role |
|------|------|
| `resources/js/pages/Admin/Venues/Create.vue` | Vue form component (BUG LOCATION) |
| `app/DataTransferObjects/VenueData.php` | DTO with validation rules |
| `app/Http/Controllers/Admin/VenueController.php` | Controller handling store action |

---

## Root Cause Analysis

The bug was in the `cleanTranslatableField` function within the Create.vue component (lines 40-49):

### Problematic Code

```javascript
// resources/js/pages/Admin/Venues/Create.vue (BEFORE FIX)
const submit = () => {
    const cleanTranslatableField = (field: any) => {
        if (!field || typeof field !== 'object') return field;
        const cleaned: any = {};
        for (const [locale, value] of Object.entries(field)) {
            if (value && typeof value === 'string' && value.trim() !== '') {
                cleaned[locale] = value;  // BUG: Only keeps non-empty values
            }
        }
        return Object.keys(cleaned).length > 0 ? cleaned : field;
    };

    const dataToSubmit = {
        ...form.data(),
        name: cleanTranslatableField(form.name),  // Removes empty 'en' key!
        // ... other fields
    };
    // ...
};
```

### Problem Flow
1. User enters data: `name: { en: '', 'zh-TW': '場地名稱', 'zh-CN': '' }`
2. `cleanTranslatableField` removes empty locale values
3. Result sent to backend: `name: { 'zh-TW': '場地名稱' }` (missing `en` key entirely)
4. Backend validation rule `'name.en' => ['required', 'string']` fails
5. Validation error: "The name.en field is required"

### Why This Happened
The developer intended to "clean up" empty strings to avoid validation issues, but this approach backfired. The backend validation expects the `en` key to exist (even if empty) so it can properly validate and return a meaningful error message.

---

## The Solution

### Fixed Code

```javascript
// resources/js/pages/Admin/Venues/Create.vue (AFTER FIX)
const submit = () => {
    form.post(route('admin.venues.store'), {
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
};
```

### What Changed
- Removed the entire `cleanTranslatableField` function
- Removed the `dataToSubmit` transformation object
- Now submits form data directly, matching the pattern used in the working Events module

### Why This Works
- Backend validation properly receives all locale keys
- Empty required fields trigger proper validation errors with clear messages
- The backend DTO (`VenueData.php`) handles validation correctly when it receives the complete data structure

---

## Troubleshooting Strategy

### Step 1: Reproduce and Identify
- Navigated to venue creation form
- Identified that the issue occurred with multilingual fields
- Suspected frontend data transformation based on CLAUDE.md guidance

### Step 2: Compare with Working Module
- Examined Events module (`resources/js/pages/Admin/Events/Create.vue`)
- Noted that Events does NOT use any `cleanTranslatableField` function
- Events submits data directly and works correctly

### Step 3: Trace Data Flow
- Identified the `cleanTranslatableField` function in Venues Create.vue
- Analyzed how it strips empty locale keys
- Confirmed this breaks backend validation expectations

### Step 4: Verify Backend Expectations
- Checked `VenueData.php` validation rules:
  ```php
  $rules["name.en"] = ["required", "string", "max:255"];
  $rules["name.zh-TW"] = ["nullable", "string", "max:255"];
  ```
- Confirmed primary locale (`en`) is required, secondary locales are nullable

### Step 5: Apply Fix and Test
- Removed the problematic function
- Ran tests: `./vendor/bin/pest tests/Feature/VenueCreationTest.php --parallel`
- All tests passed (2 passed, 7 assertions)

---

## Prevention Strategies

### Best Practices

1. **Follow Existing Patterns**: Always check how similar modules handle the same functionality before implementing custom solutions. The Events module should have been the reference.

2. **Don't Over-Engineer Frontend Transformations**: Let the backend handle validation. The backend DTO is designed to validate and transform data properly.

3. **Preserve Data Structure**: Never strip keys from objects being sent to the backend. Empty strings are valid and should be sent so validation can properly evaluate them.

4. **Test Edge Cases**: Test forms with:
   - Only primary locale filled
   - Only secondary locale filled
   - All locales filled
   - All locales empty

### Code Review Guidelines

When reviewing Vue/Inertia forms with multilingual fields:
- [ ] Does the form submit data directly without custom transformations?
- [ ] Are all locale keys preserved in the submitted data?
- [ ] Does the pattern match existing working modules (Events, etc.)?
- [ ] Is there any `cleanTranslatableField` or similar function? (RED FLAG)

### Testing Checklist

```bash
# Run venue-specific tests
./vendor/bin/pest tests/Feature/VenueCreationTest.php --parallel

# Run all venue tests
./vendor/bin/pest --filter="Venue" --parallel
```

---

## Key Takeaways

1. **Study Existing Patterns First**: The Events module was working correctly. Always reference working code before implementing new solutions.

2. **Backend Validation is Sufficient**: The Laravel/Spatie Data validation handles multilingual fields correctly. No frontend preprocessing is needed.

3. **Data Flow Debugging**: When validation fails unexpectedly, trace the data from frontend to backend. The bug is often in the transformation layer.

4. **Simple is Better**: The fix was to REMOVE code, not add it. The simplest solution (direct form submission) was the correct one.

5. **CLAUDE.md Wisdom**: "Route errors usually = missing data, not bad routes. Check props.model.id exists." This applies to validation errors too - missing data keys cause validation failures.

---

## Related Documentation

- [Spatie Laravel Data Validation](https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/validation)
- [Spatie Laravel Translatable](https://spatie.be/docs/laravel-translatable/v6/introduction)
- [Inertia.js Form Handling](https://inertiajs.com/forms)

---

## Files Modified

| File | Change |
|------|--------|
| `resources/js/pages/Admin/Venues/Create.vue` | Removed `cleanTranslatableField` function and simplified `submit()` |