<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\User;
use App\Models\Voting;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * One ballot per voter per voting (unique key), with 1..max_choices options.
 * Casting again replaces the previous choices — people may change their mind
 * while the voting is open — and is idempotent: the same choice twice is a no-op.
 */
class BallotService
{
    /** @param list<int> $optionIds */
    public function cast(Voting $voting, User $user, array $optionIds): Ballot
    {
        if (! $voting->isOpen()) {
            throw new DomainException("This voting is {$voting->state()}.");
        }

        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));

        if (count($optionIds) < 1 || count($optionIds) > $voting->max_choices) {
            throw new DomainException("Choose between 1 and {$voting->max_choices} option(s).");
        }

        $valid = $voting->options()->whereIn('id', $optionIds)->count();
        if ($valid !== count($optionIds)) {
            throw new DomainException('Some options do not belong to this voting.');
        }

        return DB::transaction(function () use ($voting, $user, $optionIds) {
            try {
                $ballot = Ballot::firstOrCreate(['voting_id' => $voting->id, 'user_id' => $user->id]);
            } catch (UniqueConstraintViolationException) {
                // Two tabs submitted at once: the other request created the ballot.
                $ballot = Ballot::where('voting_id', $voting->id)->where('user_id', $user->id)->firstOrFail();
            }

            $ballot->choices()->sync($optionIds);
            $ballot->touch();

            return $ballot;
        });
    }

    public function withdraw(Voting $voting, User $user): void
    {
        if (! $voting->isOpen()) {
            throw new DomainException("This voting is {$voting->state()}.");
        }

        Ballot::where('voting_id', $voting->id)->where('user_id', $user->id)->delete();
    }
}
