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
    <div style="height:6px;background:{{ $announcement->type==='danger'?'#dc2626':($announcement->type==='warning'?'#d97706':($announcement->type==='success'?'#16a34a':'#3b82f6')) }};"></div>
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
        
        <div class="prose max-w-none text-sm leading-relaxed mb-6" style="color:#334155;white-space:pre-wrap;">{{ $announcement->content }}</div>
        
        <div class="pt-4 border-t border-gray-100 flex justify-between items-center text-xs text-muted">
            <div>
                <p>กลุ่มเป้าหมาย: คณะ{{ $announcement->target_faculty ?? 'ทุกคน' }}</p>
            </div>
            <div>
                <p>ประกาศโดย: {{ $announcement->creator->full_name }}</p>
            </div>
        </div>
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
