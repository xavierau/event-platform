<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Data;

class CommentVoteData extends Data
{
    public function __construct(
        #[FromRouteParameter('id')]
        public readonly ?int $id,
        
        #[Validation\Required]
        #[Validation\Integer]
        #[Validation\Exists('users', 'id')]
        public readonly int $user_id,
        
        #[Validation\Required]
        #[Validation\Integer]
        #[Validation\Exists('comments', 'id')]
        public readonly int $comment_id,
        
        #[Validation\Required]
        #[Validation\In(['up', 'down'])]
        public readonly string $vote_type,
    ) {}

    public function isUpVote(): bool
    {
        return $this->vote_type === 'up';
    }

    public function isDownVote(): bool
    {
        return $this->vote_type === 'down';
    }

    public function toggleVote(): self
    {
        return new self(
            id: $this->id,
            user_id: $this->user_id,
            comment_id: $this->comment_id,
            vote_type: $this->isUpVote() ? 'down' : 'up'
        );
    }
}