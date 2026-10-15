<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Hoạt động trên khách hàng / người liên hệ / lead / cơ hội.
 * - Ghi nhận (mode=log): việc đã diễn ra → status done.
 * - Lên lịch (mode=plan): việc sắp làm, có người phụ trách và thời điểm hẹn → status planned, được nhắc khi đến hạn.
 */
class InteractionController extends Controller
{
    private function subjectFor(string $type, int $id)
    {
        $class = Interaction::SUBJECTS[$type][0] ?? null;
        abort_unless($class, 404);

        return $class::findOrFail($id);   // global scope tổ chức: đối tượng tổ chức khác → 404
    }

    private function rules(string $mode): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(Interaction::TYPES))],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => [$mode === 'log' ? 'required' : 'nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
            'scheduled_at' => [$mode === 'plan' ? 'required' : 'nullable', 'date'],
            'assigned_to' => ['nullable', TenantRule::member()],
        ];
    }

    private function attributes(): array
    {
        return ['type' => __('Loại hoạt động'), 'content' => __('Nội dung'), 'scheduled_at' => __('Thời điểm hẹn'), 'assigned_to' => __('Người phụ trách'), 'occurred_at' => __('Thời điểm')];
    }

    public function store(Request $request, string $type, int $id)
    {
        $subject = $this->subjectFor($type, $id);
        $this->authorize('update', $subject);   // ai được sửa đối tượng thì được thêm hoạt động

        $mode = $request->input('mode') === 'plan' ? 'plan' : 'log';
        $data = $request->validate($this->rules($mode), [], $this->attributes());
        $user = $request->user();

        if ($mode === 'plan') {
            $activity = $subject->interactions()->create([
                'type' => $data['type'], 'status' => 'planned', 'subject' => $data['subject'] ?? null, 'content' => $data['content'] ?? null,
                'scheduled_at' => $data['scheduled_at'], 'user_id' => $user->id, 'assigned_to' => $data['assigned_to'] ?? $user->id,
            ]);
            $this->notifyAssignee($activity, $user);

            return back()->with('success', __('Đã lên lịch hoạt động.'));
        }

        $subject->interactions()->create([
            'type' => $data['type'], 'status' => 'done', 'subject' => $data['subject'] ?? null, 'content' => $data['content'],
            'occurred_at' => $data['occurred_at'] ?? now(), 'completed_at' => now(), 'user_id' => $user->id,
        ]);

        return back()->with('success', __('Đã ghi nhận hoạt động.'));
    }

    public function update(Request $request, Interaction $interaction)
    {
        abort_unless($interaction->editableBy($request->user()), 403);
        $mode = $interaction->isPlanned() ? 'plan' : 'log';
        $data = $request->validate($this->rules($mode), [], $this->attributes());

        $previousAssignee = $interaction->assigned_to;
        $interaction->fill(collect($data)->only(['type', 'subject', 'content'])->all());
        if ($mode === 'plan') {
            $interaction->scheduled_at = $data['scheduled_at'];
            $interaction->assigned_to = $data['assigned_to'] ?? $interaction->assigned_to;
            if ($interaction->isDirty('scheduled_at')) {
                $interaction->reminded_at = null;   // đổi giờ hẹn → nhắc lại theo giờ mới
            }
        } elseif (! empty($data['occurred_at'])) {
            $interaction->occurred_at = $data['occurred_at'];
        }
        $interaction->save();
        if ($interaction->assigned_to !== $previousAssignee) {
            $this->notifyAssignee($interaction, $request->user());
        }

        return back()->with('success', __('Đã cập nhật hoạt động.'));
    }

    public function complete(Request $request, Interaction $interaction)
    {
        abort_unless($interaction->completableBy($request->user()), 403);
        abort_unless($interaction->isPlanned(), 422, __('Hoạt động này đã hoàn thành.'));
        $data = $request->validate(['outcome' => ['nullable', 'string', 'max:1000']], [], ['outcome' => __('Kết quả')]);

        $interaction->forceFill([
            'status' => 'done', 'outcome' => $data['outcome'] ?? null,
            'completed_at' => now(), 'occurred_at' => now(),
        ])->save();
        ActivityLogger::log('updated', $interaction->interactable, 'Hoàn thành hoạt động: '.label_of(Interaction::TYPES, $interaction->type).($interaction->subject ? ' — '.$interaction->subject : ''));

        return back()->with('success', __('Đã đánh dấu hoàn thành.'));
    }

    public function destroy(Request $request, Interaction $interaction)
    {
        abort_unless($interaction->editableBy($request->user()), 403);
        $interaction->delete();

        return back()->with('success', __('Đã xóa hoạt động.'));
    }

    private function notifyAssignee(Interaction $activity, User $actor): void
    {
        $assignee = $activity->assignee;
        if (! $assignee || $assignee->id === $actor->id || ! $activity->interactable) {
            return;
        }
        CrmNotification::send($assignee, 'task_assigned',
            __(':actor giao cho bạn hoạt động ":title" lúc :time', [
                'actor' => $actor->name, 'title' => $activity->subject ?: label_of(Interaction::TYPES, $activity->type), 'time' => vn_date($activity->scheduled_at, true),
            ], $assignee->preferredLocale()),
            $activity->subjectUrl(), 'bi-calendar-check');
    }
}
