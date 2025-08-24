<?php

namespace App\DataTransferObjects;

use App\DataTransferObjects\Tag\TagData;
use App\Enums\CommentConfigEnum;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Attributes\WithCast;

class EventData extends Data
{
    public function __construct(
        #[FromRouteParameter('id')]
        public readonly ?int $id,
        #[Validation\Rule(['required', 'array:en,zh-TW,zh-CN'])]
        public readonly array $name,
        #[Validation\Rule(['required', 'array:en,zh-TW,zh-CN'])]
        public readonly array $short_summary,
        #[Validation\Rule(['required', 'array:en,zh-TW,zh-CN'])]
        public readonly array $description,
        #[Validation\Rule(['nullable', 'array:en,zh-TW,zh-CN'])]
        public readonly ?array $slug,
        #[Validation\Rule(['nullable', 'array:en,zh-TW,zh-CN'])]
        public readonly ?array $cancellation_policy,
        #[Validation\Rule(['nullable', 'array:en,zh-TW,zh-CN'])]
        public readonly ?array $meta_title,
        #[Validation\Rule(['nullable', 'array:en,zh-TW,zh-CN'])]
        public readonly ?array $meta_description,
        #[Validation\Rule(['nullable', 'array:en,zh-TW,zh-CN'])]
        public readonly ?array $meta_keywords,
        #[Validation\Rule(['nullable', 'string'])]
        public readonly ?string $website,
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $social_links,
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $social_media_links,
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $youtube_video_id,
        #[Validation\Rule(['nullable', 'string'])]
        public readonly ?string $contact_email,
        #[Validation\Rule(['nullable', 'string'])]
        public readonly ?string $website_url,
        #[Validation\Rule(['nullable', 'string'])]
        public readonly ?string $contact_phone,
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $additional_info,
        #[Validation\Rule(['nullable', 'string'])]
        public readonly ?string $seating_chart,
        #[Validation\Rule(['nullable', 'date'])]
        public readonly ?string $published_at,
        #[Validation\Rule(['nullable', 'boolean'])]
        public readonly ?bool $is_featured,
        #[Validation\Rule(['nullable', 'integer', 'exists:categories,id'])]
        public readonly ?int $category_id,
        #[Validation\Rule(['nullable', 'integer', 'exists:organizers,id'])]
        public readonly ?int $organizer_id,
        #[Validation\Rule(['nullable', 'integer', 'exists:users,id'])]
        public readonly ?int $created_by,
        #[Validation\Rule(['nullable', 'integer', 'exists:users,id'])]
        public readonly ?int $updated_by,

        /** @var TagData[]|Optional */
        #[Validation\Rule(['nullable', 'array'])]
        public readonly array|Optional $tag_ids,
        #[Validation\Rule(['nullable', 'string'])]
        public readonly ?string $event_status,
        #[Validation\Rule(['nullable', 'string'])]
        public readonly ?string $visibility,
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $visible_to_membership_levels,
        #[Validation\Rule(['nullable', 'string', 'in:purchase_ticket,show_member_qr'])]
        public readonly ?string $action_type,

        // For handling image uploads during create/update
        #[Validation\Rule(['nullable', 'image', 'max:2048'])]
        public readonly ?UploadedFile $main_image,
        #[Validation\Rule(['nullable', 'image', 'max:1024'])]
        public readonly ?UploadedFile $thumbnail_image,
        #[Validation\Rule(['nullable', 'image', 'max:1024'])]
        public readonly ?UploadedFile $uploaded_landscape_poster,
        #[Validation\Rule(['nullable', 'image', 'max:1024'])]
        public readonly ?UploadedFile $uploaded_portrait_poster,
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $gallery_images, // Array of UploadedFile

        // For handling removal of existing gallery items during update
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $removed_gallery_ids = null, // Array of media IDs to remove

        #[Validation\Rule(['nullable', 'string', new Enum(CommentConfigEnum::class)])]
        #[WithCast(EnumCast::class, type: CommentConfigEnum::class)]
        public readonly ?CommentConfigEnum $comment_config,
        
        #[Validation\Rule(['nullable', 'boolean'])]
        public readonly ?bool $comments_enabled,
        
        #[Validation\Rule(['nullable', 'boolean'])]
        public readonly ?bool $comments_require_approval,
    ) {}
}
