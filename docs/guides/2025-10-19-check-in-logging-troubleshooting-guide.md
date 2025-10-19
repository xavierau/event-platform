# Check-in Logging & Troubleshooting Guide

**Created:** 2025-10-19
**Purpose:** Guide for using the comprehensive check-in logging system to diagnose and troubleshoot check-in failures

---

## Table of Contents

1. [Overview](#overview)
2. [Log Format & Structure](#log-format--structure)
3. [Quick Troubleshooting Workflow](#quick-troubleshooting-workflow)
4. [Common Failure Scenarios](#common-failure-scenarios)
5. [Log Search Commands](#log-search-commands)
6. [Reading Log Entries](#reading-log-entries)
7. [Advanced Troubleshooting](#advanced-troubleshooting)
8. [Performance Considerations](#performance-considerations)

---

## Overview

### What's Logged?

The check-in system now logs **every step** of the check-in process across both systems:

- **Member Check-ins** - General member access scanning (QR codes for membership validation)
- **Booking Check-ins** - Ticketed event check-ins (QR codes for event bookings)

### Where are Logs Stored?

All logs are written to **Laravel's standard log file**:
```
storage/logs/laravel.log
```

For production environments, logs may rotate daily:
```
storage/logs/laravel-2025-10-19.log
```

### Log Levels

- `INFO` - Normal operations, successful check-ins
- `WARNING` - Validation failures, authorization denials
- `ERROR` - Exceptions, system failures

---

## Log Format & Structure

### Standard Format

```
[TYPE][REQUEST_ID][STEP] Message {context}
```

**Example:**
```
[MEMBER_CHECKIN][f8a3b2c1][VALIDATION] QR validation failed {"error":"Invalid QR code format"}
```

### Log Type Prefixes

| Prefix | Description |
|--------|-------------|
| `MEMBER_CHECKIN` | Member access check-in flow |
| `BOOKING_CHECKIN` | Event booking check-in flow |
| `MEMBER_CHECK_IN_RECORDS` | Check-in records page access |
| `QR_SCANNER` | QR scanner page operations |

### Log Step Categories

| Step | Purpose | Example |
|------|---------|---------|
| `ENTRY` | Method invocation | Method called with parameters |
| `EXIT` | Method completion | Method completed successfully |
| `VALIDATION` | QR/data validation | QR code format validation passed |
| `AUTH` | Authorization checks | Operator authorization failed |
| `BUSINESS_LOGIC` | Business rules | Eligibility check passed |
| `DB` | Database operations | Booking record found |
| `ERROR` | Exception handling | Check-in processing exception |

### Request ID Correlation

Every check-in attempt generates a **unique 8-character request ID** that appears in every log line for that request.

**Example Request Flow:**
```
[MEMBER_CHECKIN][a1b2c3d4][ENTRY] Method called
[MEMBER_CHECKIN][a1b2c3d4][VALIDATION] QR validation started
[MEMBER_CHECKIN][a1b2c3d4][DB] Member lookup successful
[MEMBER_CHECKIN][a1b2c3d4][EXIT] Method completed
```

---

## Quick Troubleshooting Workflow

### Step 1: Identify the Issue

Ask the user:
1. **When did it happen?** (timestamp helps narrow search)
2. **Which QR code failed?** (member name, booking number)
3. **Who was scanning?** (operator/scanner identity)
4. **What error message appeared?** (frontend error)

### Step 2: Search Logs

```bash
# Recent member check-in failures (last 100 lines)
tail -100 storage/logs/laravel.log | grep "\[MEMBER_CHECKIN\]"

# Search by timestamp (today's failures)
grep "2025-10-19.*\[MEMBER_CHECKIN\]" storage/logs/laravel.log

# Search by specific member email
grep "member_email.*john@example.com" storage/logs/laravel.log
```

### Step 3: Trace the Request

Once you find a relevant log entry, **copy the request ID** and trace the entire flow:

```bash
# Replace abc123 with actual request ID
grep "\[abc123\]" storage/logs/laravel.log
```

### Step 4: Identify Root Cause

Look for:
- ❌ **Validation failures** - `[VALIDATION]` with `false` or `failed`
- ❌ **Authorization denials** - `[AUTH]` with `false` or `denied`
- ❌ **Business logic errors** - `[BUSINESS_LOGIC]` with error context
- ❌ **Database issues** - `[DB]` with failure status
- ❌ **Exceptions** - `[ERROR]` entries with stack traces

### Step 5: Resolve & Verify

After fixing the issue, verify by:
1. Testing with the same QR code
2. Checking logs for successful flow
3. Confirming all steps complete without errors

---

## Common Failure Scenarios

### Scenario 1: "Invalid QR Code"

**Symptoms:**
- User scans QR code
- Gets error: "Invalid QR code" or "QR validation failed"

**Log Search:**
```bash
grep "QR validation failed\|Invalid QR code" storage/logs/laravel.log | tail -20
```

**What to Look For:**
```
[MEMBER_CHECKIN][abc123][VALIDATION] QR validation failed {"error":"Invalid QR code format"}
```

**Common Causes:**
- QR code format doesn't match expected structure
- QR code expired or corrupted
- Member not found in database
- QR code belongs to wrong system (booking vs. member)

**Resolution:**
- Check QR code generation logic
- Verify member exists in database: `SELECT * FROM users WHERE id = ?`
- Regenerate QR code for member

---

### Scenario 2: "You do not have permission"

**Symptoms:**
- Scanner gets authorization error
- Error: "You are not authorized to check in for this event"

**Log Search:**
```bash
grep "\[AUTH\].*false\|authorization failed" storage/logs/laravel.log | tail -20
```

**What to Look For:**
```
[BOOKING_CHECKIN][xyz789][AUTH] Operator authorization failed {
  "operator_id": 42,
  "error": "You are not authorized to check in for this event"
}
```

**Common Causes:**
- Scanner doesn't have organizer membership
- Scanner belongs to different organizer than event
- Scanner role insufficient (not admin, not organizer member)

**Resolution:**
1. Verify scanner's roles: `SELECT * FROM role_user WHERE user_id = ?`
2. Check organizer membership:
   ```sql
   SELECT * FROM organizer_user WHERE user_id = ? AND organizer_id = ?
   ```
3. Add user to correct organizer or assign admin role

---

### Scenario 3: "Booking not found"

**Symptoms:**
- QR scan results in "Booking not found"
- Valid-looking QR code doesn't work

**Log Search:**
```bash
grep "Booking not found" storage/logs/laravel.log | tail -20
```

**What to Look For:**
```
[BOOKING_CHECKIN][def456][VALIDATION] Booking not found {
  "qr_code_identifier": "BK-2025-001234"
}
```

**Common Causes:**
- Booking was cancelled or deleted
- QR code identifier doesn't match database
- Using legacy booking_number vs. qr_code_identifier

**Resolution:**
1. Search by booking number:
   ```sql
   SELECT * FROM bookings WHERE booking_number = 'BK-2025-001234'
   SELECT * FROM bookings WHERE qr_code_identifier = 'BK-2025-001234'
   ```
2. Check booking status (should be 'confirmed' or 'used')
3. Verify QR code generation matches expected format

---

### Scenario 4: "Maximum check-ins reached"

**Symptoms:**
- Error: "Maximum allowed check-ins reached"
- User already checked in before

**Log Search:**
```bash
grep "Maximum allowed\|check-in count limit" storage/logs/laravel.log | tail -20
```

**What to Look For:**
```
[BOOKING_CHECKIN][ghi789][VALIDATION] Check-in count limit reached {
  "successful_check_ins": 2,
  "max_allowed_check_ins": 2
}
```

**Common Causes:**
- Ticket allows only N check-ins (e.g., 1 for single-entry)
- User already used all allowed check-ins
- Incorrect `max_allowed_check_ins` on booking

**Resolution:**
1. Check booking check-in limits:
   ```sql
   SELECT id, booking_number, successful_check_ins_count, max_allowed_check_ins
   FROM bookings WHERE id = ?
   ```
2. Review check-in history:
   ```sql
   SELECT * FROM check_in_logs WHERE booking_id = ? ORDER BY check_in_timestamp DESC
   ```
3. If legitimate, update `max_allowed_check_ins` or mark previous check-ins as invalid

---

### Scenario 5: "Ticket not valid for occurrence"

**Symptoms:**
- Error: "This ticket is not valid for the selected event occurrence"
- Scanning for wrong date/session

**Log Search:**
```bash
grep "not valid for.*occurrence\|Ticket-occurrence validation failed" storage/logs/laravel.log | tail -20
```

**What to Look For:**
```
[BOOKING_CHECKIN][jkl012][VALIDATION] Ticket-occurrence validation failed {
  "error": "This ticket is not valid for the selected event occurrence",
  "ticket_definition_id": 15,
  "valid_occurrence_ids": [1, 2, 3]
}
```

**Common Causes:**
- Ticket valid for specific occurrences only
- Scanner selecting wrong occurrence
- Ticket definition not linked to occurrence

**Resolution:**
1. Check ticket-occurrence relationships:
   ```sql
   SELECT * FROM event_occurrence_ticket_definition
   WHERE ticket_definition_id = ?
   ```
2. Verify event occurrence:
   ```sql
   SELECT * FROM event_occurrences WHERE id = ?
   ```
3. Update ticket definition to include occurrence or guide user to correct session

---

### Scenario 6: "Booking status not valid"

**Symptoms:**
- Error: "Booking status is not valid for check-in"
- Booking in pending/cancelled state

**Log Search:**
```bash
grep "Booking status.*not valid\|status invalid" storage/logs/laravel.log | tail -20
```

**What to Look For:**
```
[BOOKING_CHECKIN][mno345][VALIDATION] Booking status invalid {
  "current_status": "pending",
  "valid_statuses": ["confirmed", "used"]
}
```

**Common Causes:**
- Booking not confirmed (payment pending)
- Booking cancelled
- Booking expired

**Resolution:**
1. Check booking status:
   ```sql
   SELECT id, booking_number, status FROM bookings WHERE id = ?
   ```
2. If payment completed, update status to 'confirmed'
3. If cancelled in error, restore booking

---

### Scenario 7: Database/System Errors

**Symptoms:**
- Generic error: "Check-in processing failed"
- System exception

**Log Search:**
```bash
grep "\[ERROR\]" storage/logs/laravel.log | tail -50
```

**What to Look For:**
```
[MEMBER_CHECKIN][pqr678][ERROR] Check-in processing exception {
  "exception": "Illuminate\\Database\\QueryException",
  "message": "SQLSTATE[HY000]: General error: 1 no such table: member_check_ins",
  "file": "/app/Services/MemberCheckInService.php",
  "line": 106
}
```

**Common Causes:**
- Database connection issues
- Missing migrations
- Corrupted data
- Code errors (null pointer, type errors)

**Resolution:**
1. Check database connectivity
2. Run pending migrations: `php artisan migrate`
3. Check exception stack trace for code issues
4. Review database integrity

---

## Log Search Commands

### By Time Range

```bash
# Today's check-ins
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep "CHECKIN"

# Last hour
grep "$(date +%Y-%m-%d\ %H)" storage/logs/laravel.log | grep "CHECKIN"

# Specific time window
grep "2025-10-19 14:" storage/logs/laravel.log | grep "CHECKIN"
```

### By User/Member

```bash
# By member ID
grep "member_id.*123" storage/logs/laravel.log

# By member email
grep "member_email.*john@example.com" storage/logs/laravel.log

# By scanner/operator
grep "scanner_id.*42\|operator_id.*42" storage/logs/laravel.log
```

### By Status

```bash
# All failures
grep "CHECKIN.*failed\|CHECKIN.*ERROR" storage/logs/laravel.log

# Successful check-ins
grep "Check-in successful" storage/logs/laravel.log

# Authorization failures
grep "\[AUTH\].*false" storage/logs/laravel.log

# Validation failures
grep "\[VALIDATION\].*false\|validation failed" storage/logs/laravel.log
```

### By Booking/Event

```bash
# By booking ID
grep "booking_id.*456" storage/logs/laravel.log

# By booking number
grep "booking_number.*BK-2025-001234" storage/logs/laravel.log

# By event
grep "event_id.*789" storage/logs/laravel.log
```

### By Request ID

```bash
# Trace complete request flow
grep "\[abc123\]" storage/logs/laravel.log

# Multiple requests
grep -E "\[abc123\]|\[def456\]" storage/logs/laravel.log
```

### Advanced Filtering

```bash
# Count failures by type
grep "CHECKIN.*failed" storage/logs/laravel.log | grep -oE "\[.*?\]" | sort | uniq -c

# Extract all request IDs from failures
grep "CHECKIN.*failed" storage/logs/laravel.log | grep -oE "\[[a-z0-9]{8}\]" | sort -u

# Find slow operations (if timestamps present)
grep "CHECKIN" storage/logs/laravel.log | grep -E "ENTRY|EXIT"
```

---

## Reading Log Entries

### Anatomy of a Log Entry

```
[2025-10-19 14:23:45] local.INFO: [MEMBER_CHECKIN][a1b2c3d4][VALIDATION] QR validation successful {
  "member_id": 123,
  "member_email": "john@example.com",
  "membership_type": "annual",
  "membership_status": "active"
}
```

**Components:**
1. **Timestamp:** `[2025-10-19 14:23:45]`
2. **Environment & Level:** `local.INFO`
3. **Type:** `MEMBER_CHECKIN`
4. **Request ID:** `a1b2c3d4`
5. **Step:** `VALIDATION`
6. **Message:** `QR validation successful`
7. **Context:** JSON object with details

### Context Data Fields

#### Common Fields (All Check-ins)

| Field | Description | Example |
|-------|-------------|---------|
| `request_id` | Unique request identifier | `"a1b2c3d4"` |
| `user_id` | Authenticated user/scanner | `42` |
| `ip_address` | Client IP address | `"192.168.1.100"` |
| `user_agent` | Browser/device info | `"Mozilla/5.0..."` |

#### Member Check-in Fields

| Field | Description | Example |
|-------|-------------|---------|
| `member_id` | Member being checked in | `123` |
| `member_email` | Member email | `"john@example.com"` |
| `scanner_id` | User performing scan | `42` |
| `membership_type` | Type of membership | `"annual"` |
| `membership_status` | Status of membership | `"active"` |
| `location` | Check-in location | `"Main Entrance"` |
| `device_identifier` | Scanning device | `"iPad-001"` |

#### Booking Check-in Fields

| Field | Description | Example |
|-------|-------------|---------|
| `booking_id` | Booking record ID | `456` |
| `booking_number` | Public booking number | `"BK-2025-001234"` |
| `booking_status` | Current booking status | `"confirmed"` |
| `event_id` | Associated event | `789` |
| `event_occurrence_id` | Specific occurrence | `101` |
| `operator_id` | Check-in operator | `42` |
| `successful_check_ins` | Check-in count | `1` |
| `max_allowed_check_ins` | Maximum allowed | `2` |

---

## Advanced Troubleshooting

### Debugging Complete Check-in Flow

**Step 1: Find the initial request**
```bash
grep "MEMBER_CHECKIN.*ENTRY" storage/logs/laravel.log | tail -5
```

**Step 2: Extract request ID**
```
[MEMBER_CHECKIN][a1b2c3d4][ENTRY] Method called
                 ^^^^^^^^
```

**Step 3: Trace full flow**
```bash
grep "\[a1b2c3d4\]" storage/logs/laravel.log
```

**Step 4: Analyze each step**
```
[ENTRY]           -> ✓ Request received
[VALIDATION]      -> ✓ QR validated
[AUTH]            -> ✓ Authorization passed
[DB]              -> ✓ Database lookup
[BUSINESS_LOGIC]  -> ✓ Rules checked
[DB]              -> ✓ Record created
[EXIT]            -> ✓ Completed successfully
```

### Performance Debugging

**Find slow check-ins:**
```bash
# Extract entry/exit times for analysis
grep "CHECKIN.*ENTRY\|CHECKIN.*EXIT" storage/logs/laravel.log | \
  awk '{print $1, $2, $5, $6}'
```

**Database query analysis:**
```bash
# Count DB operations per check-in
grep "\[DB\]" storage/logs/laravel.log | \
  grep -oE "\[[a-z0-9]{8}\]" | sort | uniq -c | sort -rn
```

### Pattern Analysis

**Identify common failure patterns:**
```bash
# Most common errors
grep "CHECKIN.*failed" storage/logs/laravel.log | \
  grep -oE '"error":"[^"]*"' | sort | uniq -c | sort -rn

# Failures by scanner
grep "CHECKIN.*failed" storage/logs/laravel.log | \
  grep -oE 'scanner_id":[0-9]+' | sort | uniq -c | sort -rn

# Failures by hour
grep "CHECKIN.*failed" storage/logs/laravel.log | \
  awk '{print $1" "$2}' | cut -d: -f1 | sort | uniq -c
```

### Multi-System Issues

**Check if issue affects both systems:**
```bash
# Member check-ins today
grep "$(date +%Y-%m-%d).*MEMBER_CHECKIN" storage/logs/laravel.log | wc -l

# Booking check-ins today
grep "$(date +%Y-%m-%d).*BOOKING_CHECKIN" storage/logs/laravel.log | wc -l

# Compare success rates
echo "Member Success:"
grep "$(date +%Y-%m-%d).*MEMBER_CHECKIN.*successful" storage/logs/laravel.log | wc -l
echo "Member Failures:"
grep "$(date +%Y-%m-%d).*MEMBER_CHECKIN.*failed" storage/logs/laravel.log | wc -l
```

---

## Performance Considerations

### Log Volume

Verbose logging generates significant data:
- **Typical check-in:** 15-25 log lines
- **Failed check-in:** 20-30 log lines (includes error traces)
- **Daily volume (1000 check-ins):** ~20,000 log lines (~10MB)

### Best Practices

1. **Regular Log Rotation**
   ```bash
   # Laravel daily rotation (config in config/logging.php)
   'daily' => [
       'driver' => 'daily',
       'path' => storage_path('logs/laravel.log'),
       'level' => env('LOG_LEVEL', 'debug'),
       'days' => 14, // Keep 2 weeks
   ],
   ```

2. **Archive Old Logs**
   ```bash
   # Compress logs older than 7 days
   find storage/logs -name "laravel-*.log" -mtime +7 -exec gzip {} \;

   # Delete logs older than 30 days
   find storage/logs -name "laravel-*.log.gz" -mtime +30 -delete
   ```

3. **Use Log Levels Appropriately**
   - Production: `LOG_LEVEL=warning` (errors + warnings only)
   - Staging: `LOG_LEVEL=info` (all logs)
   - Development: `LOG_LEVEL=debug` (maximum verbosity)

4. **Monitor Disk Usage**
   ```bash
   # Check log directory size
   du -sh storage/logs/

   # Find largest log files
   du -h storage/logs/* | sort -rh | head -10
   ```

### Disabling Verbose Logging

If you need to reduce log volume temporarily:

**Option 1: Environment Variable**
```env
# .env
LOG_LEVEL=warning  # Only errors and warnings
```

**Option 2: Conditional Logging**

Modify `CheckInLoggable` trait to check environment:
```php
protected function logCheckInAttempt(string $type, string $step, string $message, array $context = [], string $level = 'info'): void
{
    // Only log in development/staging
    if (!app()->environment('production')) {
        // ... existing logging code
    }
}
```

---

## Quick Reference Card

### Essential Commands

```bash
# View recent check-in activity
tail -100 storage/logs/laravel.log | grep "CHECKIN"

# Find failures from last hour
grep "$(date +%Y-%m-%d\ %H)" storage/logs/laravel.log | grep "failed"

# Trace specific request
grep "\[REQUEST_ID\]" storage/logs/laravel.log

# Count check-ins today
grep "$(date +%Y-%m-%d).*Check-in successful" storage/logs/laravel.log | wc -l
```

### Log Locations

| File | Description |
|------|-------------|
| `storage/logs/laravel.log` | Main log file |
| `storage/logs/laravel-YYYY-MM-DD.log` | Daily rotated logs |
| `storage/logs/qr_scanner.log` | QR scanner specific (if configured) |

### Support Checklist

When user reports check-in failure:

- [ ] Get timestamp (when it happened)
- [ ] Get QR code details (member name, booking number)
- [ ] Get scanner identity (who was scanning)
- [ ] Get error message (exact text)
- [ ] Search logs by timestamp
- [ ] Extract request ID
- [ ] Trace complete flow
- [ ] Identify failure step
- [ ] Check database state
- [ ] Apply fix
- [ ] Verify with test scan
- [ ] Document resolution

---

## Additional Resources

- **Laravel Logging Docs:** https://laravel.com/docs/logging
- **Check-in Architecture:** `docs/architecture/check-in-system.md` (if exists)
- **Database Schema:** `database/migrations/*_create_check_in_*.php`

---

**Document Version:** 1.0
**Last Updated:** 2025-10-19
**Maintained By:** Development Team
