@extends('layouts.app')

@section('title', 'ใบรับรองชั่วโมงกิจกรรม (Certificates)')

@section('content')
<div class="container py-4" style="max-width:960px; margin:0 auto;">

    <div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap; gap:1rem;">
        <div>
            <h1 class="h3 font-weight-bold text-gray-800 mb-1" style="font-size:1.5rem; font-weight:800; color:#0f172a;">
                ใบรับรองชั่วโมงกิจกรรม (Certificates)
            </h1>
            <p class="text-muted" style="color:#64748b; font-size:0.9rem; margin:0;">
                ขอรับและดาวน์โหลดใบเกียรติบัตรอิเล็กทรอนิกส์เมื่อสะสมชั่วโมงครบตามเกณฑ์หมวดหมู่
            </p>
        </div>

        <form action="{{ route('student.certificates.claim') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" style="background:#4f46e5; border-color:#4f46e5; font-weight:600; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ขอรับใบรับรองกิจกรรมรวม
            </button>
        </form>
    </div>

    @if(session('success'))
        <div style="background:#dcfce7; border:1px solid #86efac; color:#15803d; padding:0.875rem 1.25rem; border-radius:10px; margin-bottom:1.5rem; display:flex; align-items:center; gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div style="background:#fee2e2; border:1px solid #fca5a5; color:#b91c1c; padding:0.875rem 1.25rem; border-radius:10px; margin-bottom:1.5rem; display:flex; align-items:center; gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Issued Certificates List -->
    <div style="background:#fff; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden; margin-bottom:2rem;">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #f1f5f9; font-weight:700; color:#0f172a; font-size:1.05rem;">
            ใบรับรองที่ได้รับแล้ว
        </div>

        @if($certificates->isEmpty())
            <div style="text-align:center; padding:3rem 1rem; color:#94a3b8;">
                <div style="width:48px; height:48px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 0.75rem auto; color:#64748b;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div style="font-weight:600; color:#475569; margin-bottom:0.25rem;">ยังไม่มีใบรับรองที่ออก</div>
                <div style="font-size:0.85rem;">เมื่อเข้าร่วมกิจกรรมและสะสมชั่วโมงครบตามเกณฑ์ คุณสามารถกดขอรับใบรับรองได้ที่นี่</div>
            </div>
        @else
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem; padding:1.25rem;">
                @foreach($certificates as $cert)
                    <div style="border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:0.5rem;">
                                <span style="font-family:monospace; font-size:0.75rem; background:#e0e7ff; color:#4338ca; font-weight:700; padding:2px 8px; border-radius:4px;">
                                    {{ $cert->certificate_code }}
                                </span>
                                <span style="font-size:0.75rem; color:#64748b;">
                                    {{ $cert->issued_at ? $cert->issued_at->format('d/m/Y') : '' }}
                                </span>
                            </div>
                            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">
                                {{ $cert->title }}
                            </h3>
                            <div style="font-size:0.85rem; color:#059669; font-weight:600; margin-bottom:1rem;">
                                ชั่วโมงที่ผ่านการรับรอง: {{ number_format($cert->hours_completed, 1) }} ชม.
                            </div>
                        </div>

                        <div style="display:flex; gap:0.5rem;">
                            <a href="{{ route('student.certificates.download', $cert->id) }}" class="btn btn-primary btn-sm" style="flex:1; background:#4f46e5; border-color:#4f46e5; border-radius:6px; font-size:0.8rem; text-align:center; padding:0.4rem;">
                                ดาวน์โหลด PDF
                            </a>
                            <a href="{{ route('certificates.verify', $cert->certificate_code) }}" target="_blank" class="btn btn-outline btn-sm" style="border-radius:6px; font-size:0.8rem; padding:0.4rem 0.75rem; background:#fff;">
                                ตรวจสอบ
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Categories Completion List -->
    <div style="background:#fff; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
        <h2 style="font-size:1.05rem; font-weight:700; color:#0f172a; margin-bottom:1rem;">
            เกณฑ์การขอรับใบรับรองรายหมวดหมู่
        </h2>
        <div style="display:grid; gap:0.75rem;">
            @foreach($categories as $cat)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1rem; background:#f8fafc; border-radius:10px; border:1px solid #f1f5f9;">
                    <div>
                        <div style="font-weight:600; color:#0f172a; font-size:0.9rem;">{{ $cat->name }}</div>
                        <div style="font-size:0.8rem; color:#64748b;">เกณฑ์ขั้นต่ำ: {{ $cat->required_hours ?? 0 }} ชั่วโมง</div>
                    </div>
                    <form action="{{ route('student.certificates.claim') }}" method="POST">
                        @csrf
                        <input type="hidden" name="category_id" value="{{ $cat->id }}">
                        <button type="submit" class="btn btn-outline btn-sm" style="border-radius:6px; font-size:0.8rem; background:#fff;">
                            ขอรับใบรับรองหมวดนี้
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
