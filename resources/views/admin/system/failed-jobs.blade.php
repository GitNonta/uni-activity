@extends('layouts.admin')

@section('title', 'Failed Queue Jobs Management')

@section('content')
<div class="container-fluid" style="max-width:1200px; margin:0 auto; padding-bottom:3rem;">
    
    <!-- Top Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <h1 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg style="width:24px; height:24px; color:#ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Failed Queue Jobs
                </h1>
                <span class="badge" style="background:{{ $totalFailed > 0 ? '#fee2e2' : '#dcfce7' }}; color:{{ $totalFailed > 0 ? '#b91c1c' : '#15803d' }}; font-weight:700; font-size:0.8rem; padding:4px 10px; border-radius:999px;">
                    {{ $totalFailed }} งานที่ล้มเหลว
                </span>
            </div>
            <p style="color:#64748b; font-size:0.9rem; margin:0.25rem 0 0 0;">
                รายการงานเบื้องหลัง (LINE Notify, AI Extraction, PDF/Excel Exports) ที่ประมวลผลไม่สำเร็จ สามารถตรวจสอบสาเหตุและกด Retry ได้ทันที
            </p>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
            <a href="{{ route('admin.system.cluster') }}" class="btn btn-outline btn-sm" style="background:#fff;">
                <svg style="width:16px; height:16px; margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Cluster Telemetry
            </a>

            @if($totalFailed > 0)
                <form action="{{ route('admin.system.failed-jobs.retry-all') }}" method="POST" onsubmit="return confirm('คุณต้องการส่งงานที่ล้มเหลวทั้งหมดกลับเข้าคิวประมวลผลใหม่ใช่หรือไม่?');" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm" style="background:#4f46e5; border-color:#4f46e5;">
                        <svg style="width:16px; height:16px; margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        ลองใหม่ทั้งหมด (Retry All)
                    </button>
                </form>

                <form action="{{ route('admin.system.failed-jobs.flush') }}" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการล้างรายการงานที่ล้มเหลวทั้งหมด?');" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" style="background:#ef4444; border-color:#ef4444; color:#fff;">
                        <svg style="width:16px; height:16px; margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        ล้างทั้งหมด (Flush All)
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div style="background:#dcfce7; border:1px solid #86efac; color:#15803d; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.875rem; display:flex; align-items:center; gap:.5rem; line-height:1.6;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background:#fee2e2; border:1px solid #fca5a5; color:#b91c1c; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.875rem; display:flex; align-items:center; gap:.5rem; line-height:1.6;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif
    @if(session('info'))
        <div style="background:#e0f2fe; border:1px solid #7dd3fc; color:#0369a1; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.875rem; display:flex; align-items:center; gap:.5rem; line-height:1.6;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
            {{ session('info') }}
        </div>
    @endif

    <!-- Filters Card -->
    <div style="background:#fff; border-radius:12px; padding:1rem 1.25rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('admin.system.failed-jobs.index') }}" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
            <div style="flex:1; min-width:220px;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="ค้นหาจาก Job Name, Error, หรือ UUID..." class="form-control" style="font-size:0.875rem; border-radius:8px; border:1px solid #cbd5e1; padding:0.45rem 0.75rem; width:100%;">
            </div>

            <div style="min-width:160px;">
                <select name="queue" class="form-select" style="font-size:0.875rem; border-radius:8px; border:1px solid #cbd5e1; padding:0.45rem 0.75rem; width:100%;">
                    <option value="">-- ทุก Queue Channel --</option>
                    @foreach($queues as $q)
                        <option value="{{ $q }}" {{ request('queue') === $q ? 'selected' : '' }}>queue:{{ $q }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex; gap:0.5rem;">
                <button type="submit" class="btn btn-primary btn-sm" style="padding:0.45rem 1rem; border-radius:8px;">ค้นหา</button>
                @if(request()->hasAny(['search', 'queue']))
                    <a href="{{ route('admin.system.failed-jobs.index') }}" class="btn btn-outline btn-sm" style="padding:0.45rem 1rem; border-radius:8px;">ล้างตัวกรอง</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Failed Jobs Table -->
    <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden;">
        @if($failedJobs->isEmpty())
            <div style="text-align:center; padding:3rem 1rem;">
                <div style="width:48px; height:48px; background:#dcfce7; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem auto; color:#16a34a;">
                    <svg style="width:24px; height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 style="font-size:1.1rem; font-weight:700; color:#0f172a; margin-bottom:0.25rem;">ระบบทำงานราบรื่น ไม่มีงานที่ล้มเหลว</h3>
                <p style="color:#64748b; font-size:0.875rem; margin:0;">คิวประมวลผลเบื้องหลังทั้งหมด (Dragonfly Queue) กำลังทำงานอย่างสมบูรณ์แบบ</p>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%; border-collapse:collapse; margin:0;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left; font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">
                            <th style="padding:0.75rem 1rem;">Job Name</th>
                            <th style="padding:0.75rem 1rem;">Queue & Driver</th>
                            <th style="padding:0.75rem 1rem;">Exception Excerpt</th>
                            <th style="padding:0.75rem 1rem;">Failed At</th>
                            <th style="padding:0.75rem 1rem; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($failedJobs as $job)
                        <tr style="border-bottom:1px solid #f1f5f9; font-size:0.875rem;">
                            <td style="padding:0.75rem 1rem;">
                                <div style="font-weight:700; color:#0f172a;">{{ $job->display_name }}</div>
                                <div style="font-size:0.75rem; font-family:monospace; color:#64748b;">{{ $job->uuid }}</div>
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                <span class="badge" style="background:#e0e7ff; color:#4338ca; font-weight:600; font-size:0.75rem; padding:2px 8px; border-radius:4px;">
                                    queue:{{ $job->queue }}
                                </span>
                                <div style="font-size:0.75rem; color:#94a3b8; margin-top:2px;">{{ $job->connection }}</div>
                            </td>
                            <td style="padding:0.75rem 1rem; max-width:360px;">
                                <div style="color:#ef4444; font-family:monospace; font-size:0.8rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ $job->exception_summary }}
                                </div>
                            </td>
                            <td style="padding:0.75rem 1rem; color:#64748b; font-size:0.8rem; white-space:nowrap;">
                                {{ $job->failed_at }}
                            </td>
                            <td style="padding:0.75rem 1rem; text-align:right; white-space:nowrap;">
                                <button onclick="viewJobDetails('{{ $job->uuid }}')" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:3px 8px; margin-right:4px;" title="ดูรายละเอียด Exception">
                                    รายละเอียด
                                </button>

                                <form action="{{ route('admin.system.failed-jobs.retry', $job->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm" style="font-size:0.75rem; padding:3px 8px; background:#4f46e5; border-color:#4f46e5;" title="ส่งกลับเข้าคิวลองใหม่">
                                        ลองใหม่
                                    </button>
                                </form>

                                <form action="{{ route('admin.system.failed-jobs.destroy', $job->id) }}" method="POST" onsubmit="return confirm('คุณต้องการลบรายการนี้ใช่หรือไม่?');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:3px 8px; color:#ef4444; border-color:#fca5a5;" title="ลบทิ้ง">
                                        ลบ
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($failedJobs->hasPages())
                <div style="padding:1rem; border-top:1px solid #e2e8f0;">
                    {{ $failedJobs->links() }}
                </div>
            @endif
        @endif
    </div>

</div>

<!-- Exception Detail Modal -->
<div id="jobDetailModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:1.5rem;">
    <div style="background:#fff; border-radius:12px; max-width:800px; width:100%; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); overflow:hidden;">
        <div style="padding:1.25rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="modalJobTitle" style="font-size:1.1rem; font-weight:700; color:#0f172a; margin:0;">รายละเอียด Exception Stack Trace</h3>
            <button onclick="closeJobModal()" style="background:none; border:none; font-size:1.25rem; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:1.25rem; overflow-y:auto; flex:1;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem; font-size:0.85rem;">
                <div><strong>UUID:</strong> <span id="modalUuid" style="font-family:monospace; color:#475569;"></span></div>
                <div><strong>Queue:</strong> <span id="modalQueue" style="color:#4338ca; font-weight:600;"></span></div>
                <div><strong>Failed At:</strong> <span id="modalFailedAt" style="color:#64748b;"></span></div>
                <div><strong>Connection:</strong> <span id="modalConnection" style="color:#64748b;"></span></div>
            </div>
            <div>
                <strong style="font-size:0.875rem; color:#0f172a; display:block; margin-bottom:0.35rem;">Exception Stack Trace:</strong>
                <pre id="modalException" style="background:#0f172a; color:#f8fafc; padding:1rem; border-radius:8px; font-size:0.75rem; max-height:350px; overflow-x:auto; white-space:pre-wrap; font-family:monospace; line-height:1.4;"></pre>
            </div>
        </div>
        <div style="padding:1rem 1.25rem; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:0.5rem; background:#f8fafc;">
            <button onclick="closeJobModal()" class="btn btn-outline btn-sm">ปิด</button>
        </div>
    </div>
</div>

<script>
    async function viewJobDetails(uuid) {
        try {
            const res = await fetch(`{{ url('/admin/system/failed-jobs') }}/${uuid}`);
            if (!res.ok) throw new Error('Failed to load details');
            const data = await res.json();

            document.getElementById('modalJobTitle').textContent = data.display_name;
            document.getElementById('modalUuid').textContent = data.uuid;
            document.getElementById('modalQueue').textContent = 'queue:' + data.queue;
            document.getElementById('modalFailedAt').textContent = data.failed_at;
            document.getElementById('modalConnection').textContent = data.connection;
            document.getElementById('modalException').textContent = data.exception;

            const modal = document.getElementById('jobDetailModal');
            modal.style.display = 'flex';
        } catch (e) {
            alert('ไม่สามารถโหลดรายละเอียดได้: ' + e.message);
        }
    }

    function closeJobModal() {
        document.getElementById('jobDetailModal').style.display = 'none';
    }
</script>
@endsection
