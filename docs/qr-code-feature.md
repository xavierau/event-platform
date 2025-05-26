# QR Code Booking Feature

## Overview

The QR Code booking feature allows users to view their booking details along with a dynamically generated QR code that can be used for check-in verification at events.

## Features

- **Dynamic QR Code Generation**: Each QR code is generated in real-time with current UTC timestamp
- **Secure Data Encoding**: QR codes contain base64-encoded JSON data with booking verification information
- **Comprehensive Booking Details**: Modal displays all relevant booking information including event occurrences
- **Mobile-Optimized UI**: Responsive design that works well on all device sizes

## QR Code Data Structure

The QR code contains the following information encoded as base64 JSON:

```typescript
interface QRCodeData {
  timestamp: string;      // UTC ISO string when QR was generated
  userId: number | string; // ID of the user who made the booking
  bookingNumber: string;   // Unique booking number (e.g., "BK-2024-001")
  bookingId: number | string; // Internal booking ID
}
```

### Example QR Code Data

```json
{
  "timestamp": "2024-01-15T10:30:00.000Z",
  "userId": 123,
  "bookingNumber": "BK-2024-001",
  "bookingId": 456
}
```

## How to Use

### For Users

1. Navigate to "My Bookings" page
2. Click on any booking item to view details
3. The modal will display:
   - QR code for check-in
   - Booking information (ticket type, quantity, price)
   - Event details and valid dates
   - Venue information

### For Event Organizers

The QR code can be scanned and decoded to verify:
- Booking authenticity
- User identity
- Booking validity
- Timestamp for security

## Technical Implementation

### Frontend Components

- **`BookingDetailsModal.vue`**: Main modal component that displays booking details and QR code
- **`MyBookings.vue`**: Updated to integrate with the new modal
- **`BookingItem.vue`**: Reusable component for displaying booking items

### Utility Functions

Located in `resources/js/Utils/qrcode.ts`:

- `createQRCodeData()`: Creates the data structure for QR encoding
- `encodeQRCodeData()`: Converts data to base64 string
- `decodeQRCodeData()`: Decodes base64 back to data object
- `isQRCodeDataValid()`: Validates QR code age (default: 24 hours)

### Dependencies

- **qrcode**: Library for generating QR code images
- **@types/qrcode**: TypeScript definitions

## Security Considerations

1. **Time-based Validation**: QR codes include generation timestamp for age verification
2. **User Authentication**: QR generation requires authenticated user session
3. **Booking Verification**: QR codes are tied to specific bookings and users
4. **Base64 Encoding**: Data is encoded (not encrypted) - consider additional security for sensitive deployments

## Future Enhancements

Potential improvements for the QR code system:

1. **Encryption**: Add encryption layer for sensitive data
2. **Expiration**: Implement automatic QR code expiration
3. **Usage Tracking**: Track QR code scans for analytics
4. **Offline Verification**: Enable offline QR code validation
5. **Custom Styling**: Allow customization of QR code appearance

## API Integration

For backend verification, implement endpoints to:

```php
// Example Laravel routes
Route::post('/api/verify-qr-code', [QRCodeController::class, 'verify']);
Route::post('/api/check-in', [CheckInController::class, 'process']);
```

## Testing

The QR code functionality can be tested by:

1. Creating a booking through the system
2. Viewing the booking in "My Bookings"
3. Opening the booking details modal
4. Verifying the QR code generates correctly
5. Using a QR code scanner to verify the encoded data

## Browser Compatibility

The feature uses modern browser APIs:
- `btoa()` for base64 encoding
- `atob()` for base64 decoding
- Canvas API (via qrcode library) for QR generation

Supported browsers: Chrome 4+, Firefox 1+, Safari 1.2+, IE 10+ 
