<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,         // User-defined name for the media item
        public readonly string $file_name,    // Original file name
        public readonly string $mime_type,
        public readonly int $size,         // Size in bytes
        public readonly string $original_url, // URL to the original media
        public readonly ?string $preview_url = null, // URL to a preview version (if generated)
        public readonly ?string $thumbnail_url = null, // URL to a thumbnail version (if generated)
        public readonly ?int $order_column = null,
        public readonly array $custom_properties = [],
        public readonly string $created_at,
        public readonly string $updated_at
    ) {}

    public static function fromModel(Media $media): self
    {
        return new self(
            id: $media->id,
            name: $media->name,
            file_name: $media->file_name,
            mime_type: $media->mime_type,
            size: $media->size,
            original_url: $media->getFullUrl(), // Get original URL
            preview_url: $media->hasGeneratedConversion('preview') ? $media->getFullUrl('preview') : null,
            thumbnail_url: $media->hasGeneratedConversion('thumbnail') ? $media->getFullUrl('thumbnail') : null,
            order_column: $media->order_column,
            custom_properties: $media->custom_properties ?? [],
            created_at: $media->created_at->toISOString(),
            updated_at: $media->updated_at->toISOString()
        );
    }
}
