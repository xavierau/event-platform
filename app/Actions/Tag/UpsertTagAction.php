<?php

namespace App\Actions\Tag;

use App\DataTransferObjects\Tag\TagData;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpsertTagAction
{
    public function execute(?Tag $tag, TagData $tagData): Tag
    {
        DB::beginTransaction();

        try {
            $data = [
                'name' => $tagData->name,
                'slug' => $tagData->slug ?: Str::slug($tagData->name['en'] ?? ''), // Auto-generate slug if not provided
            ];

            if ($tag) {
                // Check for slug uniqueness on update, excluding the current tag
                if (Tag::where('slug', $data['slug'])->where('id', '!=', $tag->id)->exists()) {
                    throw new \InvalidArgumentException('Slug already exists.');
                }
                $tag->update($data);
            } else {
                // Check for slug uniqueness on create
                if (Tag::where('slug', $data['slug'])->exists()) {
                    throw new \InvalidArgumentException('Slug already exists.');
                }
                $tag = Tag::create($data);
            }

            DB::commit();

            return $tag;
        } catch (\Exception $e) {
            DB::rollBack();
            // Re-throw the exception to be handled by the service or controller
            throw $e;
        }
    }
}
