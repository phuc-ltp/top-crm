<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Services\LeadService;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Form thu lead công khai (không cần đăng nhập): /f/{token}.
 * Chống spam: token riêng mỗi tổ chức (đổi được), trường ẩn (honeypot), kiểm tra thời gian điền (≥ 3 giây, ≤ 1 ngày),
 * giới hạn số lần gửi theo IP. Chỉ hiển thị tên + logo công ty — không lộ dữ liệu nào khác.
 */
class WebLeadFormController extends Controller
{
    private function resolve(string $token): Organization
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);
        $orgId = OrganizationSetting::where('key', 'lead_form_token')->where('value', $token)->value('organization_id');
        $org = $orgId ? Organization::where('status', 'active')->find($orgId) : null;
        abort_unless($org && Setting::allCached($org->id)['lead_form_enabled'] === '1', 404);

        return $org;
    }

    public function show(Request $request, string $token)
    {
        $org = $this->resolve($token);

        return response()->view('lead-form.show', [
            'token' => $token,
            'company' => Setting::allCached($org->id)['company_name'] ?: $org->name,
            'logo' => app(CurrentOrganization::class)->run($org->id, fn () => Setting::get('company_logo')),
            'stamp' => Crypt::encryptString((string) now()->getTimestamp()),
            'thanks' => Setting::allCached($org->id)['lead_form_thanks'],
            'tracking' => collect($request->only(['c', 'utm_source', 'utm_medium', 'utm_campaign']))->map(fn ($v) => is_string($v) ? mb_substr($v, 0, 100) : null)->filter()->all(),
        ])->header('X-Robots-Tag', 'noindex');
    }

    public function submit(Request $request, string $token, LeadService $leads)
    {
        $org = $this->resolve($token);

        // Bẫy spam: trường ẩn phải trống; thời gian điền hợp lý
        $started = rescue(fn () => (int) Crypt::decryptString((string) $request->input('stamp')), 0, false);
        $elapsed = now()->getTimestamp() - $started;
        if (filled($request->input('website')) || $elapsed < 3 || $elapsed > 86400) {
            return redirect()->route('lead-form.show', $token)->with('lead_form_sent', true);   // trả lời như thành công, không tạo lead
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['nullable', 'boolean'],
            'c' => ['nullable', 'string', 'max:16', 'regex:/^[a-z0-9]+$/'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ], [], ['name' => __('Họ tên'), 'email' => 'Email', 'phone' => __('Số điện thoại'), 'company' => __('Công ty'), 'message' => __('Nội dung')]);

        app(CurrentOrganization::class)->run($org->id, function () use ($org, $data, $request, $leads) {
            $settings = Setting::allCached($org->id);
            $assignee = User::inOrganization($org->id)->active()->find((int) $settings['lead_form_assignee']) ?? $org->owner;
            $sourceId = LeadSource::whereKey((int) $settings['lead_form_source'])->value('id')
                ?? LeadSource::firstOrCreate(['name' => 'Website'], ['is_active' => true])->id;
            $firstStage = LeadStage::whereNotIn('key', [LeadStage::CONVERTED, LeadStage::LOST])->orderBy('position')->value('key') ?? 'new';

            $lead = DB::transaction(function () use ($data, $assignee, $sourceId, $firstStage, $request, $leads) {
                $lead = new Lead([
                    'name' => $data['name'], 'email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
                    'phone' => \App\Services\CustomerService::normalizePhone($data['phone'] ?? null), 'company' => $data['company'] ?? null,
                    'lead_source_id' => $sourceId, 'status' => $firstStage, 'assigned_to' => $assignee?->id,
                    'notes' => trim(__('Gửi từ form website').(! empty($data['message']) ? ":\n".$data['message'] : '')),
                    'marketing_consent' => (bool) ($data['consent'] ?? false),
                    'marketing_consent_source' => ($data['consent'] ?? false) ? __('Form website') : null,
                    'utm_source' => $data['utm_source'] ?? null, 'utm_medium' => $data['utm_medium'] ?? null, 'utm_campaign' => $data['utm_campaign'] ?? null,
                ]);
                $lead->score = $leads->score($lead);
                $lead->save();
                // Link theo dõi của chiến dịch (?c=mã): gắn lead vào đúng chiến dịch của tổ chức này
                if (! empty($data['c']) && ($campaign = \App\Models\Campaign::where('tracking_code', $data['c'])->first())) {
                    $campaign->leads()->syncWithoutDetaching([$lead->id => ['created_at' => now()]]);
                }

                return $lead;
            });

            ActivityLogger::log('created', $lead, 'Lead mới từ form website: '.$lead->name.' (IP '.$request->ip().')');
            if ($assignee) {
                CrmNotification::send($assignee, 'lead_assigned', __('Lead mới từ form website: :name', ['name' => $lead->name], $assignee->preferredLocale()),
                    route('leads.show', $lead), 'bi-globe2');
            }
        });

        return redirect()->route('lead-form.show', $token)->with('lead_form_sent', true);
    }
}
