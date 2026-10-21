<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetCategory;
use App\Models\AssetEvent;
use App\Models\Organization;
use App\Models\User;
use App\Services\AssetService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 5.5: danh mục tài sản, mã tự sinh, khấu hao đường thẳng, luân chuyển theo trạng thái, QR, kiểm kê, quyền & cô lập. */
class AssetTest extends TestCase
{
    private function newAsset(User $by, array $data = []): Asset
    {
        $this->actingAs($by)->post(route('assets.store'), $data + ['name' => 'Laptop Dell Latitude', 'purchase_cost' => 36000000, 'purchase_date' => now()->subMonths(10)->toDateString(),
            'depreciation_months' => 36, 'serial_number' => 'SN-001'])->assertSessionHasNoErrors();

        return Asset::latest('id')->firstOrFail();
    }

    public function test_create_with_auto_code_category_default_and_permissions(): void
    {
        $manager = $this->userWithRole('Inventory Manager');
        $this->actingAs($manager)->get(route('assets.create'))->assertOk();
        $this->assertSame(5, AssetCategory::count());                                   // nhóm mặc định được tạo
        $first = $this->newAsset($manager);
        $category = AssetCategory::where('depreciation_months', 60)->firstOrFail();
        $second = $this->newAsset($manager, ['name' => 'Bàn họp', 'asset_category_id' => $category->id, 'depreciation_months' => null]);

        $this->assertSame(['TS-000001', 'TS-000002'], [$first->code, $second->code]);
        $this->assertSame(60, $second->depreciation_months);                            // lấy theo nhóm
        $this->assertSame(['created'], $first->events()->pluck('type')->all());
        $this->assertSame(32, strlen($first->qr_token));
        $this->assertArrayNotHasKey('qr_token', $first->toArray());

        $staff = $this->userWithRole('Employee');
        $this->actingAs($staff)->get(route('assets.index'))->assertForbidden();
        $this->post(route('assets.store'), ['name' => 'X', 'purchase_cost' => 1])->assertForbidden();
        $this->get(route('assets.show', $first))->assertNotFound();
        $this->actingAs($this->userWithRole('Accountant'))->get(route('assets.show', $first))->assertOk()->assertSee('TS-000001');
        $this->post(route('assets.act', [$first, 'assign']), ['target_user_id' => $staff->id])->assertForbidden();
    }

    public function test_straight_line_depreciation(): void
    {
        $service = app(AssetService::class);
        $asset = new Asset(['purchase_cost' => 36000000, 'salvage_value' => 0, 'depreciation_months' => 36, 'purchase_date' => now()->subMonths(10)->toDateString()]);
        $d = $service->depreciation($asset);
        $this->assertSame(['1000000', 10, '10000000', '26000000'], [$d['monthly'], $d['months_used'], $d['accumulated'], $d['book_value']]);

        $asset->purchase_date = now()->subMonths(40);
        $this->assertSame('0', $service->depreciation($asset)['book_value']);            // hết khấu hao, không âm

        $asset->salvage_value = 6000000;
        $asset->purchase_date = now()->subMonths(12);
        $d = $service->depreciation($asset);
        $this->assertSame(['833333', '10000000', '26000000'], [$d['monthly'], $d['accumulated'], $d['book_value']]);   // 30tr / 36 × 12

        $asset->depreciation_months = 0;
        $this->assertSame('36000000', $service->depreciation($asset)['book_value']);
    }

    public function test_movement_state_machine_and_holder_access(): void
    {
        $manager = $this->userWithRole('Inventory Manager');
        $staff = $this->userWithRole('Employee');
        $other = $this->userWithRole('Employee');
        $asset = $this->newAsset($manager);

        $this->post(route('assets.act', [$asset, 'recall']))->assertSessionHasErrors('action');          // chưa cấp phát
        $this->post(route('assets.act', [$asset, 'assign']), ['target_user_id' => $staff->id, 'condition' => 'good'])->assertSessionHas('success');
        $asset->refresh();
        $this->assertSame(['in_use', $staff->id], [$asset->status, $asset->holder_id]);
        $this->assertSame(1, $staff->notifications()->where('data->kind', 'asset_assigned')->count());
        $this->post(route('assets.act', [$asset, 'assign']), ['target_user_id' => $other->id])->assertSessionHasErrors('action');
        $this->delete(route('assets.destroy', $asset))->assertStatus(422);                                // đang sử dụng

        // Người đang giữ xem được tài sản của mình (không thấy giá trị); người khác thì không
        $this->actingAs($staff)->get(route('assets.mine'))->assertOk()->assertSee($asset->code);
        $this->get(route('assets.show', $asset))->assertOk()->assertDontSee(money('36000000'));
        $this->actingAs($other)->get(route('assets.show', $asset))->assertNotFound();

        $this->actingAs($manager)->post(route('assets.act', [$asset, 'recall']), ['condition' => 'fair'])->assertSessionHas('success');
        $this->assertSame(['available', null], [$asset->fresh()->status, $asset->fresh()->holder_id]);
        $this->post(route('assets.act', [$asset, 'borrow']), ['target_user_id' => $other->id])->assertSessionHasErrors('due_back_at');
        $this->post(route('assets.act', [$asset, 'borrow']), ['target_user_id' => $other->id, 'due_back_at' => now()->addDays(3)->toDateString()])->assertSessionHas('success');
        $this->post(route('assets.act', [$asset, 'return']))->assertSessionHas('success');
        $this->post(route('assets.act', [$asset, 'maintenance']), ['note' => 'Thay pin'])->assertSessionHas('success');
        $this->post(route('assets.act', [$asset, 'repaired']), ['cost' => 1500000])->assertSessionHas('success');
        $this->post(route('assets.act', [$asset, 'status']), ['status' => 'broken'])->assertSessionHasErrors('note');
        $this->post(route('assets.act', [$asset, 'status']), ['status' => 'broken', 'note' => 'Rơi vỡ màn hình'])->assertSessionHas('success');
        $this->post(route('assets.act', [$asset, 'dispose']), ['cost' => 2000000, 'note' => 'Bán phế liệu'])->assertSessionHas('success');

        $asset->refresh();
        $this->assertSame(['disposed', '2000000.00'], [$asset->status, $asset->disposal_value]);
        $this->assertNotNull($asset->disposed_at);
        $this->post(route('assets.act', [$asset, 'assign']), ['target_user_id' => $staff->id])->assertSessionHasErrors('action');
        $this->assertSame(['created', 'assigned', 'recalled', 'borrowed', 'returned', 'maintenance', 'repaired', 'status', 'disposed'],
            AssetEvent::where('asset_id', $asset->id)->orderBy('id')->pluck('type')->all());
        $this->get(route('assets.overview'))->assertOk()->assertSee(money('1500000'));                  // chi phí bảo trì năm nay

        $this->expectException(\LogicException::class);
        AssetEvent::first()->update(['note' => 'sửa lịch sử']);
    }

