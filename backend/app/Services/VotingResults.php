<?php

namespace App\Services;

use App\Enums\ResultsVisibility;
use App\Models\User;
use App\Models\Voting;
use Illuminate\Support\Facades\DB;

class VotingResults
{
    /** Admins always see the tally; voters per the voting's visibility rule. */
    public function visibleTo(Voting $voting, User $user): bool
    {
        if ($user->isAdmin() || $voting->state() === 'closed') {
            return true;
        }

        return match ($voting->results_visibility) {
            ResultsVisibility::Always => true,
            ResultsVisibility::AfterVote => $voting->ballots()->where('user_id', $user->id)->exists(),
            ResultsVisibility::AfterClose => false,
        };
    }

    /**
     * Percentages are of ballots, not of choices: with multiple choices per
     * ballot "60%" should read "60% of voters picked this".
     *
     * @return array{ballots: int, options: list<array{id: int, label: string, votes: int, share: float, leading: bool}>}
     */
    public function tally(Voting $voting): array
    {
        $ballots = $voting->ballots()->count();

        $votes = DB::table('ballot_choices')
            ->join('ballots', 'ballots.id', '=', 'ballot_choices.ballot_id')
            ->where('ballots.voting_id', $voting->id)
            ->select('ballot_choices.voting_option_id', DB::raw('count(*) as total'))
            ->groupBy('ballot_choices.voting_option_id')
            ->pluck('total', 'voting_option_id');

        $max = $votes->max() ?? 0;

        return [
            'ballots' => $ballots,
            'options' => $voting->options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'votes' => (int) ($votes[$option->id] ?? 0),
                'share' => $ballots ? round(($votes[$option->id] ?? 0) / $ballots, 4) : 0.0,
                'leading' => $max > 0 && (int) ($votes[$option->id] ?? 0) === (int) $max,
            ])->all(),
        ];
    }
}
