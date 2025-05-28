# QR Scanner Camera Access Troubleshooting Guide

## Overview
This guide helps troubleshoot camera access issues with the QR scanner feature in the Event Platform.

## Quick Checklist

### 1. **HTTPS Requirement** ✅
- **Required**: The application MUST be accessed via HTTPS
- **Check**: Look at the URL bar - it should show `https://` not `http://`
- **Why**: Modern browsers only allow camera access on secure connections

### 2. **Browser Permissions** 🎥
- **Check**: Browser should prompt for camera permission on first access
- **Location**: Usually appears in the address bar or as a popup
- **Action**: Click "Allow" when prompted

### 3. **Camera Hardware** 📷
- **Check**: Ensure a camera is connected and working
- **Test**: Try using the camera in another application (e.g., Photo Booth, Zoom)

## Debugging Steps

### Step 1: Access the QR Scanner
1. Navigate to Admin → Bookings
2. Click the "QR Code Scanner" button
3. If you're an admin, select an event from the dropdown
4. Open browser Developer Tools (F12)
5. Go to the Console tab

### Step 2: Check Debug Information
Look for the blue "Debug Info" box on the QR scanner page. It should show:
- 🔒 HTTPS: Yes
- 🎥 Camera API: Available
- 🔐 Secure Context: Yes
- 🔄 Should Show Scanner: Yes

### Step 3: Review Console Logs
The console will show detailed logging:

#### On Page Load:
```
🚀 QR Scanner Component Mounted
├── Current URL: https://your-domain.com/admin/qr-scanner
├── Protocol: https:
├── Is HTTPS?: true
├── Secure context?: true
├── Camera API available?: true
└── Testing camera access...
```

#### If Camera Access Succeeds:
```
✅ Camera access test successful
├── Stream: MediaStream {...}
├── Video tracks: 1
└── Video track 1: {...}
```

#### If Camera Access Fails:
```
❌ Camera access test failed: NotAllowedError
├── Error name: NotAllowedError
├── Error message: Permission denied
└── Error details: {...}
```

### Step 4: Manual Camera Test
Click the "Test Camera Access" button to manually test camera permissions.

## Common Issues & Solutions

### Issue 1: "Camera access denied"
**Error**: `NotAllowedError`
**Solution**:
1. Click the camera icon in the browser address bar
2. Change permission to "Allow"
3. Refresh the page

### Issue 2: "No camera found"
**Error**: `NotFoundError`
**Solution**:
1. Check if camera is connected
2. Close other applications using the camera
3. Try a different browser

### Issue 3: "Camera is already in use"
**Error**: `NotReadableError`
**Solution**:
1. Close other tabs/applications using the camera
2. Restart the browser
3. Refresh the page

### Issue 4: "Security error"
**Error**: `SecurityError`
**Solution**:
1. Ensure you're accessing via HTTPS
2. Check if the SSL certificate is valid
3. Try in an incognito/private window

### Issue 5: No camera prompt appears
**Possible Causes**:
1. Camera permission was previously denied
2. Browser settings block camera access
3. Not on HTTPS

**Solution**:
1. Check browser camera settings
2. Reset permissions for the site
3. Try in incognito mode

## Browser-Specific Instructions

### Chrome
1. Click the lock icon in the address bar
2. Set Camera to "Allow"
3. Refresh the page

### Firefox
1. Click the shield icon in the address bar
2. Click "Turn off Blocking for This Site"
3. Refresh and allow camera access

### Safari
1. Go to Safari → Preferences → Websites → Camera
2. Set your domain to "Allow"
3. Refresh the page

## Advanced Debugging

### Check Browser Console for Detailed Logs
The enhanced logging will show:
- Browser capabilities
- Available video devices
- Permission states
- Detailed error information

### Test in Different Browsers
Try accessing the QR scanner in:
- Chrome
- Firefox
- Safari
- Edge

### Check Network Tab
1. Open Developer Tools → Network tab
2. Look for any failed requests to camera-related APIs

## Still Having Issues?

If the problem persists:
1. Copy all console logs from the "🚀 QR Scanner Component Mounted" section
2. Note your browser version and operating system
3. Include the exact error message shown
4. Report the issue with these details

## Technical Notes

- The QR scanner uses the `vue-qrcode-reader` library
- Camera access requires `navigator.mediaDevices.getUserMedia()`
- The application prefers the back camera (`facingMode: 'environment'`) for better QR code scanning
- All camera operations are logged to the browser console for debugging 
