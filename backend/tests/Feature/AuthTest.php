<?php

use App\Models\User;

it('issues a token for valid credentials and uses it', function () {
    User::factory()->create(['email' => 'kim@example.test']);

    $token = $this->postJson('/api/login', ['email' => 'kim@example.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('user.role', 'member')
        ->json('token');

    $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'kim@example.test');
});

it('rejects wrong credentials without telling which part was wrong', function () {
    User::factory()->create(['email' => 'kim@example.test']);

    $this->postJson('/api/login', ['email' => 'kim@example.test', 'password' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
});

it('rate limits login attempts', function () {
    foreach (range(1, 10) as $i) {
        $this->postJson('/api/login', ['email' => 'x@example.test', 'password' => 'bad']);
    }

    $this->postJson('/api/login', ['email' => 'x@example.test', 'password' => 'bad'])->assertStatus(429);
});

it('requires authentication', function () {
    $this->getJson('/api/ideas')->assertUnauthorized();
});

it('keeps administration for admins', function () {
    actingAsMember();

    $this->postJson('/api/votings', [])->assertForbidden();
    $this->putJson('/api/roadmap/order', [])->assertForbidden();
});

it('revokes the token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)->postJson('/api/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
