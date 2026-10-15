<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LeadRequest;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\Request;

class LeadApiController extends ApiController
{
    public function __construct(private LeadService $service) {}

    private function transform(Lead $l): array
    {
        return [
            'id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'company' => $l->company, 'email' => $l->email,
            'phone' => $l->phone, 'status' => $l->status, 'score' => $l->score, 'estimated_value' => (float) $l->estimated_value,
            'source' => $l->source?->name, 'assigned_to' => $l->assignee?->only(['id', 'name']),
            'converted_at' => $l->converted_at?->toIso8601String(), 'created_at' => $l->created_at?->toIso8601String(),
        ];
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $page = Lead::visibleTo($request->user())->with(['source', 'assignee'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', $this->like($request->q)))
            ->latest()->paginate($this->perPage($request));

        return $this->paginated($page, fn ($l) => $this->transform($l));
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        return $this->ok($this->transform($lead->load(['source', 'assignee'])));
    }

    public function store(LeadRequest $request)
    {
        $this->authorize('create', Lead::class);
        $data = $request->validated();
        if (! $request->user()->can('leads.assign')) {
            $data['assigned_to'] = $request->user()->id;
        }
        $lead = $this->service->save(new Lead, $data, $request->user(), $request->boolean('auto_score', true));

        return $this->ok($this->transform($lead->load(['source', 'assignee'])), 'Đã tạo lead.', 201);
    }

    public function update(LeadRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $data = $request->validated();
        if (! $request->user()->can('leads.assign')) {
            $data['assigned_to'] = $lead->assigned_to;
        }
        $this->service->save($lead, $data, $request->user(), $request->boolean('auto_score'));

        return $this->ok($this->transform($lead->fresh(['source', 'assignee'])), 'Đã cập nhật lead.');
    }
}
