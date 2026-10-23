@props(['href', 'label' => null])

{{-- มาตรฐานปุ่มย้อนกลับของทั้งระบบ: อยู่มุมซ้ายบนของเนื้อหาเสมอ
     ใช้คลาส .page-back ใน public/css/app.css เพื่อให้หน้าตาเหมือนกันทุกหน้า --}}
<a href="{{ $href }}"
   {{ $attributes->merge([
        'class' => 'page-back',
        'title' => $label ?: __('ย้อนกลับ'),
        'aria-label' => $label ?: __('ย้อนกลับ'),
   ]) }}>
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    @if($label)
        <span class="page-back-label">{{ $label }}</span>
    @endif
</a>
