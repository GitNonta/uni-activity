@extends('layouts.admin')

@section('title', 'สำรองและกู้คืนข้อมูลระบบ')

@section('styles')
<style>
    /* ── Backup Page Styles ── */
    .backup-tab { transition: all 0.2s; }
    .backup-tab.active { background: #ea580c; color: #fff; border-color: #ea580c; }
    .backup-tab:not(.active):hover { border-color: #ea580c; color: #ea580c; }

    .backup-row { transition: background 0.15s; }
    .backup-row:hover { background: #f8fafc; }
    html[data-theme="dark"] .backup-row:hover { background: #27272a; }

    .copy-hash-btn { cursor: pointer; transition: all 0.15s; }
    .copy-hash-btn:hover { background: #ea580c !important; color: #fff !important; }
    .copy-hash-btn.copied { background: #16a34a !important; color: #fff !important; }

    .backup-spinner { display: none; }
    .backup-btn-loading .backup-spinner { display: inline-block; }
    .backup-btn-loading .backup-btn-text { display: none; }

    .disk-bar { height: 8px; border-radius: 9999px; background: #e2e8f0; overflow: hidden; }
    html[data-theme="dark"] .disk-bar { background: #3f3f46; }
    .disk-bar-fill { height: 100%; border-radius: 9999px; transition: width 0.5s ease; }
    .disk-bar-fill.low { background: #16a34a; }
    .disk-bar-fill.medium { background: #eab308; }
    .disk-bar-fill.high { background: #ef4444; }

    .age-badge { font-size: 0.65rem; padding: 2px 8px; border-radius: 9999px; font-weight: 600; }
    .age-fresh { background: #dcfce7; color: #166534; }
    .age-recent { background: #dbeafe; color: #1e40af; }
    .age-old { background: #fef3c7; color: #92400e; }
    html[data-theme="dark"] .age-fresh { background: #14532d; color: #86efac; }
    html[data-theme="dark"] .age-recent { background: #1e3a5f; color: #93c5fd; }
    html[data-theme="dark"] .age-old { background: #78350f; color: #fcd34d; }

    /* Mobile card layout */
    @media (max-width: 768px) {
        .backup-table-desktop { display: none !important; }
        .backup-cards-mobile { display: block !important; }
    }
    @media (min-width: 769px) {
        .backup-cards-mobile { display: none !important; }
    }

    .backup-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: border-color 0.2s;
    }
    .backup-card:hover { border-color: #ea580c; }
    html[data-theme="dark"] .backup-card {
        background: #18181b;
        border-color: #3f3f46;
    }
    html[data-theme="dark"] .backup-card:hover { border-color: #ea580c; }

    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .toast {
        padding: 0.75rem 1rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease, fadeOut 0.3s ease 3.7s forwards;
        max-width: 400px;
    }
    .toast-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .toast-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    html[data-theme="dark"] .toast-success { background: #14532d; color: #86efac; border-color: #166534; }
    html[data-theme="dark"] .toast-error { background: #7f1d1d; color: #fca5a5; border-color: #991b1b; }

    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; transform: translateX(50px); }
    }
</style>
@endsection

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-zinc-800/80 p-6 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:underline">Dashboard</a>
                <span>/</span>
                <span class="text-zinc-900 dark:text-zinc-200 font-medium">สำรองข้อมูลระบบ</span>
            </div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-3">
                <span class="p-2.5 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                </span>
                สำรองและกู้คืนข้อมูลระบบ
            </h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                ระบบสำรองข้อมูลอัตโนมัติสำหรับฐานข้อมูล, ไฟล์สื่อ, ข้อมูลชีวมิติ (Face Descriptors) และประวัติเช็คชื่อ
            </p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <form action="{{ route('admin.backups.clean') }}" method="POST" onsubmit="return confirm('ยืนยันการลบไฟล์สำรองข้อมูลที่เก่าเกิน {{ $scheduleInfo['retention_days'] }} วัน?');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 transition-colors shadow-sm">
                    <svg class="w-4 h-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    ล้างไฟล์เก่า
                </button>
            </form>

            <button onclick="openBackupModal()" id="backupNowBtn" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                <svg class="w-4 h-4 backup-btn-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <svg class="backup-spinner animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span class="backup-btn-text">สำรองข้อมูลทันที</span>
                <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] font-mono bg-white/20 rounded">Ctrl+B</kbd>
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Size --}}
        <div class="bg-white dark:bg-zinc-800/90 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm flex items-center gap-4">
            <div class="p-3.5 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">ขนาดสำรองรวม</p>
                <p class="text-xl font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $formattedTotalSize }}</p>
                <p class="text-xs text-zinc-500 mt-0.5">{{ count($backups) }} ไฟล์</p>
            </div>
        </div>

        {{-- Latest Backup --}}
        <div class="bg-white dark:bg-zinc-800/90 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm flex items-center gap-4">
            <div class="p-3.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">สำรองล่าสุด</p>
                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mt-0.5 truncate">
                    {{ $latestBackup ? $latestBackup['created_at'] : 'ยังไม่มีข้อมูล' }}
                </p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                    @if($latestBackup)
                        {{ strtoupper($latestBackup['type']) }} · {{ $latestBackup['formatted_size'] }}
                        · <span data-time="{{ $latestBackup['created_at'] }}">{{ $latestBackup['created_at'] }}</span>
                    @else
                        -
                    @endif
                </p>
            </div>
        </div>

        {{-- Schedule --}}
        <div class="bg-white dark:bg-zinc-800/90 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm flex items-center gap-4">
            <div class="p-3.5 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">ตารางอัตโนมัติ</p>
                <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">DB: {{ $scheduleInfo['daily_db'] }}</p>
                <p class="text-xs text-zinc-500 mt-0.5">Full: {{ $scheduleInfo['weekly_full'] }}</p>
            </div>
        </div>

        {{-- Disk Space --}}
        <div class="bg-white dark:bg-zinc-800/90 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm flex items-center gap-4">
            <div class="p-3.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">พื้นที่ดิสก์</p>
                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                    {{ \App\Repositories\BackupRepository::formatBytes((int) $diskUsed) }} / {{ \App\Repositories\BackupRepository::formatBytes((int) $diskTotal) }}
                </p>
                <div class="disk-bar mt-1.5">
                    <div class="disk-bar-fill {{ $diskPercent > 90 ? 'high' : ($diskPercent > 70 ? 'medium' : 'low') }}" style="width: {{ $diskPercent }}%"></div>
                </div>
                <p class="text-[10px] text-zinc-500 mt-1">{{ $diskPercent }}% ใช้งาน · ว่าง {{ \App\Repositories\BackupRepository::formatBytes((int) $diskFree) }}</p>
            </div>
        </div>
    </div>

    {{-- PDPA Notice --}}
    <div class="p-4 rounded-xl bg-orange-500/5 dark:bg-orange-500/10 border border-orange-500/20 text-orange-900 dark:text-orange-200 text-sm flex items-start gap-3">
        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <span class="font-bold">PDPA & Biometric Protection:</span>
            ข้อมูลเวกเตอร์ใบหน้า (512D/128D) ถูกเข้ารหัส (Encrypted at Rest) ด้วย Laravel Crypt Key ทุกการดาวน์โหลด/ลบจะบันทึกลง <a href="{{ route('admin.audit-logs.index') }}" class="underline font-semibold hover:text-orange-700">Audit Logs</a>
        </div>
    </div>

    {{-- Backup List --}}
    <div class="bg-white dark:bg-zinc-800/90 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm overflow-hidden">
        {{-- Header + Filter Tabs --}}
        <div class="p-5 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <span>รายการไฟล์สำรอง</span>
                    <span class="text-xs font-semibold text-zinc-400 bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 rounded-full" id="backupCount">{{ count($backups) }}</span>
                </h2>

                {{-- Type Filter Tabs --}}
                <div class="flex items-center gap-1.5 flex-wrap" id="filterTabs">
                    <button onclick="filterBackups('all')" class="backup-tab active px-3 py-1.5 text-xs font-semibold rounded-lg border border-zinc-200 dark:border-zinc-600 transition-all" data-filter="all">
                        ทั้งหมด <span class="ml-1 opacity-70">{{ count($backups) }}</span>
                    </button>
                    @php
                        $fullCount = count(array_filter($backups, fn($b) => $b['type'] === 'full'));
                        $dbCount = count(array_filter($backups, fn($b) => $b['type'] === 'db'));
                        $bioCount = count(array_filter($backups, fn($b) => $b['type'] === 'biometrics'));
                        $fileCount = count(array_filter($backups, fn($b) => $b['type'] === 'files'));
                    @endphp
                    @if($fullCount > 0)
                        <button onclick="filterBackups('full')" class="backup-tab px-3 py-1.5 text-xs font-semibold rounded-lg border border-zinc-200 dark:border-zinc-600 transition-all" data-filter="full">
                            FULL <span class="ml-1 opacity-70">{{ $fullCount }}</span>
                        </button>
                    @endif
                    @if($dbCount > 0)
                        <button onclick="filterBackups('db')" class="backup-tab px-3 py-1.5 text-xs font-semibold rounded-lg border border-zinc-200 dark:border-zinc-600 transition-all" data-filter="db">
                            DB <span class="ml-1 opacity-70">{{ $dbCount }}</span>
                        </button>
                    @endif
                    @if($bioCount > 0)
                        <button onclick="filterBackups('biometrics')" class="backup-tab px-3 py-1.5 text-xs font-semibold rounded-lg border border-zinc-200 dark:border-zinc-600 transition-all" data-filter="biometrics">
                            Bio <span class="ml-1 opacity-70">{{ $bioCount }}</span>
                        </button>
                    @endif
                    @if($fileCount > 0)
                        <button onclick="filterBackups('files')" class="backup-tab px-3 py-1.5 text-xs font-semibold rounded-lg border border-zinc-200 dark:border-zinc-600 transition-all" data-filter="files">
                            Files <span class="ml-1 opacity-70">{{ $fileCount }}</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        @if(empty($backups))
            <div class="p-12 text-center">
                <div class="inline-flex p-4 rounded-full bg-zinc-100 dark:bg-zinc-700/50 text-zinc-400 mb-3">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-base font-medium text-zinc-900 dark:text-zinc-200">ยังไม่มีไฟล์สำรองข้อมูล</h3>
                <p class="text-sm text-zinc-500 mt-1 max-w-md mx-auto">คลิกปุ่ม "สำรองข้อมูลทันที" ด้านบน หรือรอตารางเวลาอัตโนมัติ (ทุกวัน 01:00 น.)</p>
                <button onclick="openBackupModal()" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-orange-600 hover:bg-orange-500 text-white transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    เริ่มสำรองข้อมูลชุดแรก
                </button>
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="overflow-x-auto backup-table-desktop">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="px-5 py-3.5">ไฟล์</th>
                            <th class="px-5 py-3.5">ประเภท</th>
                            <th class="px-5 py-3.5">ขนาด</th>
                            <th class="px-5 py-3.5">วันที่</th>
                            <th class="px-5 py-3.5">Age</th>
                            <th class="px-5 py-3.5">SHA-256</th>
                            <th class="px-5 py-3.5 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @foreach($backups as $b)
                            @php
                                $ts = strtotime($b['created_at']);
                                $diffHours = (time() - $ts) / 3600;
                                if ($diffHours < 24) { $ageClass = 'age-fresh'; $ageText = round($diffHours) . 'ชม.'; }
                                elseif ($diffHours < 168) { $ageClass = 'age-recent'; $ageText = round($diffHours / 24) . 'วัน'; }
                                else { $ageClass = 'age-old'; $ageText = round($diffHours / 168) . 'สัปดาห์'; }
                            @endphp
                            <tr class="backup-row" data-type="{{ $b['type'] }}">
                                <td class="px-5 py-4 font-mono font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                    @if($b['type'] === 'full')
                                        <svg class="w-4 h-4 text-purple-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                    @elseif($b['type'] === 'db')
                                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                                    @elseif($b['type'] === 'biometrics')
                                        <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    @else
                                        <svg class="w-4 h-4 text-zinc-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    @endif
                                    <span class="truncate max-w-[260px]" title="{{ $b['filename'] }}">{{ $b['filename'] }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    @if($b['type'] === 'full')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">FULL</span>
                                    @elseif($b['type'] === 'db')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">DATABASE</span>
                                    @elseif($b['type'] === 'biometrics')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">BIOMETRICS</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">FILES</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ $b['formatted_size'] }}</td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-400 text-xs">{{ $b['created_at'] }}</td>
                                <td class="px-5 py-4"><span class="age-badge {{ $ageClass }}">{{ $ageText }}</span></td>
                                <td class="px-5 py-4">
                                    <button onclick="copyHash('{{ $b['sha256'] }}', this)" class="copy-hash-btn inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-zinc-100 dark:bg-zinc-700/60 border border-zinc-200 dark:border-zinc-600 font-mono text-[11px] text-zinc-500 hover:text-white transition-all" title="Click to copy full SHA-256">
                                        {{ substr((string)$b['sha256'], 0, 10) }}…
                                        <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.backups.download', $b['filename']) }}" class="p-2 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="ดาวน์โหลด">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>
                                        <form action="{{ route('admin.backups.destroy', $b['filename']) }}" method="POST" class="inline" onsubmit="return confirm('ลบไฟล์สำรอง {{ $b['filename'] }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors" title="ลบ">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="p-4 backup-cards-mobile" style="display:none;">
                @foreach($backups as $b)
                    @php
                        $ts = strtotime($b['created_at']);
                        $diffHours = (time() - $ts) / 3600;
                        if ($diffHours < 24) { $ageClass = 'age-fresh'; $ageText = round($diffHours) . 'ชม.'; }
                        elseif ($diffHours < 168) { $ageClass = 'age-recent'; $ageText = round($diffHours / 24) . 'วัน'; }
                        else { $ageClass = 'age-old'; $ageText = round($diffHours / 168) . 'สัปดาห์'; }
                    @endphp
                    <div class="backup-card" data-type="{{ $b['type'] }}">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($b['type'] === 'full')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">FULL</span>
                                @elseif($b['type'] === 'db')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">DB</span>
                                @elseif($b['type'] === 'biometrics')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">BIO</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">FILE</span>
                                @endif
                                <span class="age-badge {{ $ageClass }}">{{ $ageText }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('admin.backups.download', $b['filename']) }}" class="p-2 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                                <form action="{{ route('admin.backups.destroy', $b['filename']) }}" method="POST" class="inline" onsubmit="return confirm('ลบไฟล์นี้?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <p class="font-mono text-xs text-zinc-900 dark:text-zinc-100 truncate mb-1" title="{{ $b['filename'] }}">{{ $b['filename'] }}</p>
                        <div class="flex items-center gap-3 text-xs text-zinc-500">
                            <span>{{ $b['formatted_size'] }}</span>
                            <span>·</span>
                            <span>{{ $b['created_at'] }}</span>
                        </div>
                        <button onclick="copyHash('{{ $b['sha256'] }}', this)" class="mt-2 copy-hash-btn inline-flex items-center gap-1 px-2 py-1 rounded-md bg-zinc-100 dark:bg-zinc-700/60 border border-zinc-200 dark:border-zinc-600 font-mono text-[10px] text-zinc-500">
                            {{ substr((string)$b['sha256'], 0, 16) }}…
                            <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Toast Container --}}
<div class="toast-container" id="toastContainer"></div>

{{-- Manual Backup Modal --}}
<div id="manualBackupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden" onclick="if(event.target===this)closeBackupModal()">
    <div class="bg-white dark:bg-zinc-800 rounded-2xl max-w-md w-full p-6 border border-zinc-200 dark:border-zinc-700 shadow-2xl mx-4 transform transition-all">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                สร้างการสำรองข้อมูล
            </h3>
            <button onclick="closeBackupModal()" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="{{ route('admin.backups.store') }}" method="POST" id="backupForm">
            @csrf
            <div class="space-y-2.5 mb-6">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">เลือกประเภท:</label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-orange-400 dark:hover:border-orange-400 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-500/5 dark:has-[:checked]:bg-orange-500/10">
                    <input type="radio" name="type" value="full" checked class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Full Backup</div>
                        <div class="text-xs text-zinc-500">ฐานข้อมูล + ไฟล์สื่อ + เวกเตอร์ชีวมิติ</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-orange-400 dark:hover:border-orange-400 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-500/5 dark:has-[:checked]:bg-orange-500/10">
                    <input type="radio" name="type" value="db" class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Database Only</div>
                        <div class="text-xs text-zinc-500">SQL Dump โครงสร้างและข้อมูลทุกตาราง (เร็วที่สุด)</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-orange-400 dark:hover:border-orange-400 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-500/5 dark:has-[:checked]:bg-orange-500/10">
                    <input type="radio" name="type" value="biometrics" class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Biometrics & Attendance</div>
                        <div class="text-xs text-zinc-500">เวกเตอร์ใบหน้า 512D/128D และประวัติเช็คชื่อ</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-orange-400 dark:hover:border-orange-400 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-500/5 dark:has-[:checked]:bg-orange-500/10">
                    <input type="radio" name="type" value="files" class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Storage Files</div>
                        <div class="text-xs text-zinc-500">รูปภาพโปรไฟล์, รูปกิจกรรม, เอกสารอัปโหลด</div>
                    </div>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeBackupModal()" class="px-4 py-2 rounded-xl text-sm border border-zinc-300 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                    ยกเลิก
                </button>
                <button type="submit" id="backupSubmitBtn" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-600/20 transition-all">
                    <svg class="backup-spinner animate-spin w-4 h-4" style="display:none;" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span class="submit-text">เริ่มสำรองข้อมูล</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    // ── Toast notifications from session flash ──
    @if(session('success'))
        showToast('✓ {{ session('success') }}', 'success');
    @endif
    @if(session('error'))
        showToast('✗ {{ session('error') }}', 'error');
    @endif

    // ── Filter tabs ──
    window.filterBackups = function(type) {
        // Update active tab
        document.querySelectorAll('.backup-tab').forEach(function(tab) {
            tab.classList.toggle('active', tab.dataset.filter === type);
        });

        // Filter rows
        var count = 0;
        document.querySelectorAll('[data-type]').forEach(function(el) {
            var show = type === 'all' || el.dataset.type === type;
            el.style.display = show ? '' : 'none';
            if (show) count++;
        });

        document.getElementById('backupCount').textContent = count;
    };

    // ── Copy SHA-256 ──
    window.copyHash = function(hash, btn) {
        navigator.clipboard.writeText(hash).then(function() {
            btn.classList.add('copied');
            var orig = btn.innerHTML;
            btn.innerHTML = '✓ Copied';
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = orig;
            }, 1500);
        });
    };

    // ── Backup modal ──
    window.openBackupModal = function() {
        document.getElementById('manualBackupModal').classList.remove('hidden');
    };
    window.closeBackupModal = function() {
        document.getElementById('manualBackupModal').classList.add('hidden');
    };

    // ── Form submit loading state ──
    document.getElementById('backupForm').addEventListener('submit', function() {
        var btn = document.getElementById('backupSubmitBtn');
        btn.disabled = true;
        btn.querySelector('.backup-spinner').style.display = 'inline-block';
        btn.querySelector('.submit-text').textContent = 'กำลังสำรองข้อมูล...';
    });

    // ── Keyboard shortcut: Ctrl+B ──
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            openBackupModal();
        }
        if (e.key === 'Escape') {
            closeBackupModal();
        }
    });

    // ── Toast helper ──
    function showToast(message, type) {
        var container = document.getElementById('toastContainer');
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 4000);
    }
    window.showToast = showToast;
})();
</script>
@endsection
