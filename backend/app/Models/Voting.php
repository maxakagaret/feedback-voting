<?php

namespace App\Models;

use App\Enums\ResultsVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'max_choices' => 'integer',
            'results_visibility' => ResultsVisibility::class,
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(VotingOption::class)->orderBy('position');
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class);
    }

    /** scheduled | active | closed — derived from dates, never stored, so it cannot go stale. */
    public function state(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            $this->closed_at !== null || $now->gte($this->ends_at) => 'closed',
            $now->lt($this->starts_at) => 'scheduled',
            default => 'active',
        };
    }

    public function isOpen(): bool
    {
        return $this->state() === 'active';
    }

    public function scopeWithState(Builder $query, string $state): Builder
    {
        $now = CarbonImmutable::now();

        return match ($state) {
            'active' => $query->whereNull('closed_at')->where('starts_at', '<=', $now)->where('ends_at', '>', $now),
            'scheduled' => $query->whereNull('closed_at')->where('starts_at', '>', $now),
            'closed' => $query->where(fn ($q) => $q->whereNotNull('closed_at')->orWhere('ends_at', '<=', $now)),
            default => $query,
        };
    }
}
