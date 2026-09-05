@extends('layouts.admin')
@section('title', 'สำรองและกู้คืนข้อมูลระบบ')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.5rem;">สำรองและกู้คืนข้อมูลระบบ</h1>
    <div style="display:flex; gap:8px;">
        <form action="{{ route('admin.backups.clean') }}" method="POST" onsubmit="return confirm('ล้างไฟล์สำรองที่เก่าเกิน {{ $scheduleInfo['retention_days'] }} วัน?');">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">ล้างไฟล์เก่า</button>
        </form>
        <button onclick="openModal()" class="btn btn-primary btn-sm">+ สำรองข้อมูลทันที</button>
    </div>
</div>

{{-- ═══ Summary Row ═══ --}}
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:1.25rem;">
    <div class="card" style="padding:1rem;">
        <p class="text-muted" style="font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin:0;">ขนาดรวม</p>
        <p style="font-size:1.1rem; font-weight:700; color:#0f172a; margin:4px 0 0;">{{ $formattedTotalSize }}</p>
        <p class="text-muted" style="font-size:0.7rem; margin:2px 0 0;">{{ count($backups) }} ไฟล์</p>
    </div>
    <div class="card" style="padding:1rem;">
        <p class="text-muted" style="font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin:0;">ล่าสุด</p>
        @if($latestBackup)
            <p style="font-size:0.85rem; font-weight:700; color:#0f172a; margin:4px 0 0;">{{ $latestBackup['created_at'] }}</p>
            <p class="text-muted" style="font-size:0.7rem; margin:2px 0 0;">{{ strtoupper($latestBackup['type']) }} · {{ $latestBackup['formatted_size'] }}</p>
        @else
            <p class="text-muted" style="font-size:0.85rem; margin:4px 0 0;">—</p>
        @endif
    </div>
    <div class="card" style="padding:1rem;">
        <p class="text-muted" style="font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin:0;">ตารางอัตโนมัติ</p>
        <p style="font-size:0.8rem; font-weight:700; color:#0f172a; margin:4px 0 0;">DB: {{ $scheduleInfo['daily_db'] }}</p>
        <p class="text-muted" style="font-size:0.7rem; margin:2px 0 0;">Full: {{ $scheduleInfo['weekly_full'] }}</p>
    </div>
    <div class="card" style="padding:1rem;">
        <p class="text-muted" style="font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin:0;">พื้นที่ดิสก์</p>
        <p style="font-size:0.85rem; font-weight:700; color:#0f172a; margin:4px 0 0;">{{ $formattedDiskUsed }} / {{ $formattedDiskTotal }}</p>
        <div style="height:6px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin:6px 0 0;">
            <div style="height:100%; border-radius:99px; width:{{ $diskPercent }}%; background:{{ $diskPercent > 90 ? '#ef4444' : ($diskPercent > 70 ? '#eab308' : '#22c55e') }};"></div>
        </div>
        <p class="text-muted" style="font-size:0.65rem; margin:4px 0 0;">ว่าง {{ $formattedDiskFree }}</p>
    </div>
</div>

{{-- ═══ Notice ═══ --}}
<div class="alert alert-info" style="display:flex; align-items:flex-start; gap:8px; margin-bottom:1.25rem;">
    <svg style="width:16px; height:16px; flex-shrink:0; margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span><strong>PDPA:</strong> Face vectors encrypted at rest. Downloads/deletions logged to <a href="{{ route('admin.audit-logs.index') }}" style="font-weight:600;">Audit Logs</a>.</span>
</div>

{{-- ═══ Table ═══ --}}
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span>รายการไฟล์สำรอง ({{ count($backups) }})</span>
        <div style="display:flex; gap:4px;" id="filterTabs">
            <button onclick="filterBackups('all')" class="filter-tab active" data-filter="all">ทั้งหมด</button>
            @php
                $fullCount = count(array_filter($backups, fn($b) => $b['type'] === 'full'));
                $dbCount = count(array_filter($backups, fn($b) => $b['type'] === 'db'));
                $bioCount = count(array_filter($backups, fn($b) => $b['type'] === 'biometrics'));
                $fileCount = count(array_filter($backups, fn($b) => $b['type'] === 'files'));
            @endphp
            @if($fullCount > 0)<button onclick="filterBackups('full')" class="filter-tab" data-filter="full">Full ({{ $fullCount }})</button>@endif
            @if($dbCount > 0)<button onclick="filterBackups('db')" class="filter-tab" data-filter="db">DB ({{ $dbCount }})</button>@endif
            @if($bioCount > 0)<button onclick="filterBackups('biometrics')" class="filter-tab" data-filter="biometrics">Bio ({{ $bioCount }})</button>@endif
            @if($fileCount > 0)<button onclick="filterBackups('files')" class="filter-tab" data-filter="files">Files ({{ $fileCount }})</button>@endif
        </div>
    </div>

    @if(empty($backups))
        <div style="padding:3rem; text-align:center;" class="text-muted">
            <p style="font-size:0.95rem; margin:0 0 4px;">ยังไม่มีไฟล์สำรองข้อมูล</p>
            <p style="font-size:0.8rem; margin:0;">คลิก "+ สำรองข้อมูลทันที" หรือรอตารางอัตโนมัติ</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>ไฟล์</th>
                        <th class="text-center">ประเภท</th>
                        <th class="text-center">ขนาด</th>
                        <th class="text-center">วันที่</th>
                        <th class="text-center">อายุ</th>
                        <th>Checksum</th>
                        <th class="text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($backups as $b)
                        @php
                            $ts = strtotime($b['created_at']);
                            $diffH = (time() - $ts) / 3600;
                            if ($diffH < 24) { $ageCls = 'badge-green'; $ageTxt = round($diffH) . 'ชม.'; }
                            elseif ($diffH < 168) { $ageCls = 'badge-yellow'; $ageTxt = round($diffH / 24) . 'วัน'; }
                            else { $ageCls = 'badge-gray'; $ageTxt = round($diffH / 168) . 'สัปดาห์'; }
                        @endphp
                        <tr data-type="{{ $b['type'] }}">
                            <td data-label="ไฟล์" style="font-family:monospace; font-size:0.8rem; font-weight:600;">{{ $b['filename'] }}</td>
                            <td data-label="ประเภท" class="text-center">
                                @if($b['type'] === 'full')
                                    <span class="badge badge-orange">FULL</span>
                                @elseif($b['type'] === 'db')
                                    <span class="badge badge-green">DB</span>
                                @elseif($b['type'] === 'biometrics')
                                    <span class="badge badge-yellow">BIO</span>
                                @else
                                    <span class="badge badge-gray">FILES</span>
                                @endif
                            </td>
                            <td data-label="ขนาด" class="text-center">{{ $b['formatted_size'] }}</td>
                            <td data-label="วันที่" class="text-center text-muted" style="font-size:0.8rem;">{{ $b['created_at'] }}</td>
                            <td data-label="อายุ" class="text-center"><span class="badge {{ $ageCls }}">{{ $ageTxt }}</span></td>
                            <td data-label="Checksum">
                                <button onclick="copyHash('{{ $b['sha256'] }}', this)" class="copy-btn" title="Click to copy">{{ substr((string)$b['sha256'], 0, 12 ) }}…</button>
                            </td>
                            <td data-label="จัดการ" class="text-right">
                                <div style="display:flex; justify-content:flex-end; gap:4px;">
                                    <a href="{{ route('admin.backups.download', $b['filename']) }}" class="btn btn-outline btn-sm" title="ดาวน์โหลด">↓</a>
                                    <form action="{{ route('admin.backups.destroy', $b['filename']) }}" method="POST" style="display:inline;" onsubmit="return confirm('ลบ {{ $b['filename'] }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="ลบ" style="display:inline-flex;align-items:center;justify-content:center;gap:.3rem;">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            ลบ
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ═══ Modal ═══ --}}
<div id="backupModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; padding:1rem;">
    <div class="card" style="width:100%; max-width:420px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <span>สร้างการสำรองข้อมูล</span>
            <button onclick="closeModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;transition:background .15s;" aria-label="ปิด">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.backups.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">เลือกประเภท</label>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <label class="checkbox-label"><input type="radio" name="type" value="full" checked> Full Backup — ฐานข้อมูล + ไฟล์สื่อ + เวกเตอร์ชีวมิติ</label>
                        <label class="checkbox-label"><input type="radio" name="type" value="db"> Database Only — SQL Dump ทุกตาราง</label>
                        <label class="checkbox-label"><input type="radio" name="type" value="biometrics"> Biometrics — เวกเตอร์ใบหน้า + ประวัติเช็คชื่อ</label>
                        <label class="checkbox-label"><input type="radio" name="type" value="files"> Files — รูปภาพและเอกสารอัปโหลด</label>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:0.5rem; border-top:1px solid #f1f5f9;">
                    <button type="button" onclick="closeModal()" class="btn btn-outline btn-sm">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary btn-sm">เริ่มสำรองข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="toastContainer" style="position:fixed; top:80px; right:20px; z-index:9999;"></div>
