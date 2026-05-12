<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    /**
     * Get the user that owns the poll.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the options for the poll.
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class);
    }

    /**
     * Get the votes for the poll.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    protected $fillable = [
    'user_id', 'title', 'question', 'secret_token',
    'is_draft', 'allow_multiple_choices', 'allow_vote_change',
    'results_public', 'duration', 'started_at', 'ends_at',
    ];

    protected $casts = [
    'is_draft'               => 'boolean',
    'allow_multiple_choices' => 'boolean',
    'allow_vote_change'      => 'boolean',
    'results_public'         => 'boolean',
    'started_at'             => 'datetime',
    'ends_at'                => 'datetime',
    ];
}
