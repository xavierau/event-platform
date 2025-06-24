# Coupon Module

## Overview

This module encapsulates all functionality related to creating, issuing, and redeeming coupons within the Event Platform. It is designed to be a self-contained domain, providing a clear service layer for other parts of the application (like Admin Panels or public-facing APIs) to interact with.

The architecture follows the established project patterns, emphasizing Domain-Driven Design, single-responsibility Actions, and a clear separation of concerns.

## Core Concepts

The module is built around three primary models:

1.  **`Coupon` (The Template)**: This is the master definition of a coupon campaign, created by an Organizer or Platform Admin. It defines the rules:
    *   **Behavior**: Is it single-use or multi-use (like a punch card)?
    *   **Limits**: What is the usage limit per instance? What is the total issuance limit for the campaign?
    *   **Validity**: When is the coupon valid?
    *   **Rules**: How many can be used in a single transaction?

2.  **`UserCoupon` (The Instance)**: This represents a specific coupon that has been issued to a user. It is the "physical" coupon in the user's digital wallet.
    *   It has a **unique code** for QR scanning.
    *   It tracks its own usage (`times_used`).
    *   Its status (`ACTIVE`, `FULLY_USED`, `EXPIRED`) is managed independently.

3.  **`CouponUsageLog` (The Audit Trail)**: This model records every successful redemption event, linking a `UserCoupon` instance to the admin/organizer who scanned it and the timestamp of the redemption.

## Key Operations

-   **Issuing**: The `IssueCouponToUserAction` is responsible for creating `UserCoupon` instances from a `Coupon` template and assigning them to a user. It respects the `max_issuance` limits of the campaign.
-   **Validation**: The `CouponService` provides methods to validate a coupon's `unique_code` to check its status, validity period, and remaining uses.
-   **Redemption**: The `RedeemUserCouponAction` handles the core logic for the QR scanner. It validates the coupon, increments its usage counter, updates its status if necessary, and creates a log entry.

## Integration

Other parts of the application should interact with this module primarily through the `CouponService`, which provides a clean, public-facing API for all coupon-related functionalities. 
