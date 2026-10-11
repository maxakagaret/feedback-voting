<?php

use App\Enums\ResultsVisibility;
use App\Models\Ballot;
use App\Models\Idea;
use App\Models\User;
use App\Models\Voting;
use App\Services\BallotService;

function optionIds(Voting $voting): array
{
    return $voting->options()->pluck('id')->all();
}

it('lets an admin create a voting from ideas and free options', function () {
    actingAsAdmin();
    $idea = Idea::factory()->create(['title' => 'Recurring appointments']);

    $this->postJson('/api/votings', [
        'title' => 'Next big feature',
        'ends_at' => now()->addWeek()->toIso8601String(),
        'max_choices' => 2,
        'results_visibility' => 'after_vote',
        'options' => [['idea_id' => $idea->id], ['label' => 'Something else']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.state', 'active')
        ->assertJsonPath('data.options.0.label', 'Recurring appointments')
        ->assertJsonPath('data.options.1.label', 'Something else');
});

it('validates votings', function () {
    actingAsAdmin();

    $this->postJson('/api/votings', [
        'title' => 'Bad',
        'ends_at' => now()->subDay()->toIso8601String(),
        'max_choices' => 3,
        'results_visibility' => 'sometimes',
        'options' => [['label' => 'Only one']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['ends_at', 'max_choices', 'results_visibility', 'options']);
});

it('records one ballot per voter and lets them change it', function () {
    $user = actingAsMember();
    $voting = Voting::factory()->withOptions()->create();
    [$first, $second] = optionIds($voting);

    $this->putJson("/api/votings/{$voting->id}/ballot", ['option_ids' => [$first]])->assertOk()->assertJsonPath('data.my_choices', [$first]);
    $this->putJson("/api/votings/{$voting->id}/ballot", ['option_ids' => [$second]])->assertOk()->assertJsonPath('data.my_choices', [$second]);

    expect(Ballot::where('voting_id', $voting->id)->where('user_id', $user->id)->count())->toBe(1);
});

it('enforces the maximum number of choices', function () {
    actingAsMember();
    $voting = Voting::factory()->withOptions()->create(['max_choices' => 2]);

    $this->putJson("/api/votings/{$voting->id}/ballot", ['option_ids' => optionIds($voting)])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Choose between 1 and 2 option(s).');
});

it('rejects options of another voting', function () {
    actingAsMember();
    $voting = Voting::factory()->withOptions()->create();
    $other = Voting::factory()->withOptions(['X', 'Y'])->create();

    $this->putJson("/api/votings/{$voting->id}/ballot", ['option_ids' => [optionIds($other)[0]]])->assertUnprocessable();
});

it('does not accept ballots outside the voting window', function () {
    actingAsMember();
    $scheduled = Voting::factory()->withOptions()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addWeek()]);
    $closed = Voting::factory()->withOptions()->create(['closed_at' => now()]);

    $this->putJson("/api/votings/{$scheduled->id}/ballot", ['option_ids' => [optionIds($scheduled)[0]]])
        ->assertUnprocessable()->assertJsonPath('message', 'This voting is scheduled.');
    $this->putJson("/api/votings/{$closed->id}/ballot", ['option_ids' => [optionIds($closed)[0]]])
        ->assertUnprocessable()->assertJsonPath('message', 'This voting is closed.');
});

it('hides results until the member has voted (anti-bandwagon)', function () {
    actingAsMember();
    $voting = Voting::factory()->withOptions()->create(['results_visibility' => ResultsVisibility::AfterVote]);

    $this->getJson("/api/votings/{$voting->id}")->assertJsonPath('data.results', null);

    $this->putJson("/api/votings/{$voting->id}/ballot", ['option_ids' => [optionIds($voting)[0]]])
        ->assertJsonPath('data.results.ballots', 1);
});

it('keeps results secret until close when configured, except for admins', function () {
    $voting = Voting::factory()->withOptions()->create(['results_visibility' => ResultsVisibility::AfterClose]);
    $member = User::factory()->create();
    app(BallotService::class)->cast($voting, $member, [optionIds($voting)[0]]);

    \Laravel\Sanctum\Sanctum::actingAs($member);
    $this->getJson("/api/votings/{$voting->id}")->assertJsonPath('data.results', null);

    actingAsAdmin();
    $this->getJson("/api/votings/{$voting->id}")->assertJsonPath('data.results.ballots', 1);
});

it('reports shares of voters and marks the leader', function () {
    $voting = Voting::factory()->withOptions(['A', 'B', 'C'])->create(['max_choices' => 2]);
    [$a, $b, $c] = optionIds($voting);
    $service = app(BallotService::class);
    $service->cast($voting, User::factory()->create(), [$a, $b]);
    $service->cast($voting, User::factory()->create(), [$a]);
    $service->cast($voting, User::factory()->create(), [$c]);

    actingAsAdmin();
    $results = $this->getJson("/api/votings/{$voting->id}")->json('data.results');

    expect($results['ballots'])->toBe(3)
        ->and($results['options'][0])->toMatchArray(['label' => 'A', 'votes' => 2, 'share' => 0.6667, 'leading' => true])
        ->and($results['options'][1]['leading'])->toBeFalse();
});

it('lets a member withdraw their ballot', function () {
    actingAsMember();
    $voting = Voting::factory()->withOptions()->create();
    $this->putJson("/api/votings/{$voting->id}/ballot", ['option_ids' => [optionIds($voting)[0]]]);

    $this->deleteJson("/api/votings/{$voting->id}/ballot")->assertOk()->assertJsonPath('data.my_choices', []);
});

it('closes a voting early and lists votings by state', function () {
    actingAsAdmin();
    $voting = Voting::factory()->withOptions()->create();
    Voting::factory()->withOptions()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addWeek()]);

    $this->postJson("/api/votings/{$voting->id}/close")->assertOk()->assertJsonPath('data.state', 'closed');
    $this->postJson("/api/votings/{$voting->id}/close")->assertStatus(409);

    $this->getJson('/api/votings?state=closed')->assertJsonCount(1, 'data');
    $this->getJson('/api/votings?state=scheduled')->assertJsonCount(1, 'data');
    $this->getJson('/api/votings?state=active')->assertJsonCount(0, 'data');
});

it('seeds a consistent demo community', function () {
    $this->seed();

    $ideas = Idea::all();
    foreach ($ideas as $idea) {
        expect($idea->votes_count)->toBe(\DB::table('idea_votes')->where('idea_id', $idea->id)->count());
    }

    expect(Voting::count())->toBe(3)
        ->and(Voting::all()->map->state()->sort()->values()->all())->toBe(['active', 'closed', 'scheduled'])
        ->and(Idea::where('status', 'done')->count())->toBe(2);
});
