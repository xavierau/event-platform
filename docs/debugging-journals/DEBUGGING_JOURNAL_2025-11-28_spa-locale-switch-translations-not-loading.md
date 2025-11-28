# Debugging Journal: SPA Locale Switch Translations Not Loading

**Date**: 2025-11-28
**Issue**: Translations not loading when switching to Simplified Chinese in SPA
**Severity**: High
**Resolution Time**: ~30 minutes

---

## Problem Description

### Symptoms
When a user:
1. Loads the page initially in English or Traditional Chinese
2. The SPA initializes with that locale
3. User clicks the language switcher to change to Simplified Chinese (zh-CN)
4. **The translated content does NOT load** - translations remain in the original language or show translation keys

### Expected Behavior
All translated text should display in Simplified Chinese immediately after switching locales.

### Affected Files
- `resources/js/pages/Public/EventDetail.vue`
- Any page using `vue-i18n` translations

### Affected Translation Keys (examples)
- `events.description.title`
- `events.venue.view_map`
- `events.pricing.member_price_applied`
- `comments.title`
- `comments.leave_comment`
- `comments.be_first_comment`

---

## Root Cause Analysis

### The Bug Location
**File**: `resources/js/components/Shared/LocaleSwitcher.vue:43-58`

### What Was Happening

**1. Initial App Setup (`app.ts:24-31`)**

The app only loads translations for the **initial** locale during startup:

```typescript
const i18n = createI18n({
    locale,
    fallbackLocale: 'en',
    messages: {
        [locale]: translationsData.translations || {}  // Only ONE locale loaded!
    },
    legacy: false,
});
```

**2. LocaleSwitcher Component (Before Fix)**

```typescript
const switchLocale = (locale: string) => {
  // Updates vue-i18n locale value
  i18nLocale.value = locale;  // Changes locale but messages don't exist!

  // Makes POST to switch session locale on backend
  router.post('/locale/switch', { locale }, { ... });
};
```

**3. The Missing Step**

When switching locales, the code:
1. Updated `i18nLocale.value` to `zh-CN`
2. Sent POST to update backend session
3. **NEVER** fetched the new locale's translations from `/api/translations`
4. **NEVER** called `i18n.global.setLocaleMessage()` to load them

### Why It Failed

Vue-i18n's message store was only populated with the initial locale's translations. When switching to `zh-CN`:
- The locale value changed to `zh-CN`
- But `i18n.global.messages.value['zh-CN']` was `undefined`
- Vue-i18n couldn't find translations, so it displayed keys or fell back to English

---

## The Solution

### Files Modified

1. **`app/Http/Controllers/TranslationController.php`**
   - Added `locale` query parameter support
   - Validates locale against allowed list

2. **`resources/js/app.ts`**
   - Exposed i18n instance globally via `window.__VUE_I18N__`

3. **`resources/js/components/Shared/LocaleSwitcher.vue`**
   - Made `switchLocale` async
   - Fetches translations before switching
   - Calls `setLocaleMessage()` to register new translations

### Code Changes

**TranslationController.php**
```php
public function index(Request $request): JsonResponse
{
    // Allow explicit locale parameter for SPA locale switching
    $locale = $request->query('locale', app()->getLocale());

    // Validate locale is in allowed list
    $availableLocales = array_keys(config('app.available_locales', ['en' => 'English']));
    if (! in_array($locale, $availableLocales)) {
        $locale = app()->getLocale();
    }
    // ... rest of method
}
```

**app.ts**
```typescript
const i18n = createI18n({ ... });

// Expose i18n instance globally for locale switching
(window as any).__VUE_I18N__ = i18n;
```

**LocaleSwitcher.vue**
```typescript
const switchLocale = async (locale: string) => {
  if (locale === currentLocale.value) {
    isOpen.value = false;
    return;
  }

  try {
    // Fetch translations for the new locale
    const response = await fetch(`/api/translations?locale=${locale}`);
    const data = await response.json();

    // Get the global i18n instance and set the new locale messages
    const i18n = (window as any).__VUE_I18N__;
    if (i18n && data.translations) {
      i18n.global.setLocaleMessage(locale, data.translations);
    }

    // Now update the locale value
    i18nLocale.value = locale;

    // Update backend session via Inertia
    router.post('/locale/switch', { locale }, { ... });
  } catch (error) {
    console.error('Failed to load translations:', error);
    // Fall back to just switching locale
    i18nLocale.value = locale;
    router.post('/locale/switch', { locale }, { ... });
  }
};
```

---

## Troubleshooting Strategy

### Step 1: Verify Translation Files Exist
Checked that `lang/zh-CN.json` exists and contains all required translation keys.

### Step 2: Verify Frontend Usage
Confirmed `EventDetail.vue` uses `useI18n()` and calls `t('key')` correctly.

### Step 3: Trace Data Flow
Mapped the complete flow:
```
User visits page → Controller sets locale → Inertia shares locale →
app.ts fetches translations → vue-i18n initialized → t() works
```

### Step 4: Identify the Gap
Found that locale switching only updated the locale value without loading new translations.

### Step 5: Research Vue-i18n Lazy Loading
Consulted vue-i18n documentation on lazy loading locales:
- Must fetch translation messages for new locale
- Must call `i18n.global.setLocaleMessage(locale, messages)`
- Then set `i18n.global.locale.value = locale`

---

## Prevention Strategies

### 1. Code Review Guidelines
- When implementing locale switching, always verify translations are loaded
- Test locale switching in SPA mode, not just full page reloads

### 2. Testing Requirements
- Add E2E test for locale switching that verifies:
  - Initial load in English
  - Switch to zh-CN
  - Verify specific translated strings appear

### 3. Documentation
- Document the locale switching flow in architecture docs
- Note that vue-i18n requires explicit message loading for dynamic locales

### 4. Best Practices
- Consider preloading all locale translations on app init (trade-off: larger initial payload)
- Or implement a translation loading composable that can be reused

---

## Key Takeaways

1. **Vue-i18n doesn't auto-load translations** - You must explicitly fetch and register them when switching locales in an SPA.

2. **Test SPA navigation separately from full page loads** - The locale switching worked on page refresh because the entire app re-initialized with the new locale.

3. **Global instance access pattern** - Exposing the i18n instance via `window.__VUE_I18N__` is a common pattern for dynamic locale loading.

4. **Graceful degradation** - The fix includes error handling to fall back to the basic locale switch if translation fetch fails.

---

## Related Files

- `resources/js/app.ts:24-34`
- `resources/js/components/Shared/LocaleSwitcher.vue:43-81`
- `app/Http/Controllers/TranslationController.php:10-33`
- `lang/zh-CN.json` (translation file)
- `config/app.php:129` (available locales config)

## References

- [Vue-i18n Lazy Loading Guide](https://vue-i18n.intlify.dev/guide/advanced/lazy.html)
- [Vue-i18n setLocaleMessage API](https://vue-i18n.intlify.dev/api/general.html#setlocalemessage)
