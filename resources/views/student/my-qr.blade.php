@extends('layouts.app')

@section('title', 'บัตรประจำตัวนักศึกษา')

@section('content')
<div style="max-width: 480px; margin: 2rem auto; padding: 0 1rem;">
    <div style="background: #ffffff; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); overflow: hidden; text-align: center; padding: 2.5rem 1.5rem; border: 1px solid #f1f5f9;">
        
        <h1 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
            <svg width="24" height="24" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            บัตรประจำตัวนักศึกษา
        </h1>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 2rem;">Digital Student ID Card</p>

        <!-- ID Card Container -->
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; padding: 2rem 1.25rem; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4); margin-bottom: 2rem; color: #fff;">
            
            <div style="margin-bottom: 1.5rem; width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 4px solid #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: #fff;">
                @if($user->profile_photo)
                    <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="profile" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                    <div style="width: 100%; height: 100%; background: #e0e7ff; display: flex; align-items: center; justify-content: center; color: #4338ca; font-weight: 700; font-size: 2.5rem;">
                        {{ mb_substr($user->full_name, 0, 1) }}
                    </div>
                @endif
            </div>
            
            <div style="font-weight: 700; font-size: 1.25rem; margin-bottom: 0.25rem;">{{ $user->full_name }}</div>
            <div style="font-family: monospace; font-size: 1.1rem; background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 6px; letter-spacing: 1px; margin-bottom: 1.5rem;">
                {{ $user->student_id }}
            </div>

            <div style="width: 100%; text-align: left; background: rgba(0,0,0,0.15); padding: 1rem; border-radius: 10px; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="opacity: 0.8;">คณะ</span>
                    <span style="font-weight: 600; text-align: right;">{{ $user->faculty ?? '-' }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;">สาขา</span>
                    <span style="font-weight: 600; text-align: right;">{{ $user->department ?? '-' }}</span>
                </div>
            </div>

        </div>

        <div style="margin-top: 1rem;">
            <a href="{{ route('student.profile') }}" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; font-size: 0.9rem; font-weight: 600; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#4f46e5'" onmouseout="this.style.color='#64748b'">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                กลับหน้าโปรไฟล์
            </a>
        </div>
    </div>
</div>
@endsection

