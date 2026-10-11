<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResultsVisibility;
use App\Http\Controllers\Controller;
use App\Models\Ballot;
use App\Models\Idea;
use App\Models\User;
use App\Models\Voting;
use App\Services\BallotService;
use App\Services\VotingResults;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VotingController extends Controller
{
    public function __construct(private readonly VotingResults $results) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['state' => ['nullable', Rule::in(['active', 'scheduled', 'closed'])]]);
        $user = $request->user();

        $votings = Voting::query()
            ->when($data['state'] ?? null, fn ($q, $s) => $q->withState($s))
            ->withCount('ballots')
            ->orderByDesc('starts_at')
            ->get();

        $mine = Ballot::where('user_id', $user->id)->whereIn('voting_id', $votings->pluck('id'))->pluck('voting_id')->flip();

        return response()->json(['data' => $votings->map(fn (Voting $v) => [
            ...$this->summary($v),
            'ballots_count' => $v->ballots_count,
            'voted' => $mine->has($v->id),
        ])]);
    }

    public function show(Request $request, Voting $voting): JsonResponse
    {
        return response()->json(['data' => $this->detail($voting, $request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['required', 'date', 'after:now', ...($request->filled('starts_at') ? ['after:starts_at'] : [])],
            'max_choices' => ['required', 'integer', 'min:1', 'lte:'.count($request->input('options', []))],
            'results_visibility' => ['required', Rule::enum(ResultsVisibility::class)],
            'options' => ['required', 'array', 'min:2', 'max:12'],
            'options.*.label' => ['required_without:options.*.idea_id', 'nullable', 'string', 'max:140'],
            'options.*.idea_id' => ['nullable', 'integer', 'exists:ideas,id'],
        ]);

        $voting = DB::transaction(function () use ($data, $request) {
            $voting = Voting::create([
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'] ?? CarbonImmutable::now(),
                'ends_at' => $data['ends_at'],
                'max_choices' => $data['max_choices'],
                'results_visibility' => $data['results_visibility'],
            ]);

            // validate() assembles wildcard arrays in rule order, not input order — restore it.
            $options = $data['options'];
            ksort($options);

            foreach (array_values($options) as $position => $option) {
                $voting->options()->create([
                    'idea_id' => $option['idea_id'] ?? null,
                    // An option built from an idea takes the idea's title unless relabelled.
                    'label' => $option['label'] ?? Idea::find($option['idea_id'])->title,
                    'position' => $position + 1,
                ]);
            }

            return $voting;
        });

        return response()->json(['data' => $this->detail($voting, $request->user())], 201);
    }

    public function close(Request $request, Voting $voting): JsonResponse
    {
        if ($voting->state() === 'closed') {
            return response()->json(['message' => 'This voting is already closed.'], 409);
        }

        $voting->update(['closed_at' => CarbonImmutable::now()]);

        return response()->json(['data' => $this->detail($voting, $request->user())]);
    }

    public function cast(Request $request, Voting $voting, BallotService $ballots): JsonResponse
    {
        $data = $request->validate([
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['integer'],
        ]);

        try {
            $ballots->cast($voting, $request->user(), $data['option_ids']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->detail($voting, $request->user())]);
    }

    public function withdraw(Request $request, Voting $voting, BallotService $ballots): JsonResponse
    {
        try {
            $ballots->withdraw($voting, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->detail($voting, $request->user())]);
    }

    private function summary(Voting $v): array
    {
        return [
            'id' => $v->id,
            'title' => $v->title,
            'description' => $v->description,
            'state' => $v->state(),
            'starts_at' => $v->starts_at->toIso8601String(),
            'ends_at' => $v->ends_at->toIso8601String(),
            'closed_at' => $v->closed_at?->toIso8601String(),
            'max_choices' => $v->max_choices,
            'results_visibility' => $v->results_visibility->value,
        ];
    }

    private function detail(Voting $voting, User $user): array
    {
        $voting->load('options');
        $myChoices = Ballot::where('voting_id', $voting->id)->where('user_id', $user->id)->first()
            ?->choices()->pluck('voting_options.id')->all();

        return [
            ...$this->summary($voting),
            'options' => $voting->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'idea_id' => $o->idea_id]),
            'my_choices' => $myChoices ?? [],
            'results' => $this->results->visibleTo($voting, $user) ? $this->results->tally($voting) : null,
        ];
    }
}
