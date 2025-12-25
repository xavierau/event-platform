<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Services;

use App\Modules\TemporaryRegistration\Actions\CreateTemporaryRegistrationPageAction;
use App\Modules\TemporaryRegistration\Actions\UpdateTemporaryRegistrationPageAction;
use App\Modules\TemporaryRegistration\DataTransferObjects\TemporaryRegistrationPageData;
use App\Modules\TemporaryRegistration\Models\TemporaryRegistrationPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TemporaryRegistrationService
{
    public function __construct(
        private readonly CreateTemporaryRegistrationPageAction $createAction,
        private readonly UpdateTemporaryRegistrationPageAction $updateAction
    ) {}

    public function create(TemporaryRegistrationPageData $data): TemporaryRegistrationPage
    {
        return $this->createAction->execute($data);
    }

    public function update(
        TemporaryRegistrationPage $page,
        TemporaryRegistrationPageData $data
    ): TemporaryRegistrationPage {
        return $this->updateAction->execute($page, $data);
    }

    public function delete(TemporaryRegistrationPage $page): void
    {
        $page->delete();
    }

    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return TemporaryRegistrationPage::with(['membershipLevel', 'creator'])
            ->withCount('registeredUsers')
            ->latest()
            ->paginate($perPage);
    }

    public function findByIdentifier(string $identifier): ?TemporaryRegistrationPage
    {
        return TemporaryRegistrationPage::where('slug', $identifier)
            ->orWhere('token', $identifier)
            ->with('membershipLevel')
            ->first();
    }

    public function toggleActive(TemporaryRegistrationPage $page): TemporaryRegistrationPage
    {
        $page->update(['is_active' => !$page->is_active]);

        return $page->fresh();
    }

    public function regenerateToken(TemporaryRegistrationPage $page): TemporaryRegistrationPage
    {
        $page->update(['token' => Str::random(32)]);

        return $page->fresh();
    }
}
