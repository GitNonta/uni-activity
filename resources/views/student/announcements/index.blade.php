@extends('layouts.app')
@section('title', 'ประกาศ/ข่าวสาร')

@section('content')
<div class="hero-card mb-4" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);padding:1.5rem;">
    <h1 class="font-bold text-white mb-1" style="font-size:1.4rem;">ประกาศ/ข่าวสาร</h1>
    <p class="text-white opacity-75 text-sm">อัปเดตข้อมูลข่าวสารล่าสุดจากแอดมิน</p>
</div>

<div class="space-y-3">
    @forelse($announcements as $item)
    <a href="{{ route('announcements.show', $item->id) }}" class="card mb-3 block" style="text-decoration:none;">
        <div class="card-body" style="padding:1rem;">
            <div class="flex gap-3">
                <div style="width:4px;background:{{ $item->type==='danger'?'#dc2626':($item->type==='warning'?'#d97706':($item->type==='success'?'#16a34a':'#3b82f6')) }};border-radius:2px;flex-shrink:0;"></div>
                <div style="flex:1;">
                    <div class="flex gap-3">
                        <div style="flex:1;">
                            <div class="flex justify-between items-start mb-1">
                                <h3 class="font-semi text-sm" style="color:#1e293b;">{{ $item->title }}</h3>
                                <span class="text-xs text-muted">{{ $item->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-xs text-muted line-clamp-2" style="margin-bottom:.5rem;">{{ Str::limit(strip_tags($item->content), 100) }}</p>
                        </div>
                        @if($item->image_path)
                            <div style="width:70px;height:70px;flex-shrink:0;border-radius:8px;overflow:hidden;border:1px solid #f1f5f9;">
                                <img src="{{ Storage::url($item->image_path) }}" class="w-full h-full object-cover" loading="lazy">
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        @if($item->target_faculty)
                            <span class="text-xs font-medium" style="color:#7c3aed;background:#f5f3ff;padding:2px 6px;border-radius:4px;">คณะ{{ $item->target_faculty }}</span>
                        @endif
                        <span class="text-xs text-muted">โดย {{ $item->creator->full_name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </a>
    @empty
    <div class="card p-8 text-center text-muted">
        <svg fill="none" class="w-12 h-12 mx-auto mb-2 opacity-20" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        <p>ยังไม่มีประกาศในขณะนี้</p>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $announcements->links() }}</div>
@endsection
