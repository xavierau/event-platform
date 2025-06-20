<?php

use App\Actions\Organizer\GrantOrganizerPermissionAction;
use App\Enums\OrganizerPermissionEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Database\Factories\OrganizerFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new GrantOrganizerPermissionAction();

    $this->organizer = Organizer::factory()->create();
    $this->targetUser = User::factory()->create();
    $this->grantingUser = User::factory()->create();
});

describe('GrantOrganizerPermissionAction', function () {

    it('can grant a single permission to a user', function () {
        // Arrange: Set up user memberships
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode(['view_events']),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->grantingUser->id,
        ]);

        $this->organizer->users()->attach($this->grantingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act: Grant permission
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permission: OrganizerPermissionEnum::EDIT_EVENTS->value,
            grantedBy: $this->grantingUser->id
        );

        // Assert
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toContain('view_events')
            ->and($permissions)->toContain(OrganizerPermissionEnum::EDIT_EVENTS->value);
    });

    it('can grant multiple permissions to a user', function () {
        // Arrange
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode(['view_events']),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->grantingUser->id,
        ]);

        $this->organizer->users()->attach($this->grantingUser->id, [
            'role_in_organizer' => 'owner',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        $newPermissions = [
            OrganizerPermissionEnum::EDIT_EVENTS->value,
            OrganizerPermissionEnum::VIEW_ANALYTICS->value,
        ];

        // Act
        $result = $this->action->executeMultiple(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permissions: $newPermissions,
            grantedBy: $this->grantingUser->id
        );

        // Assert
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toContain('view_events')
            ->and($permissions)->toContain(OrganizerPermissionEnum::EDIT_EVENTS->value)
            ->and($permissions)->toContain(OrganizerPermissionEnum::VIEW_ANALYTICS->value);
    });

    it('prevents duplicate permissions when granting', function () {
        // Arrange: User already has the permission
        $existingPermission = OrganizerPermissionEnum::EDIT_EVENTS->value;

        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode([$existingPermission]),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->grantingUser->id,
        ]);

        $this->organizer->users()->attach($this->grantingUser->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act: Try to grant the same permission
        $result = $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permission: $existingPermission,
            grantedBy: $this->grantingUser->id
        );

        // Assert: Should still succeed but not duplicate
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toHaveCount(1)
            ->and($permissions)->toContain($existingPermission);
    });

    it('throws exception when granting user is not authorized', function () {
        // Arrange: Granting user has insufficient permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode(['view_events']),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->grantingUser->id,
        ]);

        $this->organizer->users()->attach($this->grantingUser->id, [
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
            grantedBy: $this->grantingUser->id
        ))->toThrow(UnauthorizedOperationException::class);
    });

    it('throws exception when target user is not a member', function () {
        // Arrange: Only granting user is a member
        $this->organizer->users()->attach($this->grantingUser->id, [
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
            grantedBy: $this->grantingUser->id
        ))->toThrow(InvalidArgumentException::class, 'User is not a member of this organizer');
    });

    it('throws exception when granting user is not a member', function () {
        // Arrange: Only target user is a member
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode(['view_events']),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => null,
        ]);

        // Act & Assert
        expect(fn() => $this->action->execute(
            organizerId: $this->organizer->id,
            userId: $this->targetUser->id,
            permission: OrganizerPermissionEnum::EDIT_EVENTS->value,
            grantedBy: $this->grantingUser->id
        ))->toThrow(UnauthorizedOperationException::class);
    });

    it('throws exception for invalid permission', function () {
        // Arrange
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode(['view_events']),
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->grantingUser->id,
        ]);

        $this->organizer->users()->attach($this->grantingUser->id, [
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
            grantedBy: $this->grantingUser->id
        ))->toThrow(InvalidArgumentException::class, 'Invalid permission provided');
    });

    it('handles user with null permissions gracefully', function () {
        // Arrange: User has null permissions
        $this->organizer->users()->attach($this->targetUser->id, [
            'role_in_organizer' => 'staff',
            'permissions' => null, // No custom permissions
            'joined_at' => now(),
            'is_active' => true,
            'invited_by' => $this->grantingUser->id,
        ]);

        $this->organizer->users()->attach($this->grantingUser->id, [
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
            grantedBy: $this->grantingUser->id
        );

        // Assert
        expect($result)->toBeTrue();

        $updatedUser = $this->organizer->users()->where('user_id', $this->targetUser->id)->first();
        $permissions = json_decode($updatedUser->pivot->permissions, true);

        expect($permissions)->toHaveCount(1)
            ->and($permissions)->toContain(OrganizerPermissionEnum::EDIT_EVENTS->value);
    });
});
