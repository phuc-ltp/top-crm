<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Services\SalesDocumentService;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\VietQr;
use Tests\TestCase;

/** Mã QR liên hệ (gọi / lưu danh bạ) và VietQR chuyển khoản trên hóa đơn. */
class QrFeaturesTest extends TestCase
{
    /** Tách chuỗi TLV EMVCo thành [id => giá trị]. */
    private function tlv(string $s): array
    {
        $out = [];
        for ($i = 0; $i < strlen($s);) {
            $id = substr($s, $i, 2);
            $len = (int) substr($s, $i + 2, 2);
            $out[$id] = substr($s, $i + 4, $len);
            $i += 4 + $len;
        }

        return $out;
    }

    public function test_vietqr_payload_follows_emvco_napas_format(): void
    {
        $this->assertSame('29B1', VietQr::crc16('123456789'));                                   // giá trị kiểm tra chuẩn CRC-16/CCITT-FALSE
        $p = VietQr::payload('970436', '0071 000-123456', 1250000, 'HĐ00009 thanh toán');
        $f = $this->tlv($p);
        $this->assertSame(['01', '12', '704', '1250000', 'VN'], [$f['00'], $f['01'], $f['53'], $f['54'], $f['58']]);
        $m = $this->tlv($f['38']);
        $this->assertSame(['A000000727', 'QRIBFTTA'], [$m['00'], $m['02']]);
        $this->assertSame(['00' => '970436', '01' => '0071000123456'], $this->tlv($m['01']));
        $this->assertSame('HD00009 thanh toan', $this->tlv($f['62'])['08']);                       // bỏ dấu, bỏ ký tự đặc biệt
        $this->assertSame(VietQr::crc16(substr($p, 0, -4)), substr($p, -4));                       // CRC tính trên toàn chuỗi đến "6304"
        $this->assertSame('11', $this->tlv(VietQr::payload('970436', '123'))['01']);               // không số tiền → mã tĩnh
    }

    public function test_invoice_shows_vietqr_only_when_bank_selected_and_unpaid(): void
    {
        $this->actingAs($admin = $this->admin());
        $inv = app(SalesDocumentService::class)->save(new Invoice, ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'due_date' => today()->addDays(20),
            'status' => 'issued', 'tax_rate' => 0, 'discount_amount' => 0, 'assigned_to' => $admin->id, 'created_by' => $admin->id],
            [['description' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => 2000000]]);
        Setting::put(['bank_name' => 'Vietcombank', 'bank_account_number' => '0071000123456', 'bank_account_name' => 'CONG TY TOP']);
        $this->get(route('invoices.show', $inv))->assertOk()->assertSee(__('Thông tin chuyển khoản'))->assertDontSee(__('Mã VietQR thanh toán'));

        $base = ['company_name' => 'TOP', 'brand_color' => '#FF6B00', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND', 'date_format' => 'd/m/Y', 'default_tax_rate' => 10,
            'bank_name' => 'Vietcombank', 'bank_account_number' => '0071000123456', 'bank_account_name' => 'CONG TY TOP'];
        $this->put(route('settings.update'), $base + ['bank_bin' => '999999'])->assertSessionHasErrors('bank_bin');
        $this->put(route('settings.update'), $base + ['bank_bin' => '970436'])->assertSessionHasNoErrors();
        $this->get(route('invoices.show', $inv))->assertSee(__('Mã VietQR thanh toán'))->assertSee('data:image/png;base64', false)
            ->assertSee('id="payQrModal"', false)->assertSee(__('Thanh toán bằng QR'))->assertSee('data-countup="2000000"', false)->assertSee('download="VietQR-', false);
        $this->get(route('invoices.pdf', $inv))->assertOk()->assertHeader('content-type', 'application/pdf');

        app(PaymentService::class)->record($inv, ['amount' => 2000000], $admin);
        $this->get(route('invoices.show', $inv))->assertDontSee(__('Mã VietQR thanh toán'))->assertDontSee('id="payQrModal"', false);   // đã thanh toán đủ
    }

    public function test_contact_qr_returns_call_and_vcard_codes_with_permission_checks(): void
    {
        $owner = $this->userWithRole('Sales Manager');
        $this->actingAs($owner);
        $lead = Lead::create(['name' => 'Vũ Văn Cường', 'company' => 'Công ty Giáo dục Hà Nội', 'phone' => '0989 250 920', 'email' => 'cuong@example.vn', 'status' => 'new']);
        $noPhone = Lead::create(['name' => 'Không số', 'status' => 'new']);

        $json = $this->getJson(route('contact-qr', ['lead', $lead->id]))->assertOk()->json();
        $this->assertSame(['Vũ Văn Cường', 'Công ty Giáo dục Hà Nội', '0989250920'], [$json['name'], $json['company'], $json['dial']]);
        $this->assertStringStartsWith('data:image/png;base64,', $json['tel_qr']);
        $this->assertStringStartsWith('data:image/png;base64,', $json['vcard_qr']);
        $this->getJson(route('contact-qr', ['lead', $noPhone->id]))->assertNotFound();
        $this->get(route('leads.index'))->assertSee('data-contact-qr', false);
        $this->get(route('leads.show', $lead))->assertSee(route('contact-qr', ['lead', $lead->id]), false);

        $customer = $this->customer(['phone' => '0901234567']);
        $this->getJson(route('contact-qr', ['customer', $customer->id]))->assertOk()->assertJsonPath('dial', '0901234567');
        $contact = Contact::create(['full_name' => 'Người Liên Hệ', 'customer_id' => $customer->id, 'phone' => '+84 912 000 111']);
        $this->getJson(route('contact-qr', ['contact', $contact->id]))->assertOk()->assertJsonPath('dial', '+84912000111');

        // Không được xem bản ghi → không lấy được số
        $this->actingAs($this->userWithRole('Sales Employee'))->getJson(route('contact-qr', ['lead', $lead->id]))->assertNotFound();
        $this->get('/contact-qr/user/1')->assertNotFound();

        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $leadB = app(CurrentOrganization::class)->run($orgB->id, fn () => Lead::create(['name' => 'Lead B', 'phone' => '0911111111', 'status' => 'new']));
        $this->actingAs($owner)->getJson(route('contact-qr', ['lead', $leadB->id]))->assertNotFound();
    }
}
