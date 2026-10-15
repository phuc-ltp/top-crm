@props(['user' => null, 'size' => ''])
@if ($user && $user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => 'avatar '.($size ? 'avatar-'.$size : '')]) }}>
@else
    <span {{ $attributes->merge(['class' => 'avatar '.($size ? 'avatar-'.$size : '')]) }} title="{{ $user?->name }}">{{ $user?->initials ?? '?' }}</span>
@endif
