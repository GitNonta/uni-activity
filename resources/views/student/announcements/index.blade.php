@extends('layouts.app')
@section('title', 'ประกาศ/ข่าวสาร')

@section('content')
<div class="hero-card mb-4" style="background:linear-gradient(135deg,#ef4444,#ea580c);padding:1.5rem;">
    <h1 class="font-bold text-white mb-1" style="font-size:1.4rem;">ประกาศ/ข่าวสาร</h1>
    <p class="text-white opacity-75 text-sm">อัปเดตข้อมูลข่าวสารล่าสุดจากแอดมิน</p>
</div>

<div class="space-y-3">
    @forelse($announcements as $item)
    <a href="{{ route('announcements.show', $item->id) }}" class="card mb-3 block no-linkify" style="text-decoration:none;">
        <div class="card-body" style="padding:1rem;">
            <div class="flex gap-3">
                <div style="width:4px;background:{{ $item->type==='danger'?'#dc2626':($item->type==='warning'?'#d97706':($item->type==='success'?'#16a34a':'#ea580c')) }};border-radius:2px;flex-shrink:0;"></div>
                <div style="flex:1;">
                    <div class="flex gap-3">
                        <div style="flex:1;">
                            <div class="flex justify-between items-start mb-1">
                                <h3 class="font-semi text-sm" style="color:#1e293b;" title="{{ $item->title }}">{{ Str::limit($item->title, 45, '...') }}</h3>
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
                            <span class="text-xs font-medium" style="color:#ef4444;background:#fff5f5;padding:2px 6px;border-radius:4px;">คณะ{{ $item->target_faculty }}</span>
                        @endif
                        <span class="text-xs text-muted">โดย {{ $item->creator->full_name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </a>
    @empty
    <x-empty-state
        icon="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"
        title="ยังไม่มีประกาศในขณะนี้"
        description="เมื่อมีประกาศหรือข่าวสารใหม่จากทีมงาน จะปรากฏที่นี่"
        size="lg"
    />
    @endforelse
</div>

<div class="mt-4">{{ $announcements->links() }}</div>
@endsection