    public function test_qr_scan_labels_and_png(): void
    {
        $manager = $this->userWithRole('Inventory Manager');
        $staff = $this->userWithRole('Employee');
        $asset = $this->newAsset($manager);

        $this->get(route('assets.scan', $asset->qr_token))->assertRedirect(route('assets.show', $asset));
        $this->get('/a/'.str_repeat('x', 32))->assertNotFound();
        $this->get(route('assets.qr', $asset))->assertOk()->assertHeader('content-type', 'image/png');
        $this->post(route('assets.labels'), ['ids' => [$asset->id]])->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->post(route('assets.labels'))->assertOk();                                                   // không chọn = tất cả

        $this->actingAs($staff)->get(route('assets.scan', $asset->qr_token))->assertNotFound();
        $this->get(route('assets.qr', $asset))->assertNotFound();
        $this->post(route('assets.labels'), ['ids' => [$asset->id]])->assertForbidden();
        $this->actingAs($manager)->post(route('assets.act', [$asset, 'assign']), ['target_user_id' => $staff->id]);
        $this->actingAs($staff)->get(route('assets.scan', $asset->qr_token))->assertRedirect(route('assets.show', $asset));
    }

    public function test_inventory_audit_and_isolation(): void
    {
        $manager = $this->userWithRole('Inventory Manager');
        $a1 = $this->newAsset($manager);
        $a2 = $this->newAsset($manager, ['name' => 'Máy in']);
        $a3 = $this->newAsset($manager, ['name' => 'Máy chiếu']);

        $this->post(route('assets.audits.store'), ['name' => 'Kiểm kê quý 4'])->assertRedirect();
        $audit = AssetAudit::firstOrFail();
        $this->get(route('assets.show', $a1))->assertSee(__('Kiểm kê nhanh — :a', ['a' => 'Kiểm kê quý 4']));
        $this->post(route('assets.audits.check', $audit), ['asset_id' => $a1->id, 'result' => 'found'])->assertSessionHas('success');
        $this->post(route('assets.audits.check', $audit), ['asset_id' => $a2->id, 'result' => 'damaged', 'note' => 'Kẹt giấy'])->assertSessionHas('success');
        $this->get(route('assets.audits.show', $audit))->assertOk()->assertSee($a3->code)->assertSee('Kẹt giấy');

        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $assetB = app(CurrentOrganization::class)->run($orgB->id, fn () => app(AssetService::class)->create(['name' => 'Tài sản bí mật B', 'purchase_cost' => 1], $manager));
        $this->post(route('assets.audits.check', $audit), ['asset_id' => $assetB->id, 'result' => 'found'])->assertNotFound();
        $this->get(route('assets.show', $assetB))->assertNotFound();
        $this->get(route('assets.scan', $assetB->qr_token))->assertNotFound();
        $this->get(route('assets.index'))->assertDontSee('Tài sản bí mật B');

        $this->post(route('assets.audits.close', $audit))->assertSessionHas('success');
        $this->assertSame(['damaged' => 1, 'found' => 1, 'missing' => 1], $audit->items()->get()->countBy('result')->sortKeys()->all());
        $this->assertSame('missing', $audit->items()->where('asset_id', $a3->id)->value('result'));
        $this->post(route('assets.audits.check', $audit), ['asset_id' => $a3->id, 'result' => 'found'])->assertStatus(422);
        $this->assertSame(['created', 'checked'], AssetEvent::where('asset_id', $a2->id)->orderBy('id')->pluck('type')->all());
    }
}
