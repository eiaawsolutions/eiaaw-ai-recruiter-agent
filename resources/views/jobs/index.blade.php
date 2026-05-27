@extends('layouts.app')
@section('title', 'Jobs')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Jobs</h1>
    <a href="{{ route('jobs.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white">+ New job</a>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Title</th>
                <th class="px-5 py-3">Seniority</th>
                <th class="px-5 py-3">Mode</th>
                <th class="px-5 py-3">Location</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Opened</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($jobs as $j)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3"><a href="{{ route('jobs.show', $j->public_id) }}" class="font-medium text-slate-900">{{ $j->title }}</a></td>
                    <td class="px-5 py-3 text-slate-600">{{ $j->seniority ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $j->work_mode ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $j->location ?? '—' }}</td>
                    <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $j->status }}</span></td>
                    <td class="px-5 py-3 text-slate-600">{{ optional($j->opened_at)->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $jobs->links() }}</div>
@endsection
