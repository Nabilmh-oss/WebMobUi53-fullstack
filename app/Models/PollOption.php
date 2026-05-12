<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{
    /**
     * Get the poll that owns the option.
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * Get the votes for this option.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    protected $fillable = ['poll_id', 'label'];
}
