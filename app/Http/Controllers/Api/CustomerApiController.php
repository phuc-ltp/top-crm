<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerApiController extends ApiController
{
    public function __construct(private CustomerService $service) {}

    public static function transform(Customer $c): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email,
            'address' => $c->address, 'company' => $c->company, 'type' => $c->type, 'status' => $c->status,
            'source' => $c->source?->name, 'assigned_to' => $c->assignee ? ['id' => $c->assignee->id, 'name' => $c->assignee->name] : null,
            'tags' => $c->relationLoaded('tags') ? $c->tags->pluck('name') : [],
            'created_at' => $c->created_at?->toIso8601String(), 'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);
        $page = Customer::visibleTo($request->user())->with(['source', 'assignee', 'tags'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', $this->like($request->q)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate($this->perPage($request));

        return $this->paginated($page, fn ($c) => self::transform($c));
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        return $this->ok(self::transform($customer->load(['source', 'assignee', 'tags'])));
    }

    public function store(CustomerRequest $request)
    {
        $this->authorize('create', Customer::class);
        $data = $request->validated();
        if (! $request->user()->can('customers.assign')) {
            $data['assigned_to'] = $request->user()->id;
        }
        if (! $request->boolean('confirm_duplicate') && ($dups = $this->service->findDuplicates($data['email'] ?? null, $data['phone'] ?? null))->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => __('Khách hàng có thể bị trùng. Gửi confirm_duplicate=1 để vẫn tạo.'),
                'duplicates' => $dups->map(fn ($d) => ['code' => $d->code, 'name' => $d->name])], 409);
        }
        $customer = $this->service->save(new Customer, $data, $request->user());

        return $this->ok(self::transform($customer->load(['source', 'assignee', 'tags'])), __('Đã tạo khách hàng.'), 201);
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validated();
        if (! $request->user()->can('customers.assign')) {
            $data['assigned_to'] = $customer->assigned_to;
        }
        $this->service->save($customer, $data, $request->user());

        return $this->ok(self::transform($customer->fresh(['source', 'assignee', 'tags'])), __('Đã cập nhật khách hàng.'));
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return $this->ok(null, __('Đã lưu trữ khách hàng.'));
    }
}
