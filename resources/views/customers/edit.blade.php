@extends('layouts.app')
@section('title', 'Sửa khách hàng')
@section('content')
    <x-page-header :title="'Sửa: '.$customer->name" :breadcrumbs="['Khách hàng' => route('customers.index'), $customer->code => route('customers.show', $customer), 'Chỉnh sửa' => null]" />
    <form method="POST" action="{{ route('customers.update', $customer) }}" class="needs-validation" novalidate>
        @csrf @method('PUT')
        @include('customers._form')
    </form>
@endsection
