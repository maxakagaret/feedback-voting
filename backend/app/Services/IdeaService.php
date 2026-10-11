<?php

namespace App\Services;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class IdeaService
{
    /**
     * Toggles the user's upvote. The (idea, user) primary key is the real guard
     * against double votes; the denormalised counter is changed in the same
     * transaction, so it can never drift from idea_votes — even with two clicks
     * racing each other.
     *
     * @return array{voted: bool, votes_count: int}
     */
    public function toggleVote(Idea $idea, User $user): array
    {
        if (! $idea->status->acceptsVotes()) {
            throw new DomainException("Voting is closed for ideas that are {$idea->status->label()}.");
        }

        return DB::transaction(function () use ($idea, $user) {
            $locked = Idea::whereKey($idea->id)->lockForUpdate()->firstOrFail();

            $removed = DB::table('idea_votes')->where('idea_id', $idea->id)->where('user_id', $user->id)->delete();

            if ($removed) {
                $locked->decrement('votes_count');
                $voted = false;
            } else {
                try {
                    DB::table('idea_votes')->insert(['idea_id' => $idea->id, 'user_id' => $user->id, 'created_at' => CarbonImmutable::now()]);
                    $locked->increment('votes_count');
                } catch (UniqueConstraintViolationException) {
                    // A concurrent request already recorded this vote — the end state is the same.
                }
                $voted = true;
            }

            $idea->votes_count = $locked->refresh()->votes_count;

            return ['voted' => $voted, 'votes_count' => $idea->votes_count];
        });
    }

    /** Moves an idea through its lifecycle and records who did it and why. */
    public function changeStatus(Idea $idea, IdeaStatus $next, User $admin, ?string $comment = null): Idea
    {
        if (! $idea->status->canMoveTo($next)) {
            throw new DomainException("An idea cannot go from {$idea->status->label()} to {$next->label()}.");
        }

        return DB::transaction(function () use ($idea, $next, $admin, $comment) {
            $idea->statusChanges()->create([
                'changed_by' => $admin->id,
                'from_status' => $idea->status,
                'to_status' => $next,
                'comment' => $comment,
                'created_at' => CarbonImmutable::now(),
            ]);

            // Entering a roadmap column appends to its end; leaving the roadmap clears the slot.
            $position = in_array($next, IdeaStatus::roadmap(), true)
                ? (int) Idea::where('status', $next)->max('position') + 1
                : null;

            $idea->update(['status' => $next, 'position' => $position]);

            return $idea;
        });
    }

    /**
     * Rewrites the order of one roadmap column. The ids must be exactly the ideas
     * currently in that column — a stale client view is rejected instead of
     * silently producing gaps or duplicates.
     *
     * @param  list<int>  $ids
     */
    public function reorder(IdeaStatus $column, array $ids): void
    {
        DB::transaction(function () use ($column, $ids) {
            $current = Idea::where('status', $column)->lockForUpdate()->pluck('id')->sort()->values()->all();
            $given = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $given) {
                throw new DomainException('The roadmap changed meanwhile — reload and try again.');
            }

            foreach (array_values($ids) as $position => $id) {
                Idea::whereKey($id)->update(['position' => $position + 1]);
            }
        });
    }
}
