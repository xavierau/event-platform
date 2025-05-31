# Test Organization Guide

## Overview

This document outlines the test organization structure for the Laravel 12 Event Platform project, following TDD principles and leveraging Pest PHP for optimal performance and developer experience.

## Test Directory Structure

```
tests/
├── Unit/                               # Isolated component tests
│   ├── Models/                         # Model tests (relationships, scopes, methods)
│   ├── Services/                       # Service class tests (business logic)
│   ├── Actions/                        # Action class tests (single responsibility operations)
│   ├── DataTransferObjects/            # DTO validation and transformation tests
│   ├── Helpers/                        # Helper function and utility tests
│   ├── Rules/                          # Custom validation rule tests
│   └── Enums/                          # Enum tests
├── Feature/                            # End-to-end workflow tests
│   ├── Auth/                           # Authentication and authorization tests
│   ├── Admin/                          # Admin panel CRUD operations
│   │   ├── Events/                     # Event management tests
│   │   ├── Venues/                     # Venue management tests
│   │   ├── Categories/                 # Category management tests
│   │   ├── Users/                      # User management tests
│   │   └── Settings/                   # Settings and configuration tests
│   ├── Public/                         # Public-facing features
│   │   ├── Events/                     # Public event browsing and details
│   │   ├── Categories/                 # Category browsing
│   │   ├── Search/                     # Search functionality
│   │   └── Wishlist/                   # Wishlist functionality
│   ├── Api/                            # API endpoint tests
│   │   ├── V1/                         # API version 1
│   │   └── Mobile/                     # Mobile app specific endpoints
│   └── Integration/                    # Integration tests between components
├── Pest/                               # Modern Pest syntax tests (preferred)
│   ├── Models/                         # Modern model tests using Pest syntax
│   ├── Services/                       # Modern service tests using Pest syntax
│   └── Features/                       # Modern feature tests using Pest syntax
└── Support/                            # Test support files
    ├── Factories/                      # Custom test data factories
    ├── Traits/                         # Reusable test traits
    ├── Helpers/                        # Test helper functions
    └── Datasets/                       # Pest datasets for parameterized tests
```

## Testing Framework: Pest PHP

### Why Pest?

- **Performance**: 60% faster execution with parallel testing (`--parallel` flag)
- **Modern Syntax**: More readable and expressive test syntax
- **Better DX**: Enhanced developer experience with elegant assertions
- **Laravel Integration**: Seamless integration with Laravel testing features

### Execution Commands

**⚠️ ALWAYS use `--parallel` for optimal performance:**

```bash
# ✅ RECOMMENDED: Run all tests in parallel (fastest)
./vendor/bin/pest --parallel

# ✅ Run specific test file in parallel
./vendor/bin/pest tests/Unit/Services/EventServiceTest.php --parallel

# ✅ Run tests with coverage in parallel
./vendor/bin/pest --parallel --coverage

# ✅ Run filtered tests in parallel
./vendor/bin/pest --filter="can create event" --parallel

# Performance comparison
./test-performance.sh
```

## Test Naming Conventions

### File Naming
- **Unit Tests**: `{ClassName}Test.php` (e.g., `EventServiceTest.php`)
- **Feature Tests**: `{FeatureName}Test.php` (e.g., `EventManagementTest.php`)
- **Pest Tests**: `{ClassName}PestTest.php` (e.g., `EventServicePestTest.php`)

### Test Method Naming
- **Pest Syntax**: Use descriptive `it()` or `test()` functions
- **PHPUnit Syntax**: Use `test_` prefix with snake_case

```php
// ✅ Preferred Pest Syntax
it('can create an event with valid data', function () {
    // test implementation
});

test('user can publish an event', function () {
    // test implementation
});

// ✅ Acceptable PHPUnit Syntax
public function test_can_create_event_with_valid_data()
{
    // test implementation
}
```

## Test Types and Responsibilities

### Unit Tests
- **Models**: Relationships, scopes, mutators, accessors, business logic methods
- **Services**: Business logic orchestration, method behavior in isolation
- **Actions**: Single-responsibility operations, data transformation
- **DTOs**: Validation rules, data casting, transformation logic
- **Helpers**: Utility functions and helper methods

### Feature Tests
- **Controllers**: HTTP responses, route behavior, middleware
- **Authentication**: Login, registration, password reset workflows
- **Authorization**: Permission and role-based access control
- **Validation**: Form validation, error handling
- **Integration**: Component interaction, database transactions

### Performance Benchmarks

| Test Type | Sequential | Parallel | Improvement |
|-----------|------------|----------|-------------|
| Unit Tests | ~2.1s | ~0.8s | 62% faster |
| Feature Tests | ~4.7s | ~1.7s | 64% faster |
| Full Suite | ~6.8s | ~2.5s | 63% faster |

## Test Data Management

### Factories
- Located in `database/factories/`
- Use for consistent test data creation
- Leverage factory states for different scenarios

### Seeders
- Test-specific seeders in `database/seeders/Testing/`
- Use for complex scenario setup

### Datasets (Pest)
- Located in `tests/Support/Datasets/`
- Use for parameterized testing

```php
// Example dataset usage
it('validates event data', function ($input, $expected) {
    expect(EventData::from($input))->toBeValid($expected);
})->with('event_validation_dataset');
```

## Code Coverage Goals

- **Unit Tests**: 95%+ coverage for business logic
- **Feature Tests**: 100% coverage for user workflows
- **Integration Tests**: Key component interactions

## Best Practices

### 1. Test Structure (AAA Pattern)
```php
it('can calculate event price with discount', function () {
    // Arrange
    $event = Event::factory()->create(['base_price' => 100]);
    $discount = 0.2;

    // Act
    $finalPrice = $event->calculatePrice($discount);

    // Assert
    expect($finalPrice)->toBe(80.0);
});
```

### 2. Database Management
```php
// Use transactions for faster tests
uses(RefreshDatabase::class);

// Or use specific database strategies
beforeEach(function () {
    $this->artisan('migrate:fresh');
});
```

### 3. Mocking and Faking
```php
// Prefer Laravel's built-in fakes
beforeEach(function () {
    Mail::fake();
    Storage::fake();
});

// Use mocks for external services
it('calls external API correctly', function () {
    $mock = Mockery::mock(ExternalService::class);
    $mock->shouldReceive('process')->once()->andReturn('success');
    
    app()->instance(ExternalService::class, $mock);
    
    // test implementation
});
```

## Migration Path

### Phase 1: Reorganize Existing Tests
1. Move tests to appropriate directories
2. Rename files to follow conventions
3. Update namespaces and imports

### Phase 2: Convert to Pest Syntax
1. Convert PHPUnit tests to Pest syntax
2. Leverage Pest's elegant assertions
3. Add dataset-driven tests where appropriate

### Phase 3: Add Missing Tests
1. Identify untested components
2. Write comprehensive test coverage
3. Add integration tests for critical workflows

## Continuous Integration

### Pre-commit Hooks
```bash
# Run tests before commit
./vendor/bin/pest --parallel

# Run with coverage reporting
./vendor/bin/pest --parallel --coverage --min=80
```

### CI/CD Pipeline
- Use parallel execution in CI for faster builds
- Generate coverage reports
- Fail builds on test failures or low coverage

## Troubleshooting

### Common Issues
1. **Slow tests**: Always use `--parallel` flag
2. **Database conflicts**: Use `RefreshDatabase` trait
3. **Flaky tests**: Review test isolation and mocking

### Performance Monitoring
- Track test execution times
- Identify slow tests for optimization
- Monitor parallel execution efficiency

---

This test organization follows Laravel and Pest best practices while maintaining the project's TDD methodology and aggressive testing strategy for early error detection. 
