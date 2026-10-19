@include('errors.partials.activity', [
    'code' => '500',
    'title' => 'เชื่อมต่อฐานข้อมูลไม่ได้',
    'message' => 'ระบบไม่สามารถเชื่อมต่อกับฐานข้อมูลได้ในขณะนี้ ทีมงานกำลังดูแลอยู่ กรุณาลองใหม่อีกครั้งในอีกสักครู่',
])

@isset($details)
@php($detailText = config('app.debug') ? $details : null)
@if(! empty($detailText))
<div style="max-width:720px;margin:1.5rem auto;padding:0 1.5rem;color:var(--text-muted,#9ca3af);font-size:0.9rem;word-break:break-all;">
    {{ $detailText }}
</div>
@endif
@endisset
