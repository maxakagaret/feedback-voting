<?php

namespace Database\Factories;

use App\Enums\ResultsVisibility;
use App\Models\User;
use App\Models\Voting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Voting> */
class VotingFactory extends Factory
{
    protected $model = Voting::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory()->admin(),
            'title' => 'What should we build next?',
            'description' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'max_choices' => 1,
            'results_visibility' => ResultsVisibility::AfterVote,
        ];
    }

    /** @param list<string> $labels */
    public function withOptions(array $labels = ['Dark mode', 'Calendar export', 'Mobile app']): static
    {
        return $this->afterCreating(function (Voting $voting) use ($labels) {
            foreach ($labels as $i => $label) {
                $voting->options()->create(['label' => $label, 'position' => $i + 1]);
            }
        });
    }
}
