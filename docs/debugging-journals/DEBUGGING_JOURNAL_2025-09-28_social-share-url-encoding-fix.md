# 🔍 Problem Description

**Issue**: After fixing the initial social share URL loading issue, a new problem emerged when testing on the actual event detail page. The social share API call was returning a 404 error:

```
https://eventplatform.test/api/social-share/urls?shareable_type=App%5C%5CModels%5C%5CEvent&shareable_id=1

Response:
{
    "error": "Model not found",
    "message": "The specified shareable model could not be found."
}
```

**Expected Behavior**: The API should successfully find the Event model and return share URLs for all platforms.

**Files Involved**:
- `resources/js/pages/Public/EventDetail.vue:627` (template with incorrect shareable-type)
- `resources/js/composables/useSocialShare.ts:69` (API call with encodeURIComponent)
- `app/Modules/SocialShare/Http/Controllers/SocialShareController.php:314-320` (backend model validation)

# 🛠️ Root Cause Analysis

The issue was a **double URL encoding problem** caused by incorrect backslash escaping in the Vue template.

**The Problem Chain**:
1. **Vue Template**: `shareable-type="App\\Models\\Event"` (double backslashes)
2. **JavaScript String**: `"App\\Models\\Event"` (Vue processes as double backslashes)
3. **URL Encoding**: `encodeURIComponent()` converts to `"App%5C%5CModels%5C%5CEvent"`
4. **Server Receives**: `"App\\Models\\Event"` (double backslashes after decoding)
5. **Backend Validation**: Expects `"App\Models\Event"` (single backslashes) ❌

**API Testing Verification**:
```bash
# This works (single backslash):
curl "https://eventplatform.test/api/social-share/urls?shareable_type=App%5CModels%5CEvent&shareable_id=1"
→ Returns valid response with share URLs

# This fails (double backslash - what frontend was sending):
curl "https://eventplatform.test/api/social-share/urls?shareable_type=App%5C%5CModels%5C%5CEvent&shareable_id=1"
→ {"error":"Model not found","message":"The specified shareable model could not be found."}
```

**Backend Model Resolution**:
The backend controller (`SocialShareController.php:314-320`) has a whitelist of allowed models:
```php
$allowedModels = [
    'App\\Models\\Event' => \App\Models\Event::class,  // Single backslash expected
    // Add other shareable models here as needed
];

if (!isset($allowedModels[$type])) {
    throw new ModelNotFoundException("Model type '{$type}' is not supported for sharing.");
}
```

The double backslash `App\\Models\\Event` doesn't match the key `App\Models\Event`, causing the validation to fail.

# ✅ The Solution

**Fixed Code** (`EventDetail.vue:627`):
```vue
<!-- BEFORE (BROKEN): -->
<SocialShareWrapper
    shareable-type="App\\Models\\Event"
    :shareable-id="Number(event.id)"
    class="flex-shrink-0"
/>

<!-- AFTER (FIXED): -->
<SocialShareWrapper
    shareable-type="App\Models\Event"
    :shareable-id="Number(event.id)"
    class="flex-shrink-0"
/>
```

**Key Change**: Reduced double backslashes `\\` to single backslashes `\` in the Vue template.

**Data Flow After Fix**:
1. **Vue Template**: `"App\Models\Event"` (single backslash)
2. **JavaScript String**: `"App\Models\Event"` (single backslash)
3. **URL Encoding**: `encodeURIComponent()` converts to `"App%5CModels%5CEvent"`
4. **Server Receives**: `"App\Models\Event"` (single backslash after decoding)
5. **Backend Validation**: Matches whitelist key `"App\Models\Event"` ✅

# 🔬 Troubleshooting Strategy

1. **API Testing**: Used curl to test different encoding scenarios:
   ```bash
   # Test single backslash encoding
   curl -s "https://eventplatform.test/api/social-share/urls?shareable_type=App%5CModels%5CEvent&shareable_id=1"

   # Test double backslash encoding (reproducing frontend issue)
   curl -s "https://eventplatform.test/api/social-share/urls?shareable_type=App%5C%5CModels%5C%5CEvent&shareable_id=1"
   ```

2. **Network Analysis**: Examined the actual AJAX request in browser dev tools to see the exact URL being sent

3. **Backend Debugging**: Traced through the controller logic to understand how model type validation works

4. **String Escaping Analysis**: Understood Vue template string processing and URL encoding behavior

# 🚫 Prevention Strategies

1. **Template String Guidelines**: Document proper escaping rules for class names in Vue templates
2. **API Testing**: Always test API endpoints with actual frontend-generated requests, not just manual curl tests
3. **Validation Error Messages**: Improve backend error messages to show what was received vs. what was expected
4. **Integration Tests**: Create tests that verify complete frontend-to-backend data flow
5. **Code Review Checklist**: Include string escaping verification for template props containing backslashes

**Recommended Backend Enhancement**:
```php
// Better error message showing what was received
if (!isset($allowedModels[$type])) {
    $availableTypes = implode(', ', array_keys($allowedModels));
    throw new ModelNotFoundException(
        "Model type '{$type}' is not supported for sharing. " .
        "Available types: {$availableTypes}"
    );
}
```

**Recommended Frontend Test**:
```javascript
// Test that ensures correct encoding
test('social share component sends correct model type', async () => {
    const wrapper = mount(SocialShareWrapper, {
        props: {
            shareableType: 'App\\Models\\Event',
            shareableId: 1
        }
    });

    // Verify the actual API call uses single backslashes
    expect(mockApiCall).toHaveBeenCalledWith(
        expect.stringContaining('shareable_type=App%5CModels%5CEvent')
    );
});
```

# 📊 Time Investment

- **Investigation Time**: 20 minutes (API testing, network analysis)
- **Root Cause Identification**: 15 minutes (string escaping analysis)
- **Fix Implementation**: 5 minutes (template change)
- **Verification**: 10 minutes (testing the fix)
- **Documentation**: 25 minutes (this journal)
- **Total Time**: 1.25 hours

**Lessons Learned**:
- Vue template string escaping can be tricky with backslashes
- Always test with actual frontend-generated requests
- URL encoding issues often manifest as "not found" errors

# 🎯 Key Takeaways

1. **String Escaping**: Be careful with backslash escaping in Vue templates, especially for class names
2. **Testing Strategy**: Test API endpoints with both manual and frontend-generated requests
3. **Error Analysis**: "Model not found" errors can actually be validation/encoding issues
4. **Data Flow Verification**: Trace the complete data flow from template to backend validation
5. **Documentation**: Document string escaping rules for team members

**Prevention**: Create a style guide for handling class names in Vue templates and add linting rules to catch double-backslash patterns in template strings.

**Related Issues**: This complements the previous fix in `DEBUGGING_JOURNAL_2025-09-28_social-share-missing-facebook-url.md` - both were required to fully resolve the social sharing functionality.