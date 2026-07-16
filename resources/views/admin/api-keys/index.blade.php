@extends('layouts.admin')
@section('title', 'API Keys')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="font-bold flex items-center gap-3" style="font-size:1.5rem; color:#1e293b;">
            <svg style="width:28px; height:28px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            จัดการ API Keys
        </h1>
        <p class="text-sm text-muted mt-1">สร้างและลบกุญแจสำหรับการเข้าถึง API ของระบบ เพื่อเชื่อมต่อกับแอปพลิเคชันภายนอกอย่างปลอดภัย</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4" style="background:#dcfce7;color:#166534;padding:1rem;border-radius:8px;border:1px solid #bbf7d0;">
        {{ session('success') }}
    </div>
@endif

@if(session('new_token'))
    <div class="card mb-6" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fde68a; box-shadow: 0 4px 12px rgba(245,158,11,0.05); border-radius: 12px; animation: slideIn 0.3s ease-out;">
        <div class="card-body" style="padding:1.5rem;">
            <div style="display:flex; align-items:start; gap:12px;">
                <div style="background:#f59e0b; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:14px; font-weight:700;">!</div>
                <div style="flex:1;">
                    <h3 class="font-bold mb-1" style="color:#92400e; font-size:1.05rem;">สร้าง API Key สำเร็จ!</h3>
                    <p class="text-sm mb-4" style="color:#b45309; line-height:1.5;">กรุณาคัดลอกและเก็บ API Key ด้านล่างนี้ไว้ในที่ปลอดภัยทันที <strong>ระบบจะไม่แสดงรหัสเต็มนี้ให้เห็นอีกครั้ง:</strong></p>
                    
                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:stretch; max-width:650px;">
                        <code class="font-mono text-xs break-all" id="newTokenText" style="flex:1; background:#fff; padding:10px 14px; border-radius:8px; border:1px solid #fcd34d; display:inline-block; font-weight:700; color:#1e293b; box-shadow:inset 0 1px 2px rgba(0,0,0,0.02);">{{ session('new_token') }}</code>
                        <button onclick="copyToken()" id="copyBtn" class="btn btn-primary" style="background:#d97706; color:white; border:none; padding:0 1.25rem; border-radius:8px; font-weight:600; cursor:pointer; transition:all 0.2s;">
                            คัดลอกคีย์
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; align-items: start;">
    
    {{-- Form สร้าง API Key --}}
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
            <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1.25rem 1.5rem;">
                <h3 class="font-semi flex items-center gap-2" style="font-size:1.05rem; color:#1e293b; margin:0;">
                    <svg style="width:20px; height:20px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    สร้าง API Key ใหม่
                </h3>
            </div>
            <div class="card-body" style="padding:1.5rem;">
                <form action="{{ route('admin.api-keys.store') }}" method="POST">
                    @csrf
                    <div class="mb-5">
                        <label class="form-label" style="font-weight:600; color:#334155; margin-bottom:0.5rem; display:block;">ชื่อ / วัตถุประสงค์ของ Key</label>
                        <input type="text" name="name" class="form-control" style="width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 8px; transition: border-color 0.2s;" placeholder="เช่น For Mobile App, Cron Job" required>
                        @error('name')
                            <p class="text-xs mt-1" style="color:#ef4444; font-weight:500;">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-muted mt-2" style="line-height:1.4;">ระบุชื่อที่ชัดเจนเพื่อให้คุณจดจำได้ว่าคีย์นี้ใช้เชื่อมต่อกับแอปพลิเคชันหรือบริการใด</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="background:#4f46e5; color:white; border-radius:8px; font-weight:600; border:none; padding:0.65rem 1rem; width:100%; box-shadow:0 2px 4px rgba(79,70,229,0.15);">
                        สร้างกุญแจ API Key
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- รายการ API Keys (2 ส่วนบนหน้าจอใหญ่) --}}
    <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
            <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1.25rem 1.5rem;">
                <h3 class="font-semi flex items-center gap-2" style="font-size:1.05rem; color:#1e293b; margin:0;">
                    <svg style="width:20px; height:20px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                    </svg>
                    API Keys ที่ใช้งานอยู่
                </h3>
            </div>
            <div class="card-body" style="padding:0;">
                
                @if($tokens->isEmpty())
                    <div style="padding:3rem 1.5rem; text-align:center; display:flex; flex-direction:column; align-items:center; gap:12px;">
                        <div style="background:#f1f5f9; color:#94a3b8; border-radius:50%; width:56px; height:56px; display:flex; align-items:center; justify-content:center;">
                            <svg style="width:28px; height:28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <div>
                            <p style="font-size:0.95rem; font-weight:600; color:#475569; margin:0;">ยังไม่มี API Key ในระบบ</p>
                            <p class="text-xs text-muted mt-1">โปรดสร้างคีย์แรกในเมนูด้านซ้ายเพื่อเริ่มเชื่อมต่อบริการ</p>
                        </div>
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; margin:0;">
                            <thead>
                                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">
                                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700;">ชื่อ / วัตถุประสงค์</th>
                                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700;">ใช้งานล่าสุด</th>
                                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700;">วันที่สร้าง</th>
                                    <th style="padding:1rem 1.5rem; text-align:right; font-weight:700;">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tokens as $token)
                                    <tr style="border-bottom:1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                                        <td style="padding:1.1rem 1.5rem;">
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <div style="width:8px; height:8px; border-radius:50%; background:#4f46e5;"></div>
                                                <span style="font-size:0.9rem; font-weight:600; color:#1e293b;">{{ $token->name }}</span>
                                            </div>
                                        </td>
                                        <td style="padding:1.1rem 1.5rem; color:#475569; font-size:0.825rem;">
                                            @if($token->last_used_at)
                                                <span class="font-semi" style="color:#0f766e; background:#f0fdfa; padding:2px 8px; border-radius:6px;">
                                                    {{ $token->last_used_at->translatedFormat('d M Y H:i') }}
                                                </span>
                                            @else
                                                <span style="color:#64748b; font-style:italic;">ยังไม่เคยใช้งาน</span>
                                            @endif
                                        </td>
                                        <td style="padding:1.1rem 1.5rem; color:#64748b; font-size:0.825rem;">
                                            {{ $token->created_at->translatedFormat('d M Y') }}
                                        </td>
                                        <td style="padding:1.1rem 1.5rem; text-align:right;">
                                            <form action="{{ route('admin.api-keys.destroy', $token->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบ API Key นี้? หากยกเลิกแล้ว บริการหรือภายนอกที่กำลังใช้คีย์นี้จะไม่สามารถเชื่อมต่อได้อีกทันที')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" style="background:#fff1f2; color:#e11d48; border:1px solid #ffe4e6; padding:0.4rem 0.85rem; border-radius:8px; font-size:0.775rem; font-weight:600; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#ffe4e6'" onmouseout="this.style.background='#fff1f2'">
                                                    ยกเลิกสิทธิ์ (Revoke)
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
</div>

<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
    function copyToken() {
        const tokenText = document.getElementById('newTokenText').innerText;
        const copyBtn = document.getElementById('copyBtn');
        
        const performFeedback = () => {
            const originalText = copyBtn.innerText;
            const originalBg = copyBtn.style.background;
            
            copyBtn.innerText = '✓ คัดลอกแล้ว';
            copyBtn.style.background = '#059669';
            
            setTimeout(() => {
                copyBtn.innerText = originalText;
                copyBtn.style.background = originalBg;
            }, 2500);
        };

        if (!navigator.clipboard) {
            const textArea = document.createElement("textarea");
            textArea.value = tokenText;
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                performFeedback();
            } catch (err) {
                alert('ไม่สามารถคัดลอกได้: ' + err);
            }
            document.body.removeChild(textArea);
            return;
        }
        
        navigator.clipboard.writeText(tokenText).then(() => {
            performFeedback();
        }).catch(err => {
            console.error('Failed to copy: ', err);
            alert('ไม่สามารถคัดลอกได้: ' + err);
        });
    }
</script>
@endsection
