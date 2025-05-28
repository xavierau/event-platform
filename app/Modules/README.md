# Modules

This directory contains the modular components of the Event Platform application, following a modular monolithic design approach.

## Module Structure

Each module is organized with the following structure:
- `Models/` - Eloquent models specific to the module
- `DataTransferObjects/` - DTOs for data validation and transfer
- `Actions/` - Single-responsibility action classes
- `Services/` - Service classes that orchestrate actions
- `Enums/` - Enumerations for type safety
- `Exceptions/` - Module-specific exceptions

## Available Modules

### 1. Wallet Module (`App\Modules\Wallet`)

The Wallet module manages user points and kill points system.

**Components:**
- **Models:**
  - `Wallet` - User wallet with points and kill points balances
  - `WalletTransaction` - Transaction history for all wallet activities

- **Enums:**
  - `WalletTransactionType` - Types of wallet transactions (earn/spend points/kill points, transfers, etc.)

- **DTOs:**
  - `WalletData` - Wallet creation/update data
  - `WalletTransactionData` - Transaction data with validation

- **Actions:**
  - `AddPointsAction` - Add points to wallet with transaction logging
  - `SpendPointsAction` - Spend points with validation and logging
  - `AddKillPointsAction` - Add kill points with transaction logging
  - `SpendKillPointsAction` - Spend kill points with validation and logging

- **Services:**
  - `WalletService` - Orchestrates wallet operations, provides clean API

- **Exceptions:**
  - `InsufficientPointsException` - Thrown when user lacks sufficient points
  - `InsufficientKillPointsException` - Thrown when user lacks sufficient kill points

**Key Features:**
- Dual point system (regular points and kill points)
- Complete transaction history
- Point transfers between users
- Automatic wallet creation for users
- Balance validation and tracking

### 2. Membership Module (`App\Modules\Membership`)

The Membership module manages user memberships and subscription levels.

**Components:**
- **Models:**
  - `MembershipLevel` - Membership tiers with translatable content
  - `UserMembership` - User's membership records with status tracking

- **Enums:**
  - `MembershipStatus` - Membership statuses (active, expired, cancelled, etc.)
  - `PaymentMethod` - Payment methods (points, kill points, stripe, admin grant, etc.)

- **DTOs:**
  - `MembershipPurchaseData` - Membership purchase request data

**Key Features:**
- Translatable membership levels (name, description)
- Multiple payment methods including wallet points
- Membership expiration tracking
- Auto-renewal support
- User limits per membership level
- Benefit system for membership perks

## Integration with User Model

Both modules extend the `User` model with relationships and helper methods:

**Wallet Integration:**
- `wallet()` - HasOne relationship to user's wallet
- `getPointsBalance()` - Get current points balance
- `getKillPointsBalance()` - Get current kill points balance
- `hasEnoughPoints()` - Check if user has sufficient points
- `hasEnoughKillPoints()` - Check if user has sufficient kill points

**Membership Integration:**
- `currentMembership()` - Get user's active membership
- `memberships()` - Get all user memberships
- `hasMembership()` - Check if user has active membership

## Database Tables

### Wallet Tables:
- `user_wallets` - User wallet records
- `wallet_transactions` - Transaction history

### Membership Tables:
- `membership_levels` - Available membership tiers
- `user_memberships` - User membership records

## Usage Examples

### Wallet Operations:
```php
// Add points to user
$walletService->addPoints($user, 100, 'Event booking reward');

// Spend points
$walletService->spendPoints($user, 50, 'Membership purchase');

// Transfer points between users
$walletService->transferPoints($fromUser, $toUser, 25, 'Gift');

// Check balance
$balance = $walletService->getBalance($user);
```

### Membership Operations:
```php
// Check if user has membership
if ($user->hasMembership()) {
    // Apply membership benefits
}

// Get membership level benefits
$benefits = $user->currentMembership->membershipLevel->benefits;
```

## Task Status

These modules address the following tasks from the project task list:

**Wallet System (WAL-001 to WAL-006):**
- ✅ WAL-001: Wallet Entity created
- ✅ WAL-002: WalletTransaction Entity created  
- ✅ WAL-003: Wallet DTOs and Actions implemented
- ✅ WAL-004: WalletService implemented
- ⏳ WAL-005: API Controllers (pending)
- ✅ WAL-006: User Model wallet methods added

**Membership System (MEM-001 to MEM-006):**
- ✅ MEM-001: MembershipLevel Entity created
- ✅ MEM-002: UserMembership Entity created
- ⏳ MEM-003: Membership DTOs and Actions (partially implemented)
- ⏳ MEM-004: MembershipService (pending)
- ⏳ MEM-005: API Controllers (pending)
- ✅ MEM-006: User Model membership methods added 
