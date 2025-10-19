# Check-in Logging Quick Reference

**One-page cheat sheet for troubleshooting check-in issues**

---

## Log Format

```
[TYPE][REQUEST_ID][STEP] Message {context}
```

**Example:**
```
[MEMBER_CHECKIN][a1b2c3d4][VALIDATION] QR validation failed {"error":"Invalid format"}
```

---

## Log Types

| Type | System |
|------|--------|
| `MEMBER_CHECKIN` | Member access scanning |
| `BOOKING_CHECKIN` | Event booking check-ins |

## Log Steps

| Step | Meaning |
|------|---------|
| `ENTRY` | Method started |
| `EXIT` | Method completed |
| `VALIDATION` | QR/data validation |
| `AUTH` | Authorization check |
| `BUSINESS_LOGIC` | Business rules |
| `DB` | Database operation |
| `ERROR` | Exception/error |

---

## Essential Commands

### Recent Activity
```bash
# Last 100 check-in logs
tail -100 storage/logs/laravel.log | grep "CHECKIN"

# Today's check-ins
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep "CHECKIN"
```

### Find Failures
```bash
# All failures
grep "CHECKIN.*failed\|CHECKIN.*ERROR" storage/logs/laravel.log | tail -20

# Last hour failures
grep "$(date +%Y-%m-%d\ %H)" storage/logs/laravel.log | grep "failed"
```

### Trace Request
```bash
# Replace abc123 with actual request ID
grep "\[abc123\]" storage/logs/laravel.log
```

### Search by User
```bash
# By member
grep "member_email.*john@example.com" storage/logs/laravel.log

# By scanner
grep "scanner_id.*42" storage/logs/laravel.log
```

### Search by Booking
```bash
# By booking number
grep "booking_number.*BK-2025-001234" storage/logs/laravel.log

# By booking ID
grep "booking_id.*456" storage/logs/laravel.log
```

---

## Common Errors & Fixes

### "Invalid QR Code"
**Log Pattern:** `[VALIDATION].*failed`
**Cause:** QR format wrong, member not found, expired
**Fix:** Check QR generation, verify member exists

### "Not Authorized"
**Log Pattern:** `[AUTH].*false`
**Cause:** Scanner lacks organizer membership
**Fix:** Add scanner to organizer or assign admin role

### "Booking Not Found"
**Log Pattern:** `Booking not found`
**Cause:** Wrong identifier, booking deleted/cancelled
**Fix:** Verify booking exists in database

### "Max Check-ins Reached"
**Log Pattern:** `check-in count limit`
**Cause:** Already checked in max times
**Fix:** Check booking limits, review history

### "Not Valid for Occurrence"
**Log Pattern:** `not valid for.*occurrence`
**Cause:** Ticket for different date/session
**Fix:** Check ticket-occurrence relationships

### "Invalid Booking Status"
**Log Pattern:** `status.*not valid`
**Cause:** Booking pending/cancelled
**Fix:** Confirm booking or update status

---

## Troubleshooting Workflow

1. **Get Details**
   - Timestamp
   - QR code/member/booking
   - Scanner identity
   - Error message

2. **Search Logs**
   ```bash
   grep "2025-10-19 14:" storage/logs/laravel.log | grep "CHECKIN"
   ```

3. **Find Request ID**
   ```
   [MEMBER_CHECKIN][a1b2c3d4][ENTRY] ...
                    ^^^^^^^^
   ```

4. **Trace Flow**
   ```bash
   grep "\[a1b2c3d4\]" storage/logs/laravel.log
   ```

5. **Identify Failure**
   - Look for `failed`, `false`, `ERROR`
   - Check context for details

6. **Fix & Verify**
   - Apply fix
   - Test with same QR code
   - Confirm success in logs

---

## Log Locations

```
storage/logs/laravel.log              # Main log
storage/logs/laravel-2025-10-19.log   # Daily rotation
```

---

## Database Checks

### Member Check-ins
```sql
-- Find member
SELECT * FROM users WHERE email = 'john@example.com';

-- Recent check-ins
SELECT * FROM member_check_ins
WHERE user_id = 123
ORDER BY scanned_at DESC
LIMIT 10;
```

### Booking Check-ins
```sql
-- Find booking
SELECT * FROM bookings WHERE booking_number = 'BK-2025-001234';

-- Check-in history
SELECT * FROM check_in_logs
WHERE booking_id = 456
ORDER BY check_in_timestamp DESC;

-- Check limits
SELECT
  id,
  booking_number,
  status,
  successful_check_ins_count,
  max_allowed_check_ins
FROM bookings
WHERE id = 456;
```

### Authorization
```sql
-- Check scanner roles
SELECT r.name
FROM role_user ru
JOIN roles r ON r.id = ru.role_id
WHERE ru.user_id = 42;

-- Check organizer membership
SELECT o.id, o.name
FROM organizer_user ou
JOIN organizers o ON o.id = ou.organizer_id
WHERE ou.user_id = 42;
```

---

## Pattern Analysis

```bash
# Most common errors
grep "CHECKIN.*failed" storage/logs/laravel.log | \
  grep -oE '"error":"[^"]*"' | sort | uniq -c | sort -rn

# Failures by scanner
grep "scanner_id" storage/logs/laravel.log | \
  grep -oE 'scanner_id":[0-9]+' | sort | uniq -c | sort -rn

# Success rate today
echo "Successful:"
grep "$(date +%Y-%m-%d).*Check-in successful" storage/logs/laravel.log | wc -l
echo "Failed:"
grep "$(date +%Y-%m-%d).*CHECKIN.*failed" storage/logs/laravel.log | wc -l
```

---

## Support Checklist

- [ ] Timestamp
- [ ] QR code/member/booking details
- [ ] Scanner identity
- [ ] Error message
- [ ] Log search results
- [ ] Request ID traced
- [ ] Failure step identified
- [ ] Database state checked
- [ ] Fix applied
- [ ] Test verification
- [ ] Resolution documented

---

**For detailed guide see:** `docs/guides/2025-10-19-check-in-logging-troubleshooting-guide.md`
