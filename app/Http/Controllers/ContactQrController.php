<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Support\QrImage;
use Illuminate\Database\Eloquent\Model;

/**
 * Mã QR liên hệ (lead / khách hàng / người liên hệ): quét để gọi (tel:) hoặc lưu danh bạ (vCard).
 * Chỉ người được xem bản ghi mới lấy được mã; mã tạo trên máy chủ, không gửi dữ liệu ra dịch vụ ngoài.
 */
class ContactQrController extends Controller
{
    private const TYPES = ['lead' => Lead::class, 'customer' => Customer::class, 'contact' => Contact::class];

    public function show(string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $model = self::TYPES[$type]::find($id);
        abort_unless($model && request()->user()->can('view', $model), 404);
        [$name, $company, $phone, $email] = $this->details($model);
        abort_unless(filled($phone), 404);
        $dial = preg_replace('/[^0-9+]/', '', $phone);

        return response()->json([
            'name' => $name, 'company' => $company, 'phone' => $phone, 'dial' => $dial,
            'tel_qr' => QrImage::dataUri('tel:'.$dial, 8),
            'vcard_qr' => QrImage::dataUri($this->vcard($name, $company, $dial, $email), 6),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function details(Model $m): array
    {
        return match (true) {
            $m instanceof Lead => [$m->name, $m->company, $m->phone, $m->email],
            $m instanceof Customer => [$m->name, $m->type === 'business' ? null : $m->company, $m->phone, $m->email],
            $m instanceof Contact => [$m->full_name, $m->company ?: $m->customer?->name, $m->phone, $m->email],
        };
    }

    /** vCard 3.0 (UTF-8) — camera iPhone / Android đọc được để lưu danh bạ. */
    private function vcard(string $name, ?string $company, string $dial, ?string $email): string
    {
        $esc = fn (?string $v) => str_replace(["\\", ';', ',', "\n", "\r"], ['\\\\', '\;', '\,', ' ', ''], (string) $v);

        return implode("\r\n", array_filter([
            'BEGIN:VCARD', 'VERSION:3.0', 'FN:'.$esc($name), 'N:'.$esc($name).';;;;',
            $company ? 'ORG:'.$esc($company) : null,
            'TEL;TYPE=CELL:'.$dial,
            $email ? 'EMAIL:'.$esc($email) : null,
            'END:VCARD',
        ]));
    }
}
