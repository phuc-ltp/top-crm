@extends('layouts.app')
@section('title', 'Thêm khách hàng')
@section('content')
    <x-page-header title="Thêm khách hàng" :breadcrumbs="['Khách hàng' => route('customers.index'), 'Thêm mới' => null]" />
    <form method="POST" action="{{ route('customers.store') }}" class="needs-validation" novalidate>
        @csrf
        @include('customers._form')
    </form>
@endsection
