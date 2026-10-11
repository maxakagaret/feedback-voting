<?php

namespace App\Http\Controllers\Api;

use App\Enums\IdeaStatus;
use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Services\IdeaService;
use App\Services\SimilarIdeas;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IdeaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(IdeaStatus::class)],
            'sort' => ['nullable', Rule::in(['top', 'new'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $ideas = Idea::with('author:id,name')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when(($data['sort'] ?? 'top') === 'top', fn ($q) => $q->orderByDesc('votes_count'))
            ->latest('id')
            ->paginate(20);

        $mine = $this->votedByUser($request, $ideas->pluck('id')->all());

        return response()->json([
            'data' => $ideas->getCollection()->map(fn (Idea $i) => $this->present($i, isset($mine[$i->id]))),
            'meta' => ['total' => $ideas->total(), 'current_page' => $ideas->currentPage(), 'last_page' => $ideas->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:8', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $idea = Idea::create($data + ['author_id' => $request->user()->id, 'status' => IdeaStatus::New]);

        return response()->json(['data' => $this->present($idea->load('author:id,name'), false)], 201);
    }

    public function show(Request $request, Idea $idea): JsonResponse
    {
        $idea->load(['author:id,name', 'statusChanges.author:id,name']);

        return response()->json(['data' => [
            ...$this->present($idea, isset($this->votedByUser($request, [$idea->id])[$idea->id])),
            'history' => $idea->statusChanges->map(fn ($c) => [
                'from' => $c->from_status->value,
                'to' => $c->to_status->value,
                'by' => $c->author->name,
                'comment' => $c->comment,
                'at' => $c->created_at->toIso8601String(),
            ]),
        ]]);
    }

    public function similar(Request $request, SimilarIdeas $similar): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:140']]);

        return response()->json(['data' => $similar->find($data['title'])->map(fn ($m) => [
            'id' => $m['idea']->id,
            'title' => $m['idea']->title,
            'status' => $m['idea']->status->value,
            'votes_count' => $m['idea']->votes_count,
            'score' => $m['score'],
        ])]);
    }

    public function vote(Request $request, Idea $idea, IdeaService $ideas): JsonResponse
    {
        try {
            return response()->json(['data' => $ideas->toggleVote($idea, $request->user())]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function changeStatus(Request $request, Idea $idea, IdeaService $ideas): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(IdeaStatus::class)],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $ideas->changeStatus($idea, IdeaStatus::from($data['status']), $request->user(), $data['comment'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($idea->refresh()->load('author:id,name'), false)]);
    }

    /** @return array<int, true> */
    private function votedByUser(Request $request, array $ideaIds): array
    {
        return DB::table('idea_votes')->where('user_id', $request->user()->id)->whereIn('idea_id', $ideaIds)
            ->pluck('idea_id')->mapWithKeys(fn ($id) => [$id => true])->all();
    }

    private function present(Idea $idea, bool $votedByMe): array
    {
        return [
            'id' => $idea->id,
            'title' => $idea->title,
            'description' => $idea->description,
            'status' => $idea->status->value,
            'status_label' => $idea->status->label(),
            'accepts_votes' => $idea->status->acceptsVotes(),
            'allowed_next' => array_map(fn (IdeaStatus $s) => $s->value, $idea->status->allowedNext()),
            'votes_count' => $idea->votes_count,
            'voted_by_me' => $votedByMe,
            'author' => $idea->author?->name,
            'created_at' => $idea->created_at?->toIso8601String(),
        ];
    }
}
