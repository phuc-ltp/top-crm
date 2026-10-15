@extends('layouts.app')
@section('title', $product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')
@section('content')
    <x-page-header :title="$product->exists ? 'Sửa: '.$product->name : 'Thêm sản phẩm / dịch vụ'" :breadcrumbs="['Sản phẩm' => route('products.index'), ($product->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]" />
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
        @csrf @if ($product->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8"><div class="card"><div class="card-body"><div class="row">
                <x-input name="sku" label="Mã SKU" :value="$product->sku" required maxlength="50" pattern="[A-Za-z0-9\-_.]+" col="col-md-4 mb-3" />
                <x-input name="name" label="Tên sản phẩm" :value="$product->name" required maxlength="255" col="col-md-8 mb-3" />
                <x-select name="product_category_id" label="Danh mục" :options="$categories" :value="$product->product_category_id" placeholder="— Chọn danh mục —" col="col-md-6 mb-3" />
                <x-select name="status" label="Trạng thái" :options="App\Models\Product::STATUSES" :value="$product->status" required col="col-md-6 mb-3" />
                <x-input name="price" type="number" label="Đơn giá (₫)" :value="$product->exists ? (int) $product->price : null" required min="0" step="1000" col="col-md-6 mb-3" />
                <x-input name="unit" label="Đơn vị tính" :value="$product->unit" required maxlength="30" col="col-md-6 mb-3" />
                <x-textarea name="description" label="Mô tả" :value="$product->description" rows="5" maxlength="5000" col="col-12 mb-0" />
            </div></div></div></div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">Hình ảnh</div><div class="card-body">
                    @if ($product->image_url)<img src="{{ $product->image_url }}" alt="" class="img-fluid rounded mb-3">
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image"><label class="form-check-label" for="remove_image">Xóa ảnh hiện tại</label></div>@endif
                    <x-input name="image" type="file" accept="image/png,image/jpeg,image/webp" help="JPG, PNG, WEBP — tối đa 2MB." />
                </div></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>Lưu</button>
                    <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn btn-light">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection
