<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

function actingAsMember(): User
{
    return Sanctum::actingAs(User::factory()->create());
}

function actingAsAdmin(): User
{
    return Sanctum::actingAs(User::factory()->admin()->create());
}