@endsection

@section('styles')
<style>
    .filter-tab { padding:4px 10px; font-size:0.7rem; font-weight:600; border-radius:4px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer; }
    .filter-tab.active { background:#ea580c; color:#fff; border-color:#ea580c; }
    .filter-tab:hover { border-color:#ea580c; color:#ea580c; }
    .copy-btn { font-family:monospace; font-size:0.7rem; padding:2px 8px; border-radius:4px; background:#f1f5f9; border:1px solid #e2e8f0; color:#64748b; cursor:pointer; transition:background 0.15s; }
    .copy-btn:hover { background:#ea580c; color:#fff; border-color:#ea580c; }
    .toast { padding:0.625rem 0.875rem; border-radius:6px; font-size:0.8rem; font-weight:600; box-shadow:0 2px 8px rgba(0,0,0,0.12); margin-bottom:6px; animation:fadeIn 0.2s ease; }
    .toast-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .toast-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    @media (max-width:768px) { .responsive-table { display:block; } }
</style>
@endsection

@section('scripts')
<script>
(function() {
    @if(session('success')) showToast('{{ addslashes(session("success")) }}', 'success'); @endif
    @if(session('error')) showToast('{{ addslashes(session("error")) }}', 'error'); @endif

    window.filterBackups = function(type) {
        document.querySelectorAll('.filter-tab').forEach(function(t) { t.classList.toggle('active', t.dataset.filter === type); });
        var n = 0;
        document.querySelectorAll('[data-type]').forEach(function(el) {
            var show = type === 'all' || el.dataset.type === type;
            el.style.display = show ? '' : 'none';
            if (show) n++;
        });
        document.getElementById('backupCount').textContent = n;
    };

    window.copyHash = function(hash, btn) {
        navigator.clipboard.writeText(hash).then(function() {
            var orig = btn.textContent;
            btn.textContent = '✓ Copied';
            btn.style.background = '#16a34a'; btn.style.color = '#fff'; btn.style.borderColor = '#16a34a';
            setTimeout(function() { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = ''; }, 1200);
        });
    };

    window.openModal = function() { document.getElementById('backupModal').style.display = 'flex'; };
    window.closeModal = function() { document.getElementById('backupModal').style.display = 'none'; };
    document.getElementById('backupModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'b') { e.preventDefault(); openModal(); }
        if (e.key === 'Escape') closeModal();
    });

    function showToast(msg, type) {
        var t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.textContent = msg;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(function() { t.remove(); }, 3500);
    }
})();
</script>
@endsection
