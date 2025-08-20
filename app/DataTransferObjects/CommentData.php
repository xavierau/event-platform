<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Data;

class CommentData extends Data
{
    public function __construct(
        #[FromRouteParameter('id')]
        public readonly ?int $id,
        
        #[Validation\Required]
        #[Validation\Integer]
        #[Validation\Exists('users', 'id')]
        public readonly int $user_id,
        
        #[Validation\Required]
        #[Validation\String]
        public readonly string $commentable_type,
        
        #[Validation\Required]
        #[Validation\Integer]
        public readonly int $commentable_id,
        
        #[Validation\Required]
        #[Validation\String]
        #[Validation\Max(10000)]
        public readonly string $content,
        
        #[Validation\In(['plain', 'rich'])]
        public readonly string $content_type = 'plain',
        
        #[Validation\In(['pending', 'approved', 'rejected', 'flagged'])]
        public readonly string $status = 'pending',
        
        #[Validation\Nullable]
        #[Validation\Integer]
        #[Validation\Exists('comments', 'id')]
        public readonly ?int $parent_id = null,
        
        #[Validation\Boolean]
        public readonly bool $votes_enabled = false,
        
        #[Validation\Integer]
        #[Validation\Min(0)]
        public readonly int $votes_up_count = 0,
        
        #[Validation\Integer]
        #[Validation\Min(0)]
        public readonly int $votes_down_count = 0,
    ) {}

    public static function rules(): array
    {
        return [
            'commentable_type' => ['required', 'string', 'in:App\Models\Event,App\Models\Organizer'],
            'commentable_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $commentableType = request('commentable_type');
                    if (!$commentableType) {
                        return;
                    }
                    
                    $exists = $commentableType::find($value);
                    if (!$exists) {
                        $fail("The selected commentable does not exist.");
                    }
                },
            ],
        ];
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isFlagged(): bool
    {
        return $this->status === 'flagged';
    }
}