# PromotionalModal Module

A comprehensive promotional modal system for the Laravel 12 Event Platform that supports both modal dialogs and banner notifications with advanced targeting, analytics, and user engagement tracking.

## Features

### Core Functionality
- **Modal Types**: Supports both popup modals and banner notifications
- **Translatable Content**: Multi-language support using Spatie Translatable
- **Media Support**: Banner images and background images via Spatie Media Library
- **Advanced Targeting**: Page-specific, membership level, and user segment targeting
- **Display Control**: Configurable display frequency, timing, and cooldown periods
- **Analytics**: Comprehensive impression, click, and conversion tracking
- **A/B Testing**: Support for multiple promotional variants

### Backend Architecture
- **Domain-Driven Design**: Follows DDD principles with clear separation of concerns
- **SOLID Principles**: Clean, maintainable code following SOLID design patterns
- **Test-Driven Development**: Comprehensive Pest test coverage
- **Laravel 12 Compatibility**: Uses Laravel 12's streamlined structure
- **Spatie Integration**: Leverages Spatie packages for permissions, media, and translations

## Installation

The module is already integrated into the Laravel application. The following components are included:

### Database Schema
- `promotional_modals`: Main promotional modal data
- `promotional_modal_impressions`: User interaction tracking

### Backend Components
- **Models**: `PromotionalModal`, `PromotionalModalImpression`
- **Services**: `PromotionalModalService` - Main business logic
- **Actions**: `UpsertPromotionalModalAction`, `RecordImpressionAction`
- **Controllers**: `PromotionalModalController` (public API), `AdminPromotionalModalController` (admin)
- **DTOs**: `PromotionalModalData` with comprehensive validation
- **Policies**: `PromotionalModalPolicy` for authorization
- **Factory**: `PromotionalModalFactory` for testing

### Frontend Components
- **PromotionalModal.vue**: Modal dialog component
- **PromotionalBanner.vue**: Banner notification component  
- **PromotionalDisplay.vue**: Smart wrapper for automatic display

## Usage

### Backend Usage

#### Creating a Promotional Modal
```php
use App\Modules\PromotionalModal\Services\PromotionalModalService;
use App\Modules\PromotionalModal\DataTransferObjects\PromotionalModalData;

$service = app(PromotionalModalService::class);

$data = new PromotionalModalData(
    title: ['en' => 'Special Offer!', 'zh-TW' => '特別優惠！'],
    content: ['en' => 'Get 20% off your next booking!', 'zh-TW' => '下次預訂享受8折優惠！'],
    type: 'modal',
    pages: ['home', 'events'],
    membership_levels: [1, 2, 3],
    display_frequency: 'once',
    is_active: true
);

$modal = $service->createModal($data);
```

#### Getting Promotions for User
```php
$modals = $service->getModalsForUser(
    user: $user,
    page: 'home',
    type: 'modal',
    sessionId: session()->getId(),
    limit: 3
);
```

#### Recording User Interactions
```php
$service->recordImpression($modal, 'impression', $user);
$service->recordImpression($modal, 'click', $user);
$service->recordImpression($modal, 'dismiss', $user);
```

### Frontend Usage

#### Basic Integration
```vue
<template>
  <div>
    <!-- Your page content -->
    <PromotionalDisplay 
      :page="currentPage"
      :limit="3"
      :auto-show="true"
    />
  </div>
</template>

<script setup>
import PromotionalDisplay from '@/components/PromotionalModal/PromotionalDisplay.vue';

const currentPage = 'home'; // or 'events', 'event-detail', etc.
</script>
```

#### Manual Control
```vue
<script setup>
import { ref } from 'vue';
import PromotionalDisplay from '@/components/PromotionalModal/PromotionalDisplay.vue';

const promotionalRef = ref();

// Manually show specific modal
const showPromotion = (id) => {
  promotionalRef.value?.showModal(id);
};

// Hide all promotions
const hideAllPromotions = () => {
  promotionalRef.value?.hideAllModals();
};

// Refresh promotions
const refreshPromotions = () => {
  promotionalRef.value?.refreshPromotions();
};
</script>

<template>
  <PromotionalDisplay 
    ref="promotionalRef"
    :page="currentPage"
    :auto-show="false"
  />
</template>
```

## API Endpoints

### Public API
- `GET /api/promotional-modals` - Get promotions for current user/page
- `GET /api/promotional-modals/{id}` - Get specific promotion
- `POST /api/promotional-modals/{id}/impression` - Record impression/click/dismiss
- `POST /api/promotional-modals/batch-impressions` - Batch record impressions

### Admin API
- `GET /admin/api/promotional-modals` - List all promotions
- `POST /admin/api/promotional-modals` - Create promotion
- `PUT /admin/api/promotional-modals/{id}` - Update promotion
- `DELETE /admin/api/promotional-modals/{id}` - Delete promotion
- `POST /admin/api/promotional-modals/{id}/toggle` - Toggle active status
- `GET /admin/api/promotional-modals/{id}/analytics` - Get analytics
- `GET /admin/api/promotional-modals/system/analytics` - System-wide analytics

## Configuration

### Display Rules
Promotions can be targeted based on:
- **Pages**: Specific pages or all pages
- **Membership Levels**: User membership tiers
- **User Segments**: Custom user segmentation
- **Time Windows**: Start/end dates for campaigns
- **Display Frequency**: Once, daily, weekly, or always
- **Cooldown Periods**: Hours between displays

### Analytics Tracking
The system automatically tracks:
- **Impressions**: When a promotion is displayed
- **Clicks**: When user clicks promotion button
- **Dismissals**: When user dismisses promotion
- **Conversion Rates**: Click-through rates
- **Page Performance**: Which pages perform best

## Testing

Run the comprehensive test suite:
```bash
./vendor/bin/pest tests/Feature/PromotionalModalServiceTest.php --parallel
./vendor/bin/pest tests/Feature/PromotionalModalControllerTest.php --parallel
```

## Permissions

The module uses the following permissions:
- `promotional_modals.view`
- `promotional_modals.create` 
- `promotional_modals.update`
- `promotional_modals.delete`
- `promotional_modals.manage`
- `promotional_modals.view_analytics`
- `promotional_modals.bulk_update`
- `promotional_modals.toggle_status`

## Performance Considerations

- **Database Indexing**: Optimized indexes for query performance
- **Batch Processing**: Batch impression recording for high-traffic sites
- **Caching**: Service layer supports caching strategies
- **Lazy Loading**: Frontend components load promotions asynchronously
- **Session Tracking**: Anonymous user tracking via sessions

## Security Features

- **Authorization Policies**: Role-based access control
- **Input Validation**: Comprehensive DTO validation
- **XSS Prevention**: Content sanitization
- **CSRF Protection**: Built-in Laravel CSRF protection
- **Rate Limiting**: API endpoints support rate limiting

This module provides a enterprise-grade promotional modal system with comprehensive features for targeting, tracking, and optimization.