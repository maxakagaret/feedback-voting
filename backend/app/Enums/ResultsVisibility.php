<?php

namespace App\Enums;

/**
 * When voters may see the tally. Showing live results before someone votes
 * nudges them toward the leading option (bandwagon effect), so the default is
 * "after you voted".
 */
enum ResultsVisibility: string
{
    case Always = 'always';
    case AfterVote = 'after_vote';
    case AfterClose = 'after_close';
}
