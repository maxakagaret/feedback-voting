<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ballot extends Model
{
    protected $guarded = [];

    public function choices(): BelongsToMany
    {
        return $this->belongsToMany(VotingOption::class, 'ballot_choices');
    }
}
