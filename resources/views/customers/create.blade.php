@extends('layouts.app')
@section('title', __('Thêm khách hàng'))
@section('content')
    <x-page-header :title="__('Thêm khách hàng')" :breadcrumbs="['Khách hàng' => route('customers.index'), 'Thêm mới' => null]" />
    <form method="POST" action="{{ route('customers.store') }}" class="needs-validation" novalidate>
        @csrf
        @include('customers._form')
    </form>
@endsection
