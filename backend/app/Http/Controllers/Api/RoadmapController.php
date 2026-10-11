<?php

namespace App\Http\Controllers\Api;

use App\Enums\IdeaStatus;
use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Services\IdeaService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoadmapController extends Controller
{
    public function index(): JsonResponse
    {
        $ideas = Idea::whereIn('status', IdeaStatus::roadmap())
            ->orderBy('position')
            ->get(['id', 'title', 'status', 'votes_count', 'position'])
            ->groupBy(fn (Idea $i) => $i->status->value);

        return response()->json(['data' => array_map(fn (IdeaStatus $s) => [
            'status' => $s->value,
            'label' => $s->label(),
            'ideas' => ($ideas[$s->value] ?? collect())->map(fn (Idea $i) => [
                'id' => $i->id, 'title' => $i->title, 'votes_count' => $i->votes_count,
            ])->values(),
        ], IdeaStatus::roadmap())]);
    }

    public function reorder(Request $request, IdeaService $ideas): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, IdeaStatus::roadmap()))],
            'ids' => ['present', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        try {
            $ideas->reorder(IdeaStatus::from($data['status']), $data['ids']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $this->index();
    }
}
