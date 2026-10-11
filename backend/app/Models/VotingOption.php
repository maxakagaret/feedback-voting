<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VotingOption extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }
}
