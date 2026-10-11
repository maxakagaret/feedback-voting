<?php

namespace App\Enums;

/**
 * Lifecycle of a product idea. Not every jump makes sense — an idea is not
 * "done" before it was planned — so transitions are whitelisted.
 */
enum IdeaStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Declined = 'declined';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::New => [self::UnderReview, self::Planned, self::Declined],
            self::UnderReview => [self::Planned, self::Declined, self::New],
            self::Planned => [self::InProgress, self::UnderReview, self::Declined],
            self::InProgress => [self::Done, self::Planned],
            self::Done => [self::InProgress],
            self::Declined => [self::UnderReview],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Statuses shown as roadmap columns. */
    public static function roadmap(): array
    {
        return [self::Planned, self::InProgress, self::Done];
    }

    /** Voting on finished or rejected ideas changes nothing, so it is closed. */
    public function acceptsVotes(): bool
    {
        return ! in_array($this, [self::Done, self::Declined], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::UnderReview => 'Under review',
            self::Planned => 'Planned',
            self::InProgress => 'In progress',
            self::Done => 'Done',
            self::Declined => 'Declined',
        };
    }
}
