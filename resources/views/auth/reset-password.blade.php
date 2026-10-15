@extends('layouts.auth')
@section('title', 'Đặt lại mật khẩu')
@section('content')
    @include('auth._card-brand')
    <h2>Đặt lại mật khẩu</h2>
    <p class="sub">Mật khẩu tối thiểu 8 ký tự, gồm cả chữ và số.</p>
    <form method="POST" action="{{ route('password.update') }}" class="needs-validation" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-auth-field name="email" type="email" icon="bi-envelope" placeholder="Email" :value="$email" required autocomplete="email" />
        <x-auth-field name="password" type="password" icon="bi-lock" placeholder="Mật khẩu mới" required minlength="8" autocomplete="new-password" />
        <x-auth-field name="password_confirmation" type="password" icon="bi-lock" placeholder="Nhập lại mật khẩu mới" required minlength="8" autocomplete="new-password" />
        <button type="submit" class="ta-btn"><i class="bi bi-check2-circle"></i> Đặt lại mật khẩu</button>
    </form>
@endsection
