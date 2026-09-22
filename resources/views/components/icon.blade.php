@props([
    'name',
    'size' => 24,
    'strokeWidth' => 2,
])

@php
    $n = strtolower((string)$name);
@endphp

@switch($n)
    @case('activity')
    @case('activities')
    @case('event')
    @case('events')
    @case('calendar')
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        @break

    @case('job')
    @case('jobs')
    @case('parttime')
    @case('work')
    @case('briefcase')
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        @break

    @case('announcement')
    @case('announcements')
    @case('news')
    @case('megaphone')
    @case('speakerphone')
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
        </svg>
        @break

    @case('chat')
    @case('message')
    @case('messages')
    @case('inbox')
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        @break

    @case('map')
    @case('location')
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
        </svg>
        @break

    @case('user')
    @case('profile')
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        @break

    @case('scan')
    @case('scanner')
    @case('camera')
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => 'icon', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }} width="{{ $size }}" height="{{ $size }}">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $strokeWidth }}" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
@endswitch
