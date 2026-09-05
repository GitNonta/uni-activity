{{--
    Component: empty-state
    Props:
      - $icon      (string) — SVG path data (ค่า d attribute ใน <path>), optional
      - $iconRaw   (string) — Full SVG string ถ้าต้องการ SVG แบบ custom, optional
      - $title     (string) — หัวข้อ (required)
      - $description (string) — คำอธิบาย, optional
      - $actionLabel (string) — ชื่อปุ่ม CTA, optional
      - $actionUrl   (string) — URL ของปุ่ม, optional
      - $actionOnclick (string) — JS onclick, optional
      - $size      (string) — 'sm' | 'md' | 'lg', default 'md'
      - $variant   (string) — 'default' | 'card', default 'default'
--}}
@props([
    'icon'         => null,
    'iconRaw'      => null,
    'title'        => '',
    'description'  => null,
    'actionLabel'  => null,
    'actionUrl'    => null,
    'actionOnclick' => null,
    'size'         => 'md',
    'variant'      => 'default',
])

@php
    $sizeClass = match($size) {
        'sm' => 'empty-state-sm',
        'lg' => 'empty-state-lg',
        default => 'empty-state-md',
    };
    $variantClass = $variant === 'card' ? 'empty-state-card' : '';
@endphp

<div class="empty-state {{ $sizeClass }} {{ $variantClass }}">
    {{-- SVG Illustration --}}
    @if($iconRaw)
        <div class="empty-state-icon">
            {!! $iconRaw !!}
        </div>
    @elseif($icon)
        <div class="empty-state-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}"/>
            </svg>
        </div>
    @else
        {{-- Default: document illustration --}}
        <div class="empty-state-icon">
            <svg fill="none" viewBox="0 0 80 80" aria-hidden="true">
                <circle cx="40" cy="40" r="38" fill="currentColor" class="empty-state-circle"/>
                <path d="M28 20h16l10 10v28a2 2 0 01-2 2H28a2 2 0 01-2-2V22a2 2 0 012-2z" fill="white" fill-opacity="0.15" stroke="white" stroke-opacity="0.5" stroke-width="1.5"/>
                <path d="M44 20v10h10" fill="none" stroke="white" stroke-opacity="0.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="32" y1="36" x2="48" y2="36" stroke="white" stroke-opacity="0.6" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="32" y1="42" x2="48" y2="42" stroke="white" stroke-opacity="0.6" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="32" y1="48" x2="40" y2="48" stroke="white" stroke-opacity="0.6" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </div>
    @endif

    {{-- Text Content --}}
    <div class="empty-state-body">
        @if($title)
            <p class="empty-state-title">{{ $title }}</p>
        @endif
        @if($description)
            <p class="empty-state-desc">{{ $description }}</p>
        @endif
    </div>

    {{-- CTA Button --}}
    @if($actionLabel && ($actionUrl || $actionOnclick))
        @if($actionUrl)
            <a href="{{ $actionUrl }}" class="btn btn-primary btn-sm empty-state-cta">
                {{ $actionLabel }}
            </a>
        @else
            <button type="button" onclick="{{ $actionOnclick }}" class="btn btn-primary btn-sm empty-state-cta">
                {{ $actionLabel }}
            </button>
        @endif
    @endif

    {{-- Slot สำหรับ custom content เพิ่มเติม --}}
    {{ $slot }}
</div>
