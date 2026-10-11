<?php

namespace Database\Factories;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Idea> */
class IdeaFactory extends Factory
{
    protected $model = Idea::class;

    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => 'Idea '.fake()->unique()->words(4, true),
            'description' => fake()->sentence(12),
            'status' => IdeaStatus::New,
            'votes_count' => 0,
        ];
    }

    public function status(IdeaStatus $status, ?int $position = null): static
    {
        return $this->state(['status' => $status, 'position' => $position]);
    }
}
