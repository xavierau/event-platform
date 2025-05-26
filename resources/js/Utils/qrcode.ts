/**
 * QR Code Utilities for Booking System
 *
 * This module provides utilities for generating and handling QR codes
 * for booking verification and check-in processes.
 */

export interface QRCodeData {
    timestamp: string; // UTC ISO string
    userId: number | string;
    bookingNumber: string;
    bookingId: number | string;
}

/**
 * Creates the data structure that will be encoded in the QR code
 *
 * @param userId - The ID of the user who made the booking
 * @param bookingNumber - The unique booking number
 * @param bookingId - The internal booking ID
 * @returns QRCodeData object ready for encoding
 */
export function createQRCodeData(userId: number | string, bookingNumber: string, bookingId: number | string): QRCodeData {
    return {
        timestamp: new Date().toISOString(),
        userId,
        bookingNumber,
        bookingId,
    };
}

/**
 * Encodes QR code data to base64 string
 *
 * @param data - The QR code data object
 * @returns Base64 encoded string
 */
export function encodeQRCodeData(data: QRCodeData): string {
    const jsonString = JSON.stringify(data);
    return btoa(jsonString);
}

/**
 * Decodes base64 QR code data back to object
 *
 * @param base64Data - Base64 encoded QR code data
 * @returns Decoded QR code data object
 * @throws Error if data is invalid
 */
export function decodeQRCodeData(base64Data: string): QRCodeData {
    try {
        const jsonString = atob(base64Data);
        const data = JSON.parse(jsonString) as QRCodeData;

        // Validate required fields
        if (!data.timestamp || !data.userId || !data.bookingNumber || !data.bookingId) {
            throw new Error('Invalid QR code data: missing required fields');
        }

        return data;
    } catch (error) {
        throw new Error(`Failed to decode QR code data: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
}

/**
 * Validates if QR code data is still valid (not too old)
 *
 * @param data - The QR code data object
 * @param maxAgeHours - Maximum age in hours (default: 24)
 * @returns True if valid, false if expired
 */
export function isQRCodeDataValid(data: QRCodeData, maxAgeHours: number = 24): boolean {
    const now = new Date();
    const qrTimestamp = new Date(data.timestamp);
    const ageInHours = (now.getTime() - qrTimestamp.getTime()) / (1000 * 60 * 60);

    return ageInHours <= maxAgeHours;
}

/**
 * Example usage:
 *
 * // Generate QR code data
 * const qrData = createQRCodeData(123, 'BK-2024-001', 456);
 * const base64Data = encodeQRCodeData(qrData);
 *
 * // Later, decode and validate
 * const decodedData = decodeQRCodeData(base64Data);
 * const isValid = isQRCodeDataValid(decodedData);
 */
