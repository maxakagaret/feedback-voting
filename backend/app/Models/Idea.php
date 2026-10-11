<?php

namespace App\Models;

use App\Enums\IdeaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Idea extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => IdeaStatus::class, 'votes_count' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'idea_votes');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(IdeaStatusChange::class)->orderBy('id');
    }
}
