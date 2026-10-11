<?php

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use App\Services\IdeaService;
use Illuminate\Support\Facades\DB;

it('lets members submit ideas', function () {
    $user = actingAsMember();

    $this->postJson('/api/ideas', ['title' => 'Dark mode for reports', 'description' => 'Easier on the eyes'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.author', $user->name);
});

it('validates new ideas', function () {
    actingAsMember();

    $this->postJson('/api/ideas', ['title' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('title');
});

it('toggles an upvote and keeps the counter in sync', function () {
    actingAsMember();
    $idea = Idea::factory()->create();

    $this->postJson("/api/ideas/{$idea->id}/vote")->assertOk()->assertJson(['data' => ['voted' => true, 'votes_count' => 1]]);
    $this->getJson("/api/ideas/{$idea->id}")->assertJsonPath('data.voted_by_me', true);
    $this->postJson("/api/ideas/{$idea->id}/vote")->assertOk()->assertJson(['data' => ['voted' => false, 'votes_count' => 0]]);

    expect(DB::table('idea_votes')->count())->toBe(0);
});

it('counts one vote per person across many voters', function () {
    $idea = Idea::factory()->create();
    $service = app(IdeaService::class);

    User::factory()->count(5)->create()->each(fn ($u) => $service->toggleVote($idea, $u));

    expect($idea->refresh()->votes_count)->toBe(5)
        ->and(DB::table('idea_votes')->where('idea_id', $idea->id)->count())->toBe(5);
});

it('does not accept votes for finished ideas', function () {
    actingAsMember();
    $idea = Idea::factory()->status(IdeaStatus::Done)->create();

    $this->postJson("/api/ideas/{$idea->id}/vote")->assertStatus(409);
});

it('sorts the board by votes and filters by status', function () {
    actingAsMember();
    Idea::factory()->create(['title' => 'Less popular idea', 'votes_count' => 2]);
    Idea::factory()->create(['title' => 'Most popular idea', 'votes_count' => 9]);
    Idea::factory()->status(IdeaStatus::Planned, 1)->create(['title' => 'Planned idea here']);

    $this->getJson('/api/ideas')->assertJsonPath('data.0.title', 'Most popular idea');
    $this->getJson('/api/ideas?status=planned')->assertJsonCount(1, 'data');
});

it('suggests similar ideas before a duplicate is submitted', function () {
    actingAsMember();
    Idea::factory()->create(['title' => 'Export reports to Excel']);
    Idea::factory()->create(['title' => 'Dark mode for the whole app']);

    $this->getJson('/api/ideas/similar?title='.urlencode('Excel export of the reports'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Export reports to Excel');
});

it('moves ideas through the lifecycle with history', function () {
    $admin = actingAsAdmin();
    $idea = Idea::factory()->create();

    $this->patchJson("/api/ideas/{$idea->id}/status", ['status' => 'planned', 'comment' => 'Next quarter'])
        ->assertOk()
        ->assertJsonPath('data.status', 'planned');

    $this->getJson("/api/ideas/{$idea->id}")
        ->assertJsonPath('data.history.0.from', 'new')
        ->assertJsonPath('data.history.0.to', 'planned')
        ->assertJsonPath('data.history.0.by', $admin->name)
        ->assertJsonPath('data.history.0.comment', 'Next quarter');
});

it('rejects transitions that skip steps', function () {
    actingAsAdmin();
    $idea = Idea::factory()->create();

    $this->patchJson("/api/ideas/{$idea->id}/status", ['status' => 'done'])->assertUnprocessable();
    expect($idea->refresh()->status)->toBe(IdeaStatus::New);
});

it('appends ideas to the end of a roadmap column and reorders it', function () {
    $admin = actingAsAdmin();
    $service = app(IdeaService::class);
    [$a, $b, $c] = Idea::factory()->count(3)->create()->all();
    foreach ([$a, $b, $c] as $idea) {
        $service->changeStatus($idea, IdeaStatus::Planned, $admin);
    }

    $this->getJson('/api/roadmap')->assertJsonPath('data.0.ideas.*.id', [$a->id, $b->id, $c->id]);

    $this->putJson('/api/roadmap/order', ['status' => 'planned', 'ids' => [$c->id, $a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.0.ideas.*.id', [$c->id, $a->id, $b->id]);
});

it('rejects a reorder based on a stale roadmap', function () {
    $admin = actingAsAdmin();
    $idea = Idea::factory()->create();
    app(IdeaService::class)->changeStatus($idea, IdeaStatus::Planned, $admin);
    $other = Idea::factory()->create();
    app(IdeaService::class)->changeStatus($other, IdeaStatus::Planned, $admin);

    $this->putJson('/api/roadmap/order', ['status' => 'planned', 'ids' => [$idea->id]])->assertStatus(409);
});
