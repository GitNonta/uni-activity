<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Log – Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f4ff, #e8eaf6); margin:0; padding:0; color:#333;}
        .container {max-width:1200px; margin:2rem auto; padding:1rem; background:rgba(255,255,255,0.85); border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.12);}
        h1 {font-size:1.8rem; font-weight:600; margin-bottom:1rem; text-align:center; color:#2c3e50;}
        .filter-form {display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; justify-content:space-between;}
        .filter-form input,.filter-form select {padding:0.5rem 0.75rem; border:1px solid #cbd5e0; border-radius:6px; min-width:150px; transition:border-color 0.2s;}
        .filter-form input:focus,.filter-form select:focus {outline:none; border-color:#5a67d8;}
        .filter-form button {background:#5a67d8; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; cursor:pointer; transition:background 0.2s;}
        .filter-form button:hover {background:#434190;}
        table {width:100%; border-collapse:collapse; margin-top:1rem;}
        th,td {padding:0.75rem 1rem; text-align:left;}
        th {background:#e2e8f0; font-weight:600;}
        tr:nth-child(even) {background:#f7fafc;}
        tr:hover {background:#edf2f7;}
        .pagination {margin-top:1.5rem; display:flex; justify-content:center; gap:0.5rem;}
        .pagination a,.pagination span {padding:0.4rem 0.8rem; border:1px solid #cbd5e0; border-radius:4px; text-decoration:none; color:#2d3748;}
        .pagination .active {background:#5a67d8; color:#fff; border-color:#5a67d8;}
    </style>
</head>
<body>
<div class="container">
    <h1>Audit Log</h1>
    <a href="{{ route('admin.dashboard') }}" class="btn" style="background:#5a67d8;color:#fff;padding:0.5rem 1rem;border-radius:6px;margin-bottom:1rem;display:inline-block;">Dashboard</a>
    <form method="GET" class="filter-form" action="{{ route('admin.audit-logs.index') }}">
        <input type="number" name="user_id" placeholder="User ID" value="{{ request('user_id') }}" min="1" />
        <input type="text" name="action" placeholder="Action" value="{{ request('action') }}" />
        <input type="date" name="date_from" value="{{ request('date_from') }}" />
        <input type="date" name="date_to" value="{{ request('date_to') }}" />
        <button type="submit">Filter</button>
    </form>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>User ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Action</th>
            <th>Description</th>
            <th>IP Address</th>
            <th>Device</th>
            <th>Created At</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($auditLogs as $log)
            <tr>
                <td>{{ $log->id }}</td>
                <td>{{ $log->user_id }}</td>
                <td>{{ $log->user->full_name ?? '-' }}</td>
                <td>{{ $log->user->email ?? '-' }}</td>
                <td>{{ $log->user->role ?? '-' }}</td>
                <td>{{ $log->user->is_active ? 'Active' : 'Inactive' }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->description ?? '-' }}</td>
                <td>{{ $log->ip_address }}</td>
                <td>{{ $log->user_agent }}</td>
                <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
        @empty
            <tr><td colspan="11" style="text-align:center;">No audit logs found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination">
        {{ $auditLogs->withQueryString()->links() }}
    </div>
</div>
</body>
</html>
