@props(['name', 'show' => false, 'maxWidth' => '2xl'])

@php
    $maxWidthClass = [
        'sm' => 'max-width:24rem;',
        'md' => 'max-width:28rem;',
        'lg' => 'max-width:32rem;',
        'xl' => 'max-width:36rem;',
        '2xl' => 'max-width:42rem;',
    ][$maxWidth] ?? 'max-width:42rem;';
@endphp

<div
    x-data="{ show: @js($show) }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' ? show = true : null"
    x-on:close.window="show = false"
    x-show="show"
    style="display:none;position:fixed;inset:0;z-index:5000;background:rgba(0,0,0,.45);padding:1rem;"
    {{ $attributes }}
>
    <div style="min-height:100%;display:flex;align-items:center;justify-content:center;">
        <div class="modal" style="width:100%;{{ $maxWidthClass }}">
            {{ $slot }}
        </div>
    </div>
</div>
