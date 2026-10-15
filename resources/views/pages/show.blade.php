@extends('layouts.auth')
@section('title', $title)
@section('page')
    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="ta-back"><i class="bi bi-arrow-left"></i> {{ auth()->check() ? __('Về trang tổng quan') : __('Quay lại đăng nhập') }}</a>
    <div class="ta-card">
        <div class="mb-4"><x-brand :size="48" /></div>
        <h1>{{ $title }}</h1>
        <div class="content">
            @if ($custom)
                {!! nl2br(e($custom)) !!}
            @else
                @include($default)
            @endif
        </div>
    </div>
@endsection
