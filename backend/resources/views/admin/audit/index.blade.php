@extends('layouts.app')

@section('title', 'Audit Log')
@section('heading', 'Audit Log Aktivitas')

@section('content')
    <div class="card">
        <form method="GET" class="toolbar">
            <div>
                <label for="action">Aksi</label>
                <select id="action" name="action">
                    <option value="">Semua</option>
                    @foreach ($actions as $item)
                        <option value="{{ $item }}" @selected($action === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua</option>
                    <option value="success" @selected($status === 'success')>Berhasil</option>
                    <option value="failed" @selected($status === 'failed')>Gagal</option>
                </select>
            </div>
            <button type="submit" class="btn">Terapkan</button>
        </form>

        @if ($logs->isEmpty())
            <div class="empty">Belum ada aktivitas tercatat.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Aksi</th>
                    <th>Keterangan</th>
                    <th>IP</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? ($log->employee?->name ?? '—') }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td>{{ $log->description ?? '—' }}</td>
                        <td>{{ $log->ip_address ?? '—' }}</td>
                        <td><span class="badge {{ $log->status === 'success' ? 'success' : 'failed' }}">{{ $log->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
