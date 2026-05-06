{{-- หน้ารายการ Feedback ทั้งหมด (Admin) --}}
@extends('layouts.admin')
@section('title', 'การประเมินกิจกรรม')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.5rem;">การประเมินกิจกรรม</h1>
    <div style="display:flex;gap:.5rem;">
        <span class="badge" style="background:#e0f2fe;color:#0369a1;padding:6px 12px;">ทั้งหมด {{ number_format($stats['total']) }}</span>
        <span class="badge" style="background:#fef3c7;color:#d97706;padding:6px 12px;">
            เฉลี่ย {{ $stats['average'] ?? '-' }} 
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-left: 2px; vertical-align: middle;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
        </span>
    </div>
</div>

{{-- สถิติคะแนน --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.75rem;margin-bottom:1.25rem;">
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#16a34a;">{{ $stats['rating_5'] }}</p>
            <p class="text-xs text-muted">
                5 
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 12px; height: 12px; margin-left: 2px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </p>
        </div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#84cc16;">{{ $stats['rating_4'] }}</p>
            <p class="text-xs text-muted">
                4 
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 12px; height: 12px; margin-left: 2px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </p>
        </div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#eab308;">{{ $stats['rating_3'] }}</p>
            <p class="text-xs text-muted">
                3 
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 12px; height: 12px; margin-left: 2px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </p>
        </div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#f97316;">{{ $stats['rating_2'] }}</p>
            <p class="text-xs text-muted">
                2 
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 12px; height: 12px; margin-left: 2px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </p>
        </div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#dc2626;">{{ $stats['rating_1'] }}</p>
            <p class="text-xs text-muted">
                1 
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 12px; height: 12px; margin-left: 2px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </p>
        </div>
    </div>
</div>

{{-- ตัวกรอง --}}
<form method="GET" action="{{ route('admin.feedbacks.index') }}" class="card mb-4">
    <div class="card-body" style="padding:.75rem 1rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;">
            <div style="flex:1;min-width:180px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">ค้นหา</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="ความคิดเห็น..." class="form-control" style="font-size:.85rem;">
            </div>
            <div style="min-width:200px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">กิจกรรม</label>
                <select name="activity_id" class="form-control" style="font-size:.85rem;">
                    <option value="">ทั้งหมด</option>
                    @foreach($activities as $act)
                        <option value="{{ $act->id }}" {{ request('activity_id') == $act->id ? 'selected' : '' }}>
                            {{ Str::limit($act->title, 40) }} ({{ $act->activity_date->format('d/m/y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:100px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">คะแนน</label>
                <select name="rating" class="form-control" style="font-size:.85rem;">
                    <option value="">ทั้งหมด</option>
                    @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ request('rating') == $i ? 'selected' : '' }}>{{ $i }} คะแนน</option>
                    @endfor
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="font-size:.85rem;padding:6px 16px;">กรอง</button>
            <a href="{{ route('admin.feedbacks.index') }}" class="btn btn-outline" style="font-size:.85rem;padding:6px 12px;">ล้าง</a>
        </div>
    </div>
</form>

{{-- ตาราง Feedback --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table class="admin-table" style="width:100%;font-size:.85rem;">
            <thead>
                <tr>
                    <th style="width:180px;">กิจกรรม</th>
                    <th style="width:120px;">ผู้ประเมิน</th>
                    <th style="width:80px;">คะแนน</th>
                    <th>ความคิดเห็น</th>
                    <th style="width:120px;">วันที่</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($feedbacks as $fb)
                <tr>
                    <td>
                        <a href="{{ route('admin.feedbacks.show', $fb->activity_id) }}" class="font-semi" style="color:#4f46e5;">
                            {{ Str::limit($fb->activity->title, 30) }}
                        </a>
                    </td>
                    <td>
                        @if($fb->is_anonymous)
                            <span style="color:#94a3b8;font-style:italic;">ไม่ระบุตัวตน</span>
                        @else
                            <span class="font-semi">{{ $fb->user->full_name ?? '-' }}</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:2px;">
                            @for($j = 1; $j <= $fb->rating; $j++)
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; color: #fbbf24;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            @endfor
                            <span style="font-size:0.85rem;margin-left:4px;">({{ $fb->rating }}/5)</span>
                        </div>
                    </td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        {{ $fb->comment ?? '-' }}
                    </td>
                    <td style="font-size:.8rem;color:#64748b;">
                        {{ $fb->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td>
                        <a href="{{ route('admin.feedbacks.show', $fb->activity_id) }}" class="btn btn-sm btn-outline" style="font-size:.7rem;padding:2px 8px;">ดูทั้งหมด</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8;">ยังไม่มีการประเมิน</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pagination --}}
@if($feedbacks->hasPages())
<div style="margin-top:1rem;display:flex;justify-content:center;">
    {{ $feedbacks->links() }}
</div>
@endif
@endsection
