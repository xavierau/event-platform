<?php

use App\Actions\Organizer\UpdateOrganizerPermissionsAction;
use App\Enums\OrganizerPermissionEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new UpdateOrganizerPermissionsAction();

    $this->organizer = Organizer::factory()->create();
    $this->targetUser = User::factory()->create();
    $this->updatingUser = User::factory()->create();
});

describe('UpdateOrganizerPermissionsAction', function () {

    it('can completely replace user permissions', function () {
        // Arrange: User has some existing permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([
                OrganizerPermissionEnum::VIEW_EVENTS->value,
                OrganizerPermissionEnum::EDIT_EVENTS->value,
                OrganizerPermissionEnum::VIEW_ANALYTICS->value
            ]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->updatingUser->id,
        ]);

        $this->organizer->users()->attach($this->updatingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        $newPermissions = [
            OrganizerPermissionEnum::CREATE_EVENTS->value,
            OrganizerPermissionEnum::VIEW_VENUES->value,
        ];

        // Act: Replace all permissions
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: $newPermissions,
            updatedBy: $this->updatingUser->id
        );

        // Assert: Old permissions should be gone, new ones should be present
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toHaveCount(2)
            ->and($permissions)->toContain(OrganizerPermissionEnum::CREATE_EVENTS->value)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_VENUES->value)
            ->and($permissions)->not->toContain(OrganizerPermissionEnum::VIEW_EVENTS->value)
            ->and($permissions)->not->toContain(OrganizerPermissionEnum::EDIT_EVENTS->value)
            ->and($permissions)->not->toContain(OrganizerPermissionEnum::VIEW_ANALYTICS->value);
    });

    it('can set permissions to empty array', function () {
        // Arrange: User has existing permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([
                OrganizerPermissionEnum::VIEW_EVENTS->value,
                OrganizerPermissionEnum::EDIT_EVENTS->value,
            ]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->updatingUser->id,
        ]);

        $this->organizer->users()->attach($this->updatingUser->id, [
            'role_in_organizer' => 'owner',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act: Set permissions to empty
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: [],
            updatedBy: $this->updatingUser->id
        );

        // Assert: User should have no custom permissions
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toBeEmpty();
    });

    it('handles user with null permissions gracefully', function () {
        // Arrange: User has null permissions initially
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->updatingUser->id,
        ]);

        $this->organizer->users()->attach($this->updatingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        $newPermissions = [
            OrganizerPermissionEnum::VIEW_EVENTS->value,
            OrganizerPermissionEnum::VIEW_ANALYTICS->value,
        ];

        // Act: Set new permissions
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: $newPermissions,
            updatedBy: $this->updatingUser->id
        );

        // Assert
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toHaveCount(2)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_EVENTS->value)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_ANALYTICS->value);
    });

    it('removes duplicate permissions automatically', function () {
        // Arrange
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->updatingUser->id,
        ]);

        $this->organizer->users()->attach($this->updatingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act: Set permissions with duplicates
        $newPermissions = [
            OrganizerPermissionEnum::VIEW_EVENTS->value,
            OrganizerPermissionEnum::EDIT_EVENTS->value,
            OrganizerPermissionEnum::VIEW_EVENTS->value, // Duplicate
            OrganizerPermissionEnum::EDIT_EVENTS->value, // Duplicate
        ];

        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: $newPermissions,
            updatedBy: $this->updatingUser->id
        );

        // Assert: Should have unique permissions only
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toHaveCount(2)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_EVENTS->value)
            ->and($permissions)->toContain(OrganizerPermissionEnum::EDIT_EVENTS->value);
    });

    it('throws exception when updating user is not authorized', function () {
        // Arrange: Updating user has insufficient permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->updatingUser->id,
        ]);

        $this->organizer->users()->attach($this->updatingUser->id, [
            'role_in_organizer' => 'viewer', // Insufficient role
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act & Assert
        expect(fn() => $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: [OrganizerPermissionEnum::EDIT_EVENTS->value],
            updatedBy: $this->updatingUser->id
        ))->toThrow(UnauthorizedOperationException::class);
    });

    it('throws exception when target user is not a member', function () {
        // Arrange: Only updating user is a member
        $this->organizer->users()->attach($this->updatingUser->id, [
            'role_in_organizer' => 'owner',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act & Assert
        expect(fn() => $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: [OrganizerPermissionEnum::VIEW_EVENTS->value],
            updatedBy: $this->updatingUser->id
        ))->toThrow(InvalidArgumentException::class, 'User is not a member of this organizer');
    });

    it('throws exception for invalid permissions', function () {
        // Arrange
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->updatingUser->id,
        ]);

        $this->organizer->users()->attach($this->updatingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act & Assert
        expect(fn() => $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: ['invalid_permission', OrganizerPermissionEnum::VIEW_EVENTS->value],
            updatedBy: $this->updatingUser->id
        ))->toThrow(InvalidArgumentException::class, 'Invalid permission provided');
    });
});
