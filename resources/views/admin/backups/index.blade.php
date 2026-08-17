@extends('layouts.admin')

@section('title', 'สำรองและกู้คืนข้อมูลระบบ')

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
                สำรองและกู้คืนข้อมูลระบบ (Automated Backups)
            </h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                ระบบสำรองข้อมูลอัตโนมัติสำหรับฐานข้อมูล, ไฟล์สื่อ, ข้อมูลชีวมิติ (Face Descriptors) และประวัติเช็คชื่อทั้งมหาวิทยาลัย
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-3 flex-wrap">
            <form action="{{ route('admin.backups.clean') }}" method="POST" onsubmit="return confirm('ยืนยันการลบไฟล์สำรองข้อมูลที่เก่าเกิน {{ $scheduleInfo['retention_days'] }} วัน?');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 transition-colors shadow-sm">
                    <svg class="w-4 h-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    ล้างไฟล์เก่า (Cleanup)
                </button>
            </form>

            <button onclick="document.getElementById('manualBackupModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                สำรองข้อมูลทันที (Backup Now)
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
                <p class="text-xs text-zinc-500 mt-0.5">{{ count($backups) }} ไฟล์ในระบบ</p>
            </div>
        </div>

        {{-- Latest Backup --}}
        <div class="bg-white dark:bg-zinc-800/90 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm flex items-center gap-4">
            <div class="p-3.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">สำรองข้อมูลล่าสุด</p>
                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mt-0.5 truncate">
                    {{ $latestBackup ? $latestBackup['created_at'] : 'ยังไม่มีข้อมูล' }}
                </p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                    {{ $latestBackup ? strtoupper($latestBackup['type']) . ' (' . $latestBackup['formatted_size'] . ')' : '-' }}
                </p>
            </div>
        </div>

        {{-- Automated Schedule --}}
        <div class="bg-white dark:bg-zinc-800/90 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm flex items-center gap-4">
            <div class="p-3.5 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">ตารางอัตโนมัติ (Cron)</p>
                <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">ฐานข้อมูล: {{ $scheduleInfo['daily_db'] }}</p>
                <p class="text-xs text-zinc-500 mt-0.5">Full: {{ $scheduleInfo['weekly_full'] }}</p>
            </div>
        </div>

        {{-- Retention Policy --}}
        <div class="bg-white dark:bg-zinc-800/90 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm flex items-center gap-4">
            <div class="p-3.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">นโยบายจัดเก็บ (Retention)</p>
                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">เก็บ {{ $scheduleInfo['retention_days'] }} วัน</p>
                <p class="text-xs text-zinc-500 mt-0.5">สงวนไว้อย่างน้อย {{ $scheduleInfo['keep_minimum'] }} ชุด</p>
            </div>
        </div>
    </div>

    {{-- Security & Biometric Notice --}}
    <div class="p-4 rounded-xl bg-orange-500/5 dark:bg-orange-500/10 border border-orange-500/20 text-orange-900 dark:text-orange-200 text-sm flex items-start gap-3">
        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <span class="font-bold">การคุ้มครองข้อมูลส่วนบุคคลและชีวมิติ (PDPA & Biometric Protection):</span>
            ข้อมูลเวกเตอร์ใบหน้า (512D และ 128D) ภายในไฟล์สำรองจะถูกเข้ารหัสระดับคอลัมน์ (Encrypted at Rest) ด้วย Laravel Crypt Key เสมอ ทุกการดาวน์โหลดหรือลบไฟล์สำรองจะถูกบันทึกประวัติลง <a href="{{ route('admin.audit-logs.index') }}" class="underline font-semibold">Audit Logs</a> โดยอัตโนมัติ
        </div>
    </div>

    {{-- Backup List Table --}}
    <div class="bg-white dark:bg-zinc-800/90 rounded-2xl border border-zinc-200 dark:border-zinc-700/60 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
            <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <span>รายการไฟล์สำรองข้อมูล ({{ count($backups) }})</span>
            </h2>
            <span class="text-xs text-zinc-500">เรียงตามวันที่ล่าสุด</span>
        </div>

        @if(empty($backups))
            <div class="p-12 text-center">
                <div class="inline-flex p-4 rounded-full bg-zinc-100 dark:bg-zinc-700/50 text-zinc-400 mb-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-base font-medium text-zinc-900 dark:text-zinc-200">ยังไม่มีไฟล์สำรองข้อมูลในระบบ</h3>
                <p class="text-sm text-zinc-500 mt-1">คลิกที่ปุ่ม "สำรองข้อมูลทันที" ด้านบนเพื่อเริ่มสำรองข้อมูลชุดแรก หรือรอตารางเวลาอัตโนมัติ</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="px-5 py-3.5">ชื่อไฟล์ (Filename)</th>
                            <th class="px-5 py-3.5">ประเภท (Type)</th>
                            <th class="px-5 py-3.5">ขนาด (Size)</th>
                            <th class="px-5 py-3.5">วันที่สร้าง (Created At)</th>
                            <th class="px-5 py-3.5">SHA-256 Checksum</th>
                            <th class="px-5 py-3.5 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($backups as $b)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-700/30 transition-colors">
                                <td class="px-5 py-4 font-mono font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <span>{{ $b['filename'] }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    @if($b['type'] === 'full')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                            FULL (DB+Files+Bio)
                                        </span>
                                    @elseif($b['type'] === 'db')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                            DATABASE
                                        </span>
                                    @elseif($b['type'] === 'biometrics')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            BIOMETRICS & ATT
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">
                                            FILES
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ $b['formatted_size'] }}
                                </td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $b['created_at'] }}
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-zinc-500">
                                    <span title="{{ $b['sha256'] }}" class="cursor-pointer bg-zinc-100 dark:bg-zinc-700/60 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-600">
                                        {{ substr((string)$b['sha256'], 0, 12) }}...
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.backups.download', $b['filename']) }}" class="p-2 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="ดาวน์โหลดไฟล์สำรอง">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>

                                        <form action="{{ route('admin.backups.destroy', $b['filename']) }}" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบไฟล์สำรองข้อมูล {{ $b['filename'] }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors" title="ลบไฟล์สำรอง">
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
        @endif
    </div>
</div>

{{-- Manual Backup Modal --}}
<div id="manualBackupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-zinc-800 rounded-2xl max-w-md w-full p-6 border border-zinc-200 dark:border-zinc-700 shadow-2xl mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                สร้างการสำรองข้อมูล (Manual Backup)
            </h3>
            <button onclick="document.getElementById('manualBackupModal').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="{{ route('admin.backups.store') }}" method="POST">
            @csrf
            <div class="space-y-3 mb-6">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">เลือกประเภทการสำรองข้อมูล:</label>
                
                {{-- Full --}}
                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30">
                    <input type="radio" name="type" value="full" checked class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">สำรองข้อมูลเต็มรูปแบบ (Full Backup)</div>
                        <div class="text-xs text-zinc-500">ฐานข้อมูล + ไฟล์สื่อทั้งหมด + เวกเตอร์ชีวมิติใบหน้า</div>
                    </div>
                </label>

                {{-- DB Only --}}
                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30">
                    <input type="radio" name="type" value="db" class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">เฉพาะฐานข้อมูล (Database Only)</div>
                        <div class="text-xs text-zinc-500">สร้างไฟล์ SQL Dump โครงสร้างและข้อมูลทุกตาราง (เร็วที่สุด)</div>
                    </div>
                </label>

                {{-- Biometrics & Attendance --}}
                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30">
                    <input type="radio" name="type" value="biometrics" class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">ข้อมูลชีวมิติ & เช็คชื่อ (Biometrics & Attendance)</div>
                        <div class="text-xs text-zinc-500">เวกเตอร์ใบหน้า 512D/128D และประวัติการเช็คชื่อทั้งหมด</div>
                    </div>
                </label>

                {{-- Files Only --}}
                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:border-orange-500 dark:hover:border-orange-500 cursor-pointer transition-all bg-zinc-50/50 dark:bg-zinc-900/30">
                    <input type="radio" name="type" value="files" class="mt-1 text-orange-600 focus:ring-orange-500">
                    <div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100">เฉพาะไฟล์สื่อใน Storage (Storage Files)</div>
                        <div class="text-xs text-zinc-500">รูปภาพโปรไฟล์, รูปกิจกรรม, เอกสารที่อัปโหลด</div>
                    </div>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('manualBackupModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm border border-zinc-300 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                    ยกเลิก
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-600/20">
                    เริ่มกระบวนการสำรองข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
