@extends('layouts.admin')
@section('title', 'API Keys')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="font-bold" style="font-size:1.5rem;">ระบบจัดการ API Key</h1>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4" style="background:#dcfce7;color:#166534;padding:1rem;border-radius:8px;border:1px solid #bbf7d0;">
        {{ session('success') }}
    </div>
@endif

@if(session('new_token'))
    <div class="alert alert-warning mb-6" style="background:#fef3c7;color:#92400e;padding:1.5rem;border-radius:8px;border:1px solid #fde68a;">
        <h3 class="font-bold mb-2">สร้าง API Key สำเร็จ!</h3>
        <p class="mb-3">กรุณาคัดลอก API Key ด้านล่างนี้และเก็บไว้ในที่ปลอดภัย เพราะระบบจะไม่แสดงรหัสนี้อีกครั้ง:</p>
        <div class="bg-white p-3 rounded border border-yellow-300 font-mono text-sm break-all flex items-center justify-between">
            <span id="newTokenText">{{ session('new_token') }}</span>
            <button onclick="copyToken()" class="btn btn-sm" style="background:#f59e0b;color:white;border:none;padding:0.4rem 0.8rem;border-radius:4px;">คัดลอก</button>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    {{-- Form สร้าง API Key --}}
    <div class="col-span-1">
        <div class="card p-5">
            <h2 class="font-bold mb-4" style="font-size:1.1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">สร้าง API Key ใหม่</h2>
            <form action="{{ route('admin.api-keys.store') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อของ Key (ระบุวัตถุประสงค์)</label>
                    <input type="text" name="name" class="form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="เช่น For Mobile App" required>
                    @error('name')
                        <p class="text-red-500 text-xs mt-1" style="color:#ef4444;">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary w-full" style="background:#4f46e5;color:white;padding:0.6rem;border-radius:6px;font-weight:600;border:none;width:100%;">
                    สร้าง API Key
                </button>
            </form>
        </div>
    </div>

    {{-- รายการ API Keys --}}
    <div class="col-span-2">
        <div class="card p-5">
            <h2 class="font-bold mb-4" style="font-size:1.1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">API Keys ของคุณ</h2>
            
            @if($tokens->isEmpty())
                <p class="text-gray-500 text-center py-4">ยังไม่มี API Key</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%; text-align:left; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:2px solid #e2e8f0; color:#475569; font-size:0.875rem;">
                                <th style="padding:0.75rem 0;">ชื่อ / วัตถุประสงค์</th>
                                <th style="padding:0.75rem 0;">ใช้งานล่าสุด</th>
                                <th style="padding:0.75rem 0;">สร้างเมื่อ</th>
                                <th style="padding:0.75rem 0; text-align:right;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tokens as $token)
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:0.75rem 0; font-weight:500;">{{ $token->name }}</td>
                                    <td style="padding:0.75rem 0; color:#64748b; font-size:0.875rem;">
                                        {{ $token->last_used_at ? $token->last_used_at->format('d/m/Y H:i') : 'ยังไม่เคยใช้งาน' }}
                                    </td>
                                    <td style="padding:0.75rem 0; color:#64748b; font-size:0.875rem;">
                                        {{ $token->created_at->format('d/m/Y') }}
                                    </td>
                                    <td style="padding:0.75rem 0; text-align:right;">
                                        <form action="{{ route('admin.api-keys.destroy', $token->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบ API Key นี้? หากลบแล้วแอปพลิเคชันที่ใช้ Key นี้จะไม่สามารถเชื่อมต่อได้อีก')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background:#fee2e2; color:#ef4444; border:none; padding:0.4rem 0.8rem; border-radius:4px; font-size:0.8rem; cursor:pointer;">
                                                ยกเลิก (Revoke)
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function copyToken() {
        const tokenText = document.getElementById('newTokenText').innerText;
        navigator.clipboard.writeText(tokenText).then(() => {
            alert('คัดลอก API Key สำเร็จ!');
        });
    }
</script>
@endsection
