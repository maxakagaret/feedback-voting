<?php

use App\Enums\IdeaStatus;

it('only allows meaningful status transitions', function (IdeaStatus $from, IdeaStatus $to, bool $allowed) {
    expect($from->canMoveTo($to))->toBe($allowed);
})->with([
    'new → planned' => [IdeaStatus::New, IdeaStatus::Planned, true],
    'planned → in progress' => [IdeaStatus::Planned, IdeaStatus::InProgress, true],
    'in progress → done' => [IdeaStatus::InProgress, IdeaStatus::Done, true],
    'new → done (skips the work)' => [IdeaStatus::New, IdeaStatus::Done, false],
    'declined → in progress' => [IdeaStatus::Declined, IdeaStatus::InProgress, false],
    'declined can be reconsidered' => [IdeaStatus::Declined, IdeaStatus::UnderReview, true],
    'done can be reopened' => [IdeaStatus::Done, IdeaStatus::InProgress, true],
]);

it('closes voting on finished and rejected ideas', function () {
    expect(IdeaStatus::Done->acceptsVotes())->toBeFalse()
        ->and(IdeaStatus::Declined->acceptsVotes())->toBeFalse()
        ->and(IdeaStatus::Planned->acceptsVotes())->toBeTrue();
});
