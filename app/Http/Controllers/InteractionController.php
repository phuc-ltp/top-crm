<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InteractionController extends Controller
{
    public function store(Request $request, string $type, int $id)
    {
        $class = Relation::getMorphedModel($type);
        abort_unless($class, 404);
        $subject = $class::findOrFail($id);
        // Ai được sửa đối tượng thì được thêm ghi chú
        $this->authorize('update', $subject);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Interaction::TYPES))],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $subject->interactions()->create($data + ['user_id' => $request->user()->id, 'occurred_at' => $data['occurred_at'] ?? now()]);

        return back()->with('success', __('Đã thêm ghi chú/tương tác.'));
    }

    public function destroy(Request $request, Interaction $interaction)
    {
        abort_unless($interaction->user_id === $request->user()->id || $request->user()->isPlatformAdmin() || $request->user()->isOrganizationOwner(), 403);
        $interaction->delete();

        return back()->with('success', __('Đã xóa ghi chú.'));
    }
}
