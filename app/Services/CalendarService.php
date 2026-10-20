<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lịch & lịch hẹn (bước 4.4).
 *  - Trùng lịch: hai lịch hẹn đã lên lịch (planned) của cùng một người (phụ trách hoặc tham dự) có khoảng thời gian giao nhau.
 *    Lịch không có giờ kết thúc được coi là kéo dài DEFAULT_MINUTES phút.
 *  - Xuất .ics (RFC 5545) để thêm vào Google Calendar / Outlook — không cần tích hợp tài khoản.
 */
class CalendarService
{
    public const DEFAULT_MINUTES = 30;

    public static function endOf(Interaction $i): ?Carbon
    {
        return $i->ends_at ?? $i->scheduled_at?->copy()->addMinutes(self::DEFAULT_MINUTES);
    }

    /**
     * Lịch hẹn khác của các người dùng $userIds giao với [start, end).
     * @return Collection<int, array{user: User, activity: Interaction}>
     */
    public function conflicts(array $userIds, Carbon $start, Carbon $end, ?int $exceptId = null): Collection
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (! $userIds) {
            return collect();
        }
        $candidates = Interaction::planned()->with(['attendees:id,name', 'assignee:id,name'])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where('scheduled_at', '<', $end)
            ->where('scheduled_at', '>', $start->copy()->subDay())   // giới hạn quét; lịch dài hơn 1 ngày hiếm
            ->where(fn ($q) => $q->whereIn('assigned_to', $userIds)->orWhereHas('attendees', fn ($a) => $a->whereIn('users.id', $userIds)))
            ->get();

        $out = collect();
        foreach ($candidates as $c) {
            if (self::endOf($c)->lte($start)) {
                continue;
            }
            $people = $c->attendees->push($c->assignee)->filter()->unique('id')->filter(fn ($u) => in_array($u->id, $userIds, true));
            foreach ($people as $u) {
                $out->push(['user' => $u, 'activity' => $c]);
            }
        }

        return $out;
    }

    /** Nội dung tệp .ics cho một lịch hẹn (giờ UTC, ký tự đặc biệt được escape theo RFC 5545). */
    public function ics(Interaction $i, string $organizer): string
    {
        $esc = fn (?string $v) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $v);
        $fmt = fn (Carbon $d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $title = $i->subject ?: label_of(Interaction::TYPES, $i->type);
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TOP CRM//VI', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:interaction-'.$i->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($i->scheduled_at),
            'DTEND:'.$fmt(self::endOf($i)),
            'SUMMARY:'.$esc($title),
            'DESCRIPTION:'.$esc(trim(($i->content ?? '')."\n".__('Liên quan').': '.$i->subjectLabel())),
            'LOCATION:'.$esc($i->location),
            'ORGANIZER;CN='.$esc($organizer).':mailto:no-reply@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }
}
