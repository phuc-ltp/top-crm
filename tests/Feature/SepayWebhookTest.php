<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\SepayWebhook;
use App\Services\SalesDocumentService;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SepayWebhookTest extends TestCase
{
    private const KEY = 'sk_test_0123456789abcdefXYZ';

    private User $owner;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->admin();
        $orgId = app(CurrentOrganization::class)->id();
        Organization::whereKey($orgId)->update(['owner_id' => $this->owner->id]);
        $this->actingAs($this->owner)->put(route('settings.sepay.update'), ['sepay_enabled' => '1', 'sepay_api_key' => self::KEY])->assertSessionHasNoErrors();
        $this->token = Setting::get('sepay_webhook_token');
        auth()->logout();
    }

    private function invoice(float $price = 1000000, string $status = 'issued'): Invoice
    {
        $this->actingAs($this->owner);
        $inv = app(SalesDocumentService::class)->save(new Invoice, ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'status' => $status, 'tax_rate' => 0],
            [['description' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => $price]]);
        auth()->logout();

        return $inv;
    }

    private function hook(array $over = [], ?string $key = self::KEY, ?string $token = null)
    {
        static $id = 90000;
        $payload = $over + ['id' => ++$id, 'gateway' => 'TPBank', 'transactionDate' => now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'),
            'accountNumber' => '0000000000', 'code' => null, 'content' => 'chuyen tien', 'transferType' => 'in',
            'transferAmount' => 1000000, 'accumulated' => 0, 'subAccount' => '', 'description' => ''];
        $payload['referenceCode'] ??= 'FT'.$payload['id'];

        return $this->postJson(route('webhooks.sepay', $token ?? $this->token), $payload, $key ? ['Authorization' => 'Apikey '.$key] : []);
    }

    public function test_settings_store_key_encrypted_and_only_managers_can_open(): void
    {
        $raw = \App\Models\OrganizationSetting::where('key', 'sepay_api_key')->value('value');
        $this->assertNotSame(self::KEY, $raw);
        $this->assertSame(self::KEY, Crypt::decryptString($raw));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $this->token);
        $this->actingAs($this->owner)->get(route('settings.sepay'))->assertOk()->assertSee(route('webhooks.sepay', $this->token))->assertDontSee(self::KEY);

        $staff = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $this->actingAs($staff)->get(route('settings.sepay'))->assertForbidden();
        $this->actingAs($staff)->put(route('settings.sepay.update'), ['sepay_enabled' => '0'])->assertForbidden();
    }

    public function test_cannot_enable_without_key_and_regenerate_changes_url(): void
    {
        Setting::put(['sepay_api_key' => ''], 'payments');
        $this->actingAs($this->owner)->put(route('settings.sepay.update'), ['sepay_enabled' => '1'])->assertSessionHasErrors('sepay_api_key');
        $this->actingAs($this->owner)->post(route('settings.sepay.regenerate'))->assertRedirect(route('settings.sepay'));
        $this->assertNotSame($this->token, Setting::get('sepay_webhook_token'));
        auth()->logout();
        $this->hook()->assertNotFound();   // địa chỉ cũ ngừng hoạt động
    }

    public function test_matching_transfer_records_payment_once(): void
    {
        $inv = $this->invoice(1000000);
        $payload = ['id' => 555, 'content' => 'IBFT '.$inv->number.' thanh toan', 'transferAmount' => 400000];
        $this->hook($payload)->assertOk()->assertExactJson(['success' => true]);
        $this->hook($payload)->assertOk()->assertExactJson(['success' => true]);   // SePay gửi lại → không ghi nhận lần 2

        $inv->refresh();
        $this->assertSame(1, $inv->payments()->count());
        $this->assertEquals(400000, (float) $inv->amount_paid);
        $this->assertSame('partial', $inv->payment_status);
        $tx = BankTransaction::sole();
        $this->assertSame('matched', $tx->status);
        $this->assertSame($inv->id, $tx->invoice_id);
        $p = $inv->payments()->first();
        $this->assertSame('bank_transfer', $p->method);
        $this->assertSame('FT555', $p->reference);
        $this->assertSame($this->owner->id, (int) $p->recorded_by);

        $this->hook(['content' => strtolower(str_replace('HD', 'hd ', $inv->number)), 'transferAmount' => 600000])->assertOk();   // "hd 00001"
        $this->assertSame('paid', $inv->fresh()->payment_status);
    }

    public function test_falls_back_to_owner_role_when_organization_has_no_owner(): void
    {
        Organization::whereKey(app(CurrentOrganization::class)->id())->update(['owner_id' => null]);
        $inv = $this->invoice(500000);
        $this->hook(['content' => $inv->number, 'transferAmount' => 500000])->assertOk();
        $this->assertSame('paid', $inv->fresh()->payment_status);
        $this->assertSame($this->owner->id, (int) $inv->payments()->first()->recorded_by);
    }

    public function test_code_field_is_used(): void
    {
        $inv = $this->invoice(500000);
        $this->hook(['code' => $inv->number, 'content' => 'MBVCB.123456.chuyen khoan', 'transferAmount' => 500000])->assertOk();
        $this->assertSame('paid', $inv->fresh()->payment_status);
    }

    public function test_unmatched_cases_are_kept_for_manual_handling(): void
    {
        $inv = $this->invoice(500000);
        $draft = $this->invoice(300000, 'draft');
        $this->hook(['content' => 'tien hang thang 11'])->assertOk()->assertExactJson(['success' => true]);
        $this->hook(['content' => $inv->number, 'transferAmount' => 900000])->assertOk();                  // chuyển dư
        $this->hook(['content' => $inv->number.' '.$draft->number, 'transferAmount' => 100])->assertOk(); // 2 số hóa đơn
        $this->hook(['content' => $draft->number, 'transferAmount' => 100])->assertOk();                  // hóa đơn nháp
        $this->hook(['content' => 'HD99999', 'transferAmount' => 100])->assertOk();                       // không tồn tại
        $this->hook(['content' => $inv->number, 'transferType' => 'out', 'transferAmount' => 500000])->assertOk();

        $this->assertSame(0, $inv->payments()->count());
        $this->assertSame(5, BankTransaction::where('status', 'unmatched')->count());
        $this->assertSame(1, BankTransaction::where('status', 'ignored')->count());
        $this->assertStringContainsString('lớn hơn số còn phải thu', BankTransaction::where('amount', 900000)->value('note'));
        $this->assertSame(5, $this->owner->notifications()->count());   // 5 giao dịch chưa khớp báo chủ sở hữu (tiền ra không báo)
        $this->actingAs($this->owner)->get(route('settings.sepay', ['status' => 'unmatched']))->assertOk()->assertSee('tien hang thang 11');
    }

    public function test_rejects_wrong_key_disabled_and_bad_payload(): void
    {
        $inv = $this->invoice(500000);
        $this->hook(['content' => $inv->number], 'sai-khoa')->assertUnauthorized()->assertJson(['success' => false]);
        $this->hook(['content' => $inv->number], null)->assertUnauthorized();
        $this->hook(['content' => $inv->number], self::KEY, str_repeat('a', 40))->assertNotFound();
        $this->postJson(route('webhooks.sepay', $this->token), ['transferType' => 'in'], ['Authorization' => 'Apikey '.self::KEY])->assertStatus(422);

        Setting::put(['sepay_enabled' => '0'], 'payments');
        $this->hook(['content' => $inv->number])->assertUnauthorized();
        $this->assertSame(0, BankTransaction::count());
        $this->assertSame(0, $inv->payments()->count());
    }

    public function test_webhook_of_one_organization_never_touches_another(): void
    {
        $invA = $this->invoice(500000);                       // HD00001 của tổ chức A
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        [$invB, $tokenB] = app(CurrentOrganization::class)->run($orgB->id, function () {
            $inv = app(SalesDocumentService::class)->save(new Invoice, ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'status' => 'issued', 'tax_rate' => 0],
                [['description' => 'Dịch vụ B', 'quantity' => 1, 'unit_price' => 500000]]);
            Setting::put(['sepay_enabled' => '1', 'sepay_api_key' => Crypt::encryptString('khoa-cua-to-chuc-B-123456'), 'sepay_webhook_token' => str_repeat('B', 40)], 'payments');

            return [$inv, str_repeat('B', 40)];
        });
        $invB->forceFill(['number' => $invA->number])->saveQuietly();   // trường hợp xấu nhất: 2 tổ chức trùng số hóa đơn

        $this->hook(['content' => $invA->number, 'transferAmount' => 500000], self::KEY, $tokenB)->assertUnauthorized();   // khóa A không dùng được cho B
        $this->hook(['content' => $invA->number, 'transferAmount' => 500000])->assertOk();
        $this->assertSame('paid', $invA->fresh()->payment_status);
        $this->assertSame('unpaid', Invoice::withoutGlobalScopes()->find($invB->id)->payment_status);
    }

    public function test_invoice_number_parsing(): void
    {
        $this->assertSame(['HD00010'], SepayWebhook::invoiceNumbers('TT HD00010 tien hang'));
        $this->assertSame(['HD00010'], SepayWebhook::invoiceNumbers('hd-00010'));
        $this->assertSame(['HD00010'], SepayWebhook::invoiceNumbers('MBVCB.1234.HD00010.CT'));
        $this->assertSame([], SepayWebhook::invoiceNumbers('HD000101234'));      // dính thêm số → không đoán
        $this->assertSame([], SepayWebhook::invoiceNumbers('XHD00010'));         // dính chữ phía trước
        $this->assertSame(['HD00010', 'HD00011'], SepayWebhook::invoiceNumbers('HD00010 HD00011 HD00010'));
    }
}
