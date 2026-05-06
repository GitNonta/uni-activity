{{-- หน้ารายละเอียด Audit Log --}}
@extends('layouts.admin')
@section('title', 'รายละเอียด Log #' . $log->id)

@section('content')
<div class="flex items-center gap-3 mb-4">
    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline btn-sm" style="font-size:.8rem;">&larr; กลับ</a>
    <h1 class="font-bold" style="font-size:1.3rem;">รายละเอียด Log #{{ $log->id }}</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">ผู้ดำเนินการ</p>
                <p class="font-semi">{{ $log->user->full_name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">การกระทำ</p>
                <span class="badge {{ $log->action_color }}">{{ $log->action_label }}</span>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">ประเภท</p>
                <p class="font-semi">{{ $log->model_label }} @if($log->model_id) #{{ $log->model_id }} @endif</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">เวลา</p>
                <p class="font-semi">{{ $log->created_at->format('d/m/Y H:i:s') }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">IP Address</p>
                <p class="font-semi" style="font-size:.85rem;">{{ $log->ip_address ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">User Agent</p>
                <p style="font-size:.75rem;color:#64748b;word-break:break-all;">{{ $log->user_agent ?? '-' }}</p>
            </div>
        </div>

        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0;">
            <p class="text-xs text-muted" style="margin-bottom:.25rem;">คำอธิบาย</p>
            <p class="font-semi" style="font-size:1rem;">{{ $log->description }}</p>
        </div>
    </div>
</div>

{{-- ค่าเดิม --}}
@if($log->old_values)
<div class="card mb-4">
    <div class="card-body">
        <h2 class="font-bold mb-3" style="font-size:1rem;color:#dc2626;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            ค่าเดิม (Before)
        </h2>
        <div style="overflow-x:auto;">
            <table class="admin-table" style="width:100%;font-size:.82rem;">
                <thead>
                    <tr>
                        <th style="width:180px;">ฟิลด์</th>
                        <th>ค่า</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($log->old_values as $key => $value)
                    <tr>
                        <td class="font-semi">{{ $key }}</td>
                        <td style="word-break:break-all;">
                            @if(is_array($value)) {{ json_encode($value, JSON_UNESCAPED_UNICODE) }}
                            @else {{ $value ?? '-' }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ค่าใหม่ --}}
@if($log->new_values)
<div class="card mb-4">
    <div class="card-body">
        <h2 class="font-bold mb-3" style="font-size:1rem;color:#16a34a;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            ค่าใหม่ (After)
        </h2>
        <div style="overflow-x:auto;">
            <table class="admin-table" style="width:100%;font-size:.82rem;">
                <thead>
                    <tr>
                        <th style="width:180px;">ฟิลด์</th>
                        <th>ค่า</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($log->new_values as $key => $value)
                    <tr>
                        <td class="font-semi">{{ $key }}</td>
                        <td style="word-break:break-all;">
                            @if(is_array($value)) {{ json_encode($value, JSON_UNESCAPED_UNICODE) }}
                            @else {{ $value ?? '-' }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- เปรียบเทียบความเปลี่ยนแปลง (ถ้ามีทั้ง old และ new) --}}
@if($log->old_values && $log->new_values)
<div class="card mb-4">
    <div class="card-body">
        <h2 class="font-bold mb-3" style="font-size:1rem;color:#2563eb;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            ความเปลี่ยนแปลง (Diff)
        </h2>
        <div style="overflow-x:auto;">
            <table class="admin-table" style="width:100%;font-size:.82rem;">
                <thead>
                    <tr>
                        <th style="width:180px;">ฟิลด์</th>
                        <th style="background:#fef2f2;">ค่าเดิม</th>
                        <th style="background:#f0fdf4;">ค่าใหม่</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($log->new_values as $key => $newVal)
                        @php $oldVal = $log->old_values[$key] ?? null; @endphp
                        @if($oldVal != $newVal)
                        <tr>
                            <td class="font-semi">{{ $key }}</td>
                            <td style="background:#fef2f2;color:#dc2626;">{{ is_array($oldVal) ? json_encode($oldVal, JSON_UNESCAPED_UNICODE) : ($oldVal ?? '-') }}</td>
                            <td style="background:#f0fdf4;color:#16a34a;">{{ is_array($newVal) ? json_encode($newVal, JSON_UNESCAPED_UNICODE) : ($newVal ?? '-') }}</td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
