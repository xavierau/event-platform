<?php

namespace App\Services;

use App\Actions\Tag\UpsertTagAction;
use App\DataTransferObjects\Tag\TagData;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    protected UpsertTagAction $upsertTagAction;

    public function __construct(UpsertTagAction $upsertTagAction)
    {
        $this->upsertTagAction = $upsertTagAction;
    }

    public function createTag(TagData $tagData): Tag
    {
        return $this->upsertTagAction->execute(null, $tagData);
    }

    public function updateTag(int $tagId, TagData $tagData): Tag
    {
        $tag = $this->findTag($tagId);
        return $this->upsertTagAction->execute($tag, $tagData);
    }

    public function deleteTag(int $tagId): bool
    {
        $tag = $this->findTag($tagId);
        return $tag->delete();
    }

    public function findTag(int $tagId): Tag
    {
        return Tag::findOrFail($tagId);
    }

    public function getAllTags(): Collection|array
    {
        return Tag::all();
    }

    public function getPaginatedTags(int $perPage = 15): LengthAwarePaginator
    {
        return Tag::latest()->paginate($perPage);
    }

    // Consider adding a method to get tags for a select list, perhaps with translations
    public function getTagsForSelect(): array
    {
        return Tag::all()->mapWithKeys(function (Tag $tag) {
            return [$tag->id => $tag->getTranslation('name', app()->getLocale()) . ' (' . $tag->getTranslation('name', 'en') . ')'];
        })->all();
    }
}
