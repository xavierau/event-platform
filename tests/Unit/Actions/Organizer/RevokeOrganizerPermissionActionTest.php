<?php

use App\Actions\Organizer\RevokeOrganizerPermissionAction;
use App\Enums\OrganizerPermissionEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new RevokeOrganizerPermissionAction();

    $this->organizer = Organizer::factory()->create();
    $this->targetUser = User::factory()->create();
    $this->revokingUser = User::factory()->create();
});

describe('RevokeOrganizerPermissionAction', function () {

    it('can revoke a single permission from a user', function () {
        // Arrange: Set up user memberships with permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([
                OrganizerPermissionEnum::VIEW_EVENTS->value,
                OrganizerPermissionEnum::EDIT_EVENTS->value,
                OrganizerPermissionEnum::VIEW_ANALYTICS->value
            ]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->revokingUser->id,
        ]);

        $this->organizer->users()->attach($this->revokingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act: Revoke permission
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permission: OrganizerPermissionEnum::EDIT_EVENTS->value,
            revokedBy: $this->revokingUser->id
        );

        // Assert
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toContain(OrganizerPermissionEnum::VIEW_EVENTS->value)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_ANALYTICS->value)
            ->and($permissions)->not->toContain(OrganizerPermissionEnum::EDIT_EVENTS->value);
    });

    it('can revoke multiple permissions from a user', function () {
        // Arrange
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([
                OrganizerPermissionEnum::VIEW_EVENTS->value,
                OrganizerPermissionEnum::EDIT_EVENTS->value,
                OrganizerPermissionEnum::VIEW_ANALYTICS->value,
                OrganizerPermissionEnum::CREATE_EVENTS->value
            ]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->revokingUser->id,
        ]);

        $this->organizer->users()->attach($this->revokingUser->id, [
            'role_in_organizer' => 'owner',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        $permissionsToRevoke = [
            OrganizerPermissionEnum::EDIT_EVENTS->value,
            OrganizerPermissionEnum::CREATE_EVENTS->value,
        ];

        // Act
        $result = $this->action->executeMultiple(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: $permissionsToRevoke,
            revokedBy: $this->revokingUser->id
        );

        // Assert
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toContain(OrganizerPermissionEnum::VIEW_EVENTS->value)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_ANALYTICS->value)
            ->and($permissions)->not->toContain(OrganizerPermissionEnum::EDIT_EVENTS->value)
            ->and($permissions)->not->toContain(OrganizerPermissionEnum::CREATE_EVENTS->value);
    });

    it('handles revoking non-existent permission gracefully', function () {
        // Arrange: User doesn't have the permission we're trying to revoke
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->revokingUser->id,
        ]);

        $this->organizer->users()->attach($this->revokingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act: Try to revoke permission they don't have
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permission: OrganizerPermissionEnum::EDIT_EVENTS->value,
            revokedBy: $this->revokingUser->id
        );

        // Assert: Should succeed but not change anything
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toHaveCount(1)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_EVENTS->value);
    });

    it('throws exception when revoking user is not authorized', function () {
        // Arrange: Revoking user has insufficient permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([OrganizerPermissionEnum::EDIT_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->revokingUser->id,
        ]);

        $this->organizer->users()->attach($this->revokingUser->id, [
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
            permission: OrganizerPermissionEnum::EDIT_EVENTS->value,
            revokedBy: $this->revokingUser->id
        ))->toThrow(UnauthorizedOperationException::class);
    });

    it('throws exception when target user is not a member', function () {
        // Arrange: Only revoking user is a member
        $this->organizer->users()->attach($this->revokingUser->id, [
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
            permission: OrganizerPermissionEnum::EDIT_EVENTS->value,
            revokedBy: $this->revokingUser->id
        ))->toThrow(InvalidArgumentException::class, 'User is not a member of this organizer');
    });

    it('throws exception for invalid permission', function () {
        // Arrange
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->revokingUser->id,
        ]);

        $this->organizer->users()->attach($this->revokingUser->id, [
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
            permission: 'invalid_permission',
            revokedBy: $this->revokingUser->id
        ))->toThrow(InvalidArgumentException::class, 'Invalid permission provided');
    });

    it('handles user with null permissions gracefully', function () {
        // Arrange: User has null permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => null, // No custom permissions
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->revokingUser->id,
        ]);

        $this->organizer->users()->attach($this->revokingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permission: OrganizerPermissionEnum::EDIT_EVENTS->value,
            revokedBy: $this->revokingUser->id
        );

        // Assert: Should succeed but permissions remain null/empty
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toBeEmpty();
    });
});
