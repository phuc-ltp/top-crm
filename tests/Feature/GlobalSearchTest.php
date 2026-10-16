<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\GlobalSearch;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/**
 * Bước 2.6 — tìm kiếm toàn cục: chỉ trả về dữ liệu người dùng được phép xem trong tổ chức đang làm việc;
 * lịch sử tìm kiếm là riêng tư từng người, từng tổ chức.
 */
class GlobalSearchTest extends TestCase
{
    private function found($response): array
    {
        return collect($response->json('groups'))->keyBy('type')->map(fn ($g) => collect($g['items'])->pluck('title')->all())->all();
    }

    public function test_suggestions_are_grouped_by_type_with_links(): void
    {
        $this->customer(['name' => 'Công ty Hoa Sen']);
        Product::create(['sku' => 'HS-01', 'name' => 'Trà Hoa Sen', 'price' => 1000, 'status' => 'active']);
        Lead::create(['name' => 'Anh Hoa Sen', 'status' => 'new']);

        $res = $this->actingAs($this->admin())->getJson(route('search', ['q' => 'hoa sen']))->assertOk();
        $groups = $this->found($res);
        $this->assertSame(['Công ty Hoa Sen'], $groups['customers']);
        $this->assertSame(['Trà Hoa Sen'], $groups['products']);
        $this->assertSame(['Anh Hoa Sen'], $groups['leads']);
        $this->assertSame(['groups'], array_keys($res->json()));   // không phản hồi lại từ khóa
        $this->assertStringStartsWith(url('/'), $res->json('groups.0.items.0.url'));
    }

    public function test_types_without_permission_are_never_returned(): void
    {
        $marketing = $this->userWithRole('Marketing Employee');   // không có quyền xem cơ hội / nhân viên
        $c = $this->customer(['name' => 'Khách Mẫu XYZ']);
        Opportunity::create(['title' => 'Cơ hội XYZ', 'customer_id' => $c->id, 'pipeline_stage_id' => PipelineStage::first()->id, 'value' => 1]);
        User::factory()->create(['name' => 'Nhân viên XYZ', 'status' => 'active'])->assignRole('Sales Employee');

        $groups = $this->found($this->actingAs($marketing)->getJson(route('search', ['q' => 'XYZ'])));
        $this->assertArrayHasKey('customers', $groups);
        $this->assertArrayNotHasKey('opportunities', $groups);
        $this->assertArrayNotHasKey('employees', $groups);

        // Trang kết quả: không cho chọn loại không có quyền
        $this->get(route('search.results', ['q' => 'XYZ', 'type' => 'opportunities']))->assertSessionHasErrors('type');
        $this->get(route('search.results', ['q' => 'XYZ']))->assertOk()->assertDontSee('Cơ hội XYZ')->assertDontSee('Nhân viên XYZ');

        // Admin thấy cả hai → chứng minh dữ liệu có thật, chỉ bị ẩn theo quyền
        $all = $this->found($this->actingAs($this->admin())->getJson(route('search', ['q' => 'XYZ'])));
        $this->assertSame(['Cơ hội XYZ'], $all['opportunities']);
        $this->assertSame(['Nhân viên XYZ'], $all['employees']);
    }

    public function test_staff_only_find_their_own_records(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $other = $this->userWithRole('Sales Employee');
        $this->customer(['name' => 'Khách Riêng Của Tôi', 'assigned_to' => $sale->id]);
        $this->customer(['name' => 'Khách Riêng Đồng Nghiệp', 'assigned_to' => $other->id]);

        $res = $this->actingAs($sale)->getJson(route('search', ['q' => 'Khách Riêng']));
        $this->assertSame(['Khách Riêng Của Tôi'], $this->found($res)['customers']);
        $this->get(route('search.results', ['q' => 'Khách Riêng']))->assertOk()
            ->assertSee('Khách Riêng Của Tôi')->assertDontSee('Khách Riêng Đồng Nghiệp');
    }

    public function test_other_organizations_are_invisible_including_employees(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);   // không phải quản trị nền tảng
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision($orgB->id);
        app(CurrentOrganization::class)->run($orgB->id, function () {
            User::factory()->create(['name' => 'Nhân viên ZETA', 'status' => 'active'])->assignRole('Sales Employee');
            $this->customer(['name' => 'Khách ZETA']);
            Product::create(['sku' => 'ZETA', 'name' => 'Sản phẩm ZETA', 'price' => 1, 'status' => 'active']);
        });

