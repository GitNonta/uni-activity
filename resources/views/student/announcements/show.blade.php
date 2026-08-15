@extends('layouts.app')
@section('title', $announcement->title)

@section('content')
<div class="mb-4">
    <a href="{{ route('announcements.index') }}" class="text-sm text-primary flex items-center gap-1">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        กลับไปรายการประกาศ
    </a>
</div>

<div class="card overflow-hidden">
    <div style="height:6px;background:{{ $announcement->type==='danger'?'#dc2626':($announcement->type==='warning'?'#d97706':($announcement->type==='success'?'#16a34a':'#ea580c')) }};"></div>
    <div class="card-body p-5">
        <div class="flex flex-wrap items-center gap-2 mb-3">
             <span class="text-xs font-semi {{ $announcement->type==='danger'?'text-red-700':($announcement->type==='warning'?'text-yellow-700':($announcement->type==='success'?'text-green-700':'text-blue-700')) }}">
                ★ {{ ucfirst($announcement->type) }}
            </span>
            <span class="text-xs text-muted">|</span>
            <span class="text-xs text-muted">{{ $announcement->created_at->format('d/m/Y H:i') }}</span>
        </div>
        
        <h1 class="font-bold text-xl mb-4" style="color:#0f172a;line-height:1.4;">{{ $announcement->title }}</h1>

        @if($announcement->image_path)
            <div class="mb-5 rounded-lg overflow-hidden" style="border:1px solid #f1f5f9;background:#f8fafc;">
                <img src="{{ Storage::url($announcement->image_path) }}" alt="{{ $announcement->title }}" class="w-full h-auto" style="max-height:500px;object-fit:contain;display:block;margin:0 auto; cursor:zoom-in;" onclick="openImageModal(this.src || this.dataset.src)">
            </div>
        @endif
        
        <div class="prose max-w-none text-sm leading-relaxed mb-4" style="color:#334155;white-space:pre-wrap;">{{ $announcement->content }}</div>
        
        {{-- บล็อกผู้สร้างประกาศ สไตล์ YouTube --}}
        <div>
            <x-creator-badge :creator="$announcement->creator" default-role="ผู้ลงประกาศข่าวสาร" />
        </div>
        
        <div class="pt-4 border-t border-gray-100 flex justify-between items-center text-xs text-muted">
            <div>
                <p>กลุ่มเป้าหมาย: คณะ{{ $announcement->target_faculty ?? 'ทุกคน' }}</p>
            </div>
            <div>
                <p>ประกาศโดย: {{ $announcement->creator->full_name }}</p>
            </div>
        </div>

        @guest
        <div style="background:#fff7ed; border:1.5px solid #fdba74; border-radius:12px; padding:16px; margin-top:16px; text-align:center; box-shadow:0 2px 8px rgba(234,88,12,0.08);">
            <div class="flex items-center justify-center gap-2 mb-2" style="color:#c2410c; font-weight:700; font-size:.95rem;">
                <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                เข้าสู่ระบบเพื่อเข้าร่วมกิจกรรมและรับการแจ้งเตือน
            </div>
            <p class="text-xs text-muted mb-3" style="color:#9a3412;">
                เข้าสู่ระบบด้วยรหัสนักศึกษาเพื่อรับการแจ้งเตือนข่าวสารสำคัญและลงทะเบียนกิจกรรมที่เกี่ยวข้อง
            </p>
            <a href="{{ route('login') }}" class="btn btn-primary btn-block btn-lg" style="text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-weight:700; border-radius:10px; width: 100%; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25);">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                เข้าสู่ระบบทันที
            </a>
            <p class="text-xs mt-3" style="margin-bottom: 0; color:#9a3412;">
                ยังไม่มีบัญชีนักศึกษา? <a href="{{ route('register') }}" style="color:#ea580c; text-decoration:underline; font-weight:600;">สมัครสมาชิกที่นี่</a>
            </p>
        </div>
        @endguest
    </div>
</div>
{{-- Image Modal --}}
<div id="imageModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.85); justify-content:center; align-items:center; flex-direction:column;" onclick="closeImageModal()">
    <span style="position:absolute; top:15px; right:25px; color:#fff; font-size:35px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="modalImg" style="max-width:95%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.5); cursor:zoom-out;" onclick="event.stopPropagation(); closeImageModal()">
</div>
<script>
    function openImageModal(src) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImg');
        modal.style.display = 'flex';
        modalImg.src = src;
        document.body.style.overflow = 'hidden';
    }
    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
        document.body.style.overflow = '';
    }
</script>
@endsection
