<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_full_crud(): void
    {
        $this->actingAs($this->admin());
        $tag = Tag::first();

        $this->post(route('customers.store'), [
            'name' => 'Công ty Thử Nghiệm', 'type' => 'business', 'status' => 'active',
            'phone' => '+84 901 234 567', 'email' => 'LIENHE@ThuNghiem.vn', 'tags' => [$tag->id],
        ])->assertRedirect();

        $c = Customer::where('name', 'Công ty Thử Nghiệm')->firstOrFail();
        $this->assertMatchesRegularExpression('/^KH\d{5}$/', $c->code);
        $this->assertSame('0901234567', $c->phone, 'Số điện thoại được chuẩn hóa');
        $this->assertSame('lienhe@thunghiem.vn', $c->email);
        $this->assertTrue($c->tags->contains($tag));
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'subject_type' => 'customer', 'subject_id' => $c->id]);

        $this->put(route('customers.update', $c), ['name' => 'Công ty Đổi Tên', 'type' => 'business', 'status' => 'vip'])->assertRedirect(route('customers.show', $c));
        $this->assertSame('vip', $c->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'updated', 'subject_id' => $c->id]);

        $this->delete(route('customers.destroy', $c))->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted($c);
        $this->delete(route('customers.destroy', $c->id))->assertSessionHas('success'); // khôi phục
        $this->assertNotSoftDeleted($c);
    }

    public function test_validation_errors(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('customers.store'), ['name' => '', 'type' => 'xxx', 'status' => 'active', 'email' => 'khong-hop-le'])
            ->assertSessionHasErrors(['name', 'type', 'email']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_duplicate_warning_and_confirm(): void
    {
        $this->actingAs($this->admin());
        $this->customer(['phone' => '0901234567', 'email' => 'trung@example.com']);

        $payload = ['name' => 'Khách trùng', 'type' => 'individual', 'status' => 'active', 'phone' => '090 123 4567'];
        $this->post(route('customers.store'), $payload)->assertSessionHas('duplicates');
        $this->assertDatabaseMissing('customers', ['name' => 'Khách trùng']);

        $this->getJson(route('customers.check-duplicate', ['email' => 'TRUNG@example.com']))->assertJsonCount(1, 'duplicates');

        $this->post(route('customers.store'), $payload + ['confirm_duplicate' => 1])->assertRedirect();
        $this->assertDatabaseHas('customers', ['name' => 'Khách trùng']);
    }

    public function test_search_filter_and_pagination(): void
    {
        $this->actingAs($this->admin());
        for ($i = 1; $i <= 20; $i++) {
            $this->customer(['name' => 'Khách số '.$i, 'status' => $i % 2 ? 'active' : 'vip']);
        }
        $this->get(route('customers.index', ['q' => 'Khách số 7']))->assertSee('Khách số 7')->assertDontSee('Khách số 8');
        $this->get(route('customers.index', ['status' => 'vip']))->assertDontSee('Khách số 1<');
        $this->get(route('customers.index'))->assertSee('trên tổng 20');
        $this->get(route('customers.index', ['sort' => 'name; DROP TABLE users', 'direction' => 'asc']))->assertOk(); // cột sắp xếp không hợp lệ bị bỏ qua
    }

    public function test_csv_import(): void
    {
        $this->actingAs($this->admin());
        $this->customer(['email' => 'da-co@example.com']);
        $csv = "name,phone,email,type,source,status\n"
            ."Nguyễn Văn A,0911111111,a@example.com,individual,Website,active\n"
            ."Trùng Email,0922222222,da-co@example.com,individual,,active\n"
            .",0933333333,khong-ten@example.com,individual,,active\n"
            ."Công ty B,abc,email-sai,individual,,active\n";
        $file = UploadedFile::fake()->createWithContent('kh.csv', $csv);

        $this->post(route('customers.import.store'), ['file' => $file])->assertRedirect(route('customers.import'));
        $this->assertDatabaseHas('customers', ['name' => 'Nguyễn Văn A', 'email' => 'a@example.com']);
        $this->assertDatabaseMissing('customers', ['name' => 'Trùng Email']);
        $this->assertSame(1, session('import_result')['created']);
    }

    public function test_interactions_notes(): void
    {
        $this->actingAs($this->admin());
        $c = $this->customer();
        $this->post(route('interactions.store', ['customer', $c->id]), ['type' => 'call', 'content' => 'Gọi tư vấn gói Pro'])->assertSessionHas('success');
        $this->get(route('customers.show', $c))->assertSee('Gọi tư vấn gói Pro');
    }

    public function test_xss_is_escaped(): void
    {
        $this->actingAs($this->admin());
        $c = $this->customer(['name' => '<script>alert(1)</script>']);
        $this->get(route('customers.show', $c))->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
