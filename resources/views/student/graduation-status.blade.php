{{-- หน้าตรวจสอบสถานะความพร้อมจบการศึกษาและ Official Transcript ฝั่งนักศึกษา --}}
@extends('layouts.app')
@section('title', 'ตรวจสอบสถานะการสำเร็จการศึกษา (Graduation Status)')

@section('content')
<div style="max-width:850px;margin:2rem auto;padding:0 1rem;">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="font-bold flex items-center gap-2" style="font-size:1.35rem;color:#0f172a;">
                <span style="display:inline-flex;padding:0.4rem;background:#e0e7ff;border-radius:0.5rem;color:#4338ca;">
                    <svg style="width:1.35rem;height:1.35rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                </span>
                <span>สถานะกิจกรรมเพื่อการสำเร็จการศึกษา</span>
            </h1>
            <p class="text-xs text-muted mt-1">เกณฑ์ชั่วโมงกิจกรรมตามโครงสร้างหลักสูตรและตรวจสอบการส่งรายชื่อฝ่ายทะเบียน</p>
        </div>
        <div>
            <a href="{{ route('student.graduation.transcript') }}" target="_blank" class="btn btn-primary btn-sm flex items-center gap-1" style="padding:0.5rem 1rem;">
                <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>พิมพ์ Official Transcript</span>
            </a>
        </div>
    </div>

    {{-- Banner สถานะผลการตรวจสอบ --}}
    @if($audit['is_eligible'])
        <div style="background:#ecfdf5;border:1.5px solid #10b981;border-radius:0.75rem;padding:1.25rem 1.5rem;margin-bottom:1.5rem;" class="flex items-start gap-3">
            <div style="width:2.5rem;height:2.5rem;border-radius:50%;background:#d1fae5;color:#059669;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg style="width:1.5rem;height:1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
                <h3 class="font-bold text-dark" style="font-size:1.1rem;color:#065f46;">ยินดีด้วย! คุณผ่านเกณฑ์กิจกรรมเพื่อการสำเร็จการศึกษาแล้ว</h3>
                <p class="text-xs mt-1" style="color:#047857;line-height:1.4;">
                    คุณสะสมชั่วโมงกิจกรรมครบถ้วนตามโครงสร้างหลักสูตรและข้อบังคับมหาวิทยาลัย (รวม {{ number_format($audit['total_hours'], 1) }} / {{ number_format($audit['min_total_hours'], 0) }} ชม.) ข้อมูลของคุณพร้อมสำหรับการประมวลผลสำเร็จการศึกษาจากฝ่ายทะเบียน
                </p>
            </div>
        </div>
    @else
        <div style="background:#fef2f2;border:1.5px solid #ef4444;border-radius:0.75rem;padding:1.25rem 1.5rem;margin-bottom:1.5rem;" class="flex items-start gap-3">
            <div style="width:2.5rem;height:2.5rem;border-radius:50%;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg style="width:1.5rem;height:1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div>
                <h3 class="font-bold" style="font-size:1.1rem;color:#991b1b;">คุณยังไม่ผ่านเกณฑ์ชั่วโมงกิจกรรมเพื่อการสำเร็จการศึกษา</h3>
                <p class="text-xs mt-1" style="color:#b91c1c;line-height:1.4;">
                    ขณะนี้คุณมีชั่วโมงสะสม {{ number_format($audit['total_hours'], 1) }} ชม. จากเกณฑ์ขั้นต่ำ {{ number_format($audit['min_total_hours'], 0) }} ชม. และยังขาดคุณสมบัติต่อไปนี้:
                </p>
                <ul class="text-xs mt-2" style="color:#b91c1c;padding-left:1.25rem;">
                    @foreach($audit['missing_requirements'] as $missing)
                        <li>{{ $missing }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ตารางตรวจสอบรายหมวดหมู่ตามโครงสร้าง --}}
    <div class="card mb-4" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.5rem;">
        <h3 class="font-bold text-dark text-sm mb-3 flex items-center gap-2">
            <svg style="width:1.1rem;height:1.1rem;color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            <span>รายละเอียดความคืบหน้ารายหมวดหมู่ (Curriculum Progress)</span>
        </h3>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            {{-- ขอบเขตมหาวิทยาลัย --}}
            @if(isset($audit['scope_audit']['university']))
                @php $uni = $audit['scope_audit']['university']; @endphp
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem;">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-sm text-dark">{{ $uni['name'] }}</span>
                        <span class="badge {{ $uni['passed'] ? 'badge-green' : 'badge-red' }}" style="font-size:0.75rem;">
                            {{ $uni['passed'] ? 'ครบเกณฑ์' : 'ขาด ' . number_format($uni['deficit'], 1) . ' ชม.' }}
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="flex:1;background:#e2e8f0;height:8px;border-radius:4px;overflow:hidden;">
                            @php $pct = min(100, round(($uni['earned'] / max(1, $uni['required'])) * 100)); @endphp
                            <div style="width:{{ $pct }}%;background:{{ $uni['passed'] ? '#10b981' : '#ef4444' }};height:100%;"></div>
                        </div>
                        <span class="text-xs font-semibold text-muted">{{ number_format($uni['earned'], 1) }} / {{ number_format($uni['required'], 0) }} ชม.</span>
                    </div>
                </div>
            @endif

            {{-- ขอบเขตคณะ --}}
            @if(isset($audit['scope_audit']['faculty']))
                @php $fac = $audit['scope_audit']['faculty']; @endphp
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem;">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-sm text-dark">{{ $fac['name'] }}</span>
                        <span class="badge {{ $fac['passed'] ? 'badge-green' : 'badge-red' }}" style="font-size:0.75rem;">
                            {{ $fac['passed'] ? 'ครบเกณฑ์' : 'ขาด ' . number_format($fac['deficit'], 1) . ' ชม.' }}
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="flex:1;background:#e2e8f0;height:8px;border-radius:4px;overflow:hidden;">
                            @php $pct = min(100, round(($fac['earned'] / max(1, $fac['required'])) * 100)); @endphp
                            <div style="width:{{ $pct }}%;background:{{ $fac['passed'] ? '#10b981' : '#ef4444' }};height:100%;"></div>
                        </div>
                        <span class="text-xs font-semibold text-muted">{{ number_format($fac['earned'], 1) }} / {{ number_format($fac['required'], 0) }} ชม.</span>
                    </div>
                </div>
            @endif

            {{-- จิตอาสา --}}
            @foreach($audit['category_audit'] as $cat)
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem;">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-sm text-dark">{{ $cat['name'] }}</span>
                        <span class="badge {{ $cat['passed'] ? 'badge-green' : 'badge-red' }}" style="font-size:0.75rem;">
                            {{ $cat['passed'] ? 'ครบเกณฑ์' : 'ขาด ' . number_format($cat['deficit'], 1) . ' ชม.' }}
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="flex:1;background:#e2e8f0;height:8px;border-radius:4px;overflow:hidden;">
                            @php $pct = min(100, round(($cat['earned'] / max(1, $cat['required'])) * 100)); @endphp
                            <div style="width:{{ $pct }}%;background:{{ $cat['passed'] ? '#10b981' : '#ef4444' }};height:100%;"></div>
                        </div>
                        <span class="text-xs font-semibold text-muted">{{ number_format($cat['earned'], 1) }} / {{ number_format($cat['required'], 0) }} ชม.</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
