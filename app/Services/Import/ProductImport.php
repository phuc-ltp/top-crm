<?php

namespace App\Services\Import;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Tenancy\TenantRule;
use Illuminate\Validation\Rule;

/** Nhập sản phẩm: mã SKU duy nhất trong tổ chức — SKU đã có thì bỏ qua (không ghi đè sản phẩm cũ). */
class ProductImport extends TableImport
{
    private ?array $categories = null;

    public function noun(): string
    {
        return 'sản phẩm';
    }

    public function aliases(): array
    {
        return [
            'sku' => ['sku', 'mã', 'ma', 'mã sản phẩm', 'ma san pham', 'code'],
            'name' => ['name', 'tên', 'ten', 'tên sản phẩm', 'ten san pham', 'product name'],
            'category' => ['category', 'danh mục', 'danh muc', 'nhóm', 'loại'],
            'price' => ['price', 'giá', 'gia', 'đơn giá', 'don gia', 'giá bán'],
            'unit' => ['unit', 'đơn vị', 'don vi', 'đơn vị tính', 'dvt', 'đvt'],
            'status' => ['status', 'trạng thái', 'trang thai'],
            'description' => ['description', 'mô tả', 'mo ta'],
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => __('Mã SKU (bắt buộc, duy nhất; chỉ chữ, số, - _ .)'),
            'name' => __('Tên sản phẩm (bắt buộc)'),
            'category' => __('Tên danh mục đã có (để trống nếu không có)'),
            'price' => __('Đơn giá (bắt buộc, VD: 1500000 hoặc 1.500.000)'),
            'unit' => __('Đơn vị tính (bắt buộc, VD: Cái, Gói, Giờ)'),
            'status' => __('active (đang kinh doanh) hoặc inactive (ngừng) — để trống: active'),
            'description' => __('Mô tả'),
        ];
    }

    public function sample(): array
    {
        return ['SP-001', 'Gói phần mềm CRM 1 năm', 'Phần mềm', '12000000', 'Gói', 'active', ''];
    }

    protected function build(callable $get, User $actor): array|string
    {
        $this->categories ??= ProductCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $category = mb_strtolower($get('category'));
        if ($category !== '' && ! isset($this->categories[$category])) {
            return __('không có danh mục ":category" trong tổ chức.', ['category' => $get('category')]);
        }
        $status = mb_strtolower($get('status'));
        $status = match (true) {
            $status === '' => 'active',
            in_array($status, ['active', 'đang kinh doanh', 'dang kinh doanh'], true) => 'active',
            in_array($status, ['inactive', 'ngừng kinh doanh', 'ngung kinh doanh', 'ngừng'], true) => 'inactive',
            default => $status,
        };

        return [
            'sku' => $get('sku'),
            'name' => $get('name'),
            'product_category_id' => $this->categories[$category] ?? null,
            'price' => $this->money($get('price')),
            'unit' => $get('unit'),
            'status' => $status,
            'description' => $get('description') ?: null,
        ];
    }

    protected function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.]+$/', TenantRule::unique('products', 'sku')],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'unit' => ['required', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function attributes(): array
    {
        return ['name' => __('Tên sản phẩm'), 'price' => __('Đơn giá'), 'unit' => __('Đơn vị tính')];
    }

    protected function duplicate(array $data): ?string
    {
        return null;   // SKU trùng do quy tắc unique báo (kể cả sản phẩm đã xóa vào thùng rác)
    }

    protected function persist(array $data, User $actor): void
    {
        Product::create($data);
    }
}
