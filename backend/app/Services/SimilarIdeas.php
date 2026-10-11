<?php

namespace App\Services;

use App\Models\Idea;
use Illuminate\Support\Collection;

/**
 * "Did you mean…?" before a new idea is submitted. Duplicate ideas split the
 * votes and hide real demand, so the board nudges people to upvote the existing
 * one. Plain word overlap (Jaccard) on normalised titles — good enough for short
 * titles and fully explainable; a search engine would replace it at scale.
 */
class SimilarIdeas
{
    private const STOP_WORDS = ['a', 'an', 'the', 'to', 'of', 'for', 'in', 'on', 'and', 'or', 'with', 'be', 'is', 'add', 'please', 'should', 'would', 'can', 'it', 'we'];

    /** @return Collection<int, array{idea: Idea, score: float}> */
    public function find(string $title, float $threshold = 0.5, int $limit = 5): Collection
    {
        $words = $this->words($title);
        if ($words === []) {
            return collect();
        }

        return Idea::query()
            ->select(['id', 'title', 'status', 'votes_count'])
            ->get()
            ->map(fn (Idea $idea) => ['idea' => $idea, 'score' => $this->jaccard($words, $this->words($idea->title))])
            ->filter(fn (array $m) => $m['score'] >= $threshold)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $text = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text));
        $words = array_filter(preg_split('/\s+/u', $text), fn ($w) => mb_strlen($w) > 1 && ! in_array($w, self::STOP_WORDS, true));

        // Crude plural folding: "exports" ≈ "export", "reminders" ≈ "reminder".
        return array_values(array_unique(array_map(fn ($w) => mb_strlen($w) > 3 ? preg_replace('/s$/u', '', $w) : $w, $words)));
    }

    private function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return round(count(array_intersect($a, $b)) / count(array_unique([...$a, ...$b])), 3);
    }
}
