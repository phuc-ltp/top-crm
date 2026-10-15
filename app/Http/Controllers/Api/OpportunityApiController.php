<?php

namespace App\Http\Controllers\Api;

use App\Models\Opportunity;
use Illuminate\Http\Request;

class OpportunityApiController extends ApiController
{
    private function transform(Opportunity $o): array
    {
        return [
            'id' => $o->id, 'code' => $o->code, 'title' => $o->title, 'value' => (float) $o->value,
            'probability' => $o->probability, 'weighted_value' => $o->weighted_value,
            'stage' => $o->stage?->only(['id', 'name', 'slug']), 'customer' => $o->customer?->only(['id', 'code', 'name']),
            'assigned_to' => $o->assignee?->only(['id', 'name']),
            'expected_close_date' => $o->expected_close_date?->toDateString(), 'closed_at' => $o->closed_at?->toIso8601String(),
        ];
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);
        $page = Opportunity::visibleTo($request->user())->with(['stage', 'customer', 'assignee'])
            ->when($request->filled('stage'), fn ($q) => $q->whereHas('stage', fn ($s) => $s->where('slug', $request->stage)))
            ->latest()->paginate($this->perPage($request));

        return $this->paginated($page, fn ($o) => $this->transform($o));
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);

        return $this->ok($this->transform($opportunity->load(['stage', 'customer', 'assignee'])));
    }
}
