<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'comment_id', 
        'vote_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function scopeUpVotes($query)
    {
        return $query->where('vote_type', 'up');
    }

    public function scopeDownVotes($query)
    {
        return $query->where('vote_type', 'down');
    }

    public function isUpVote(): bool
    {
        return $this->vote_type === 'up';
    }

    public function isDownVote(): bool
    {
        return $this->vote_type === 'down';
    }
}
