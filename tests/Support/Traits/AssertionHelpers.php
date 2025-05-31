<?php

namespace Tests\Support\Traits;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

trait AssertionHelpers
{
    /**
     * Assert that an Inertia response has the correct component and data structure
     */
    protected function assertInertiaResponse(TestResponse $response, string $component, array $expectedProps = []): void
    {
        $response->assertStatus(200);
        $response->assertInertia(function (AssertableInertia $page) use ($component, $expectedProps) {
            $page->component($component);

            foreach ($expectedProps as $key => $value) {
                if (is_array($value)) {
                    $page->has($key);
                } else {
                    $page->where($key, $value);
                }
            }
        });
    }

    /**
     * Assert that a collection has the expected structure
     */
    protected function assertCollectionStructure(array $collection, array $expectedStructure): void
    {
        $this->assertIsArray($collection);
        $this->assertNotEmpty($collection);

        $firstItem = $collection[0];

        foreach ($expectedStructure as $key) {
            $this->assertArrayHasKey($key, $firstItem, "Expected key '{$key}' not found in collection item");
        }
    }

    /**
     * Assert that an event data structure has all required fields
     */
    protected function assertEventDataStructure(array $eventData): void
    {
        $expectedFields = [
            'id',
            'name',
            'slug',
            'description',
            'event_status',
            'category_id',
            'published_at'
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $eventData, "Event data missing required field: {$field}");
        }
    }

    /**
     * Assert that a venue data structure has all required fields
     */
    protected function assertVenueDataStructure(array $venueData): void
    {
        $expectedFields = [
            'id',
            'name',
            'city',
            'country_id',
            'latitude',
            'longitude'
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $venueData, "Venue data missing required field: {$field}");
        }
    }

    /**
     * Assert that a category data structure has all required fields
     */
    protected function assertCategoryDataStructure(array $categoryData): void
    {
        $expectedFields = [
            'id',
            'name',
            'slug',
            'is_active'
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $categoryData, "Category data missing required field: {$field}");
        }
    }

    /**
     * Assert that a pagination response has the correct structure
     */
    protected function assertPaginationStructure(array $paginationData): void
    {
        $expectedFields = [
            'current_page',
            'data',
            'first_page_url',
            'from',
            'last_page',
            'last_page_url',
            'next_page_url',
            'path',
            'per_page',
            'prev_page_url',
            'to',
            'total'
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $paginationData, "Pagination data missing required field: {$field}");
        }
    }

    /**
     * Assert that a response contains validation errors for specific fields
     */
    protected function assertValidationErrors(TestResponse $response, array $expectedFields): void
    {
        $response->assertSessionHasErrors($expectedFields);

        foreach ($expectedFields as $field) {
            $this->assertTrue(
                session()->has("errors.{$field}"),
                "Expected validation error for field: {$field}"
            );
        }
    }

    /**
     * Assert that a user has specific roles
     */
    protected function assertUserHasRoles($user, array $expectedRoles): void
    {
        foreach ($expectedRoles as $role) {
            $this->assertTrue(
                $user->hasRole($role),
                "User does not have expected role: {$role}"
            );
        }
    }

    /**
     * Assert that a user has specific permissions
     */
    protected function assertUserHasPermissions($user, array $expectedPermissions): void
    {
        foreach ($expectedPermissions as $permission) {
            $this->assertTrue(
                $user->can($permission),
                "User does not have expected permission: {$permission}"
            );
        }
    }
}