        $this->actingAs($owner)->getJson(route('search', ['q' => 'ZETA']))->assertOk()->assertJson(['groups' => []]);
        $this->get(route('search.results', ['q' => 'ZETA']))->assertOk()->assertDontSee('Khách ZETA')->assertDontSee('Nhân viên ZETA');
    }

    public function test_phone_is_found_in_any_written_format(): void
    {
        $admin = $this->admin();
        $this->customer(['name' => 'Khách Điện Thoại', 'phone' => '0901234567']);
        Contact::create(['full_name' => 'Liên hệ Có Dấu Cách', 'phone' => '0912 345 678']);
        Lead::create(['name' => 'Lead Quốc Tế', 'status' => 'new', 'phone' => '+84 933.222.111']);

        $find = fn ($q) => collect($this->found($this->actingAs($admin)->getJson(route('search', ['q' => $q]))))->flatten()->all();
        $this->assertContains('Khách Điện Thoại', $find('+84 901 234 567'));
        $this->assertContains('Khách Điện Thoại', $find('0901.234.567'));
        $this->assertContains('Liên hệ Có Dấu Cách', $find('0912345678'));
        $this->assertContains('Lead Quốc Tế', $find('0933222111'));
        $this->assertContains('Lead Quốc Tế', $find('222 111'));
    }

    public function test_like_wildcards_are_escaped(): void
    {
        $this->customer(['name' => 'Khách Thường']);
        $groups = $this->found($this->actingAs($this->admin())->getJson(route('search', ['q' => '%%'])));
        $this->assertSame([], $groups);
        $this->getJson(route('search', ['q' => 'a']))->assertStatus(422);
        $this->getJson(route('search', ['q' => str_repeat('x', 150)]))->assertOk();   // cắt về 100 ký tự, không lỗi
    }

    public function test_results_page_counts_paginates_and_filters_by_type(): void
    {
        $admin = $this->admin();
        foreach (range(1, 23) as $i) {
            $this->customer(['name' => sprintf('Khách Phân Trang %02d', $i)]);
        }
        Lead::create(['name' => 'Lead Phân Trang', 'status' => 'new']);

        $page = $this->actingAs($admin)->get(route('search.results', ['q' => 'Phân Trang']))->assertOk();
        $page->assertSee('Khách Phân Trang 01')->assertDontSee('Khách Phân Trang 23')->assertSee('24 '.__('kết quả'), false);

        $this->get(route('search.results', ['q' => 'Phân Trang', 'page' => 2]))->assertOk()->assertSee('Khách Phân Trang 23');
        $this->get(route('search.results', ['q' => 'Phân Trang', 'type' => 'leads']))->assertOk()
            ->assertSee('Lead Phân Trang')->assertDontSee('Khách Phân Trang 01');
        $this->get(route('search.results'))->assertOk();   // không có từ khóa: chỉ hiện ô tìm
    }

    public function test_recent_searches_are_private_limited_and_clearable(): void
    {
        $me = $this->admin();
        $colleague = $this->userWithRole('Sales Employee');

        $this->actingAs($me);
        foreach (range(1, 12) as $i) {
            $this->travel(1)->seconds();
            $this->get(route('search.results', ['q' => 'từ khóa '.$i]))->assertOk();
        }
        $this->get(route('search', ['q' => 'gõ dở']));   // gợi ý khi gõ không được ghi lịch sử
        $this->get(route('search.results', ['q' => 'từ khóa 12']));   // lặp lại: không tạo bản trùng

        $recent = $this->getJson(route('search'))->assertOk()->json('recent');
        $this->assertCount(GlobalSearch::RECENT_LIMIT, $recent);
        $this->assertSame('từ khóa 12', $recent[0]);
        $this->assertNotContains('từ khóa 1', $recent);
        $this->assertNotContains('gõ dở', $recent);
        $this->assertSame(GlobalSearch::RECENT_LIMIT, SearchHistory::where('user_id', $me->id)->count());

        // Đồng nghiệp không thấy lịch sử của tôi, và không xóa được nó
        $this->actingAs($colleague)->getJson(route('search'))->assertJson(['recent' => []]);
        $this->deleteJson(route('search.recent.clear'))->assertOk();
        $this->assertSame(GlobalSearch::RECENT_LIMIT, SearchHistory::where('user_id', $me->id)->count());

        $this->actingAs($me)->deleteJson(route('search.recent.clear'))->assertOk();
        $this->assertSame(0, SearchHistory::where('user_id', $me->id)->count());
    }

    public function test_recent_searches_are_kept_per_organization(): void
    {
        $owner = $this->admin();
        $orgA = app(CurrentOrganization::class)->id();
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $search = app(GlobalSearch::class);
        $search->remember('bí mật tổ chức B', $owner, $orgB->id);
        $search->remember('việc tổ chức A', $owner, $orgA);

        $this->assertSame(['việc tổ chức A'], $search->recent($owner, $orgA)->all());
        $this->actingAs($owner)->getJson(route('search'))->assertJsonMissing(['bí mật tổ chức B']);
    }

    public function test_guests_cannot_search_and_mobile_has_search_button(): void
    {
        $this->getJson(route('search', ['q' => 'abc']))->assertUnauthorized();
        $this->get(route('search.results', ['q' => 'abc']))->assertRedirect(route('login'));

        $this->actingAs($this->admin())->get(route('dashboard'))->assertOk()
            ->assertSee('data-results-url="'.route('search.results').'"', false)
            ->assertSee('class="topbar-icon d-md-none"', false);
    }
}
