@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
    <a href="{{ route('jobs.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white">+ New job</a>
</div>

<div class="grid gap-4 md:grid-cols-5">
    @foreach ([
        ['Open jobs', $stats['jobs_open']],
        ['Sourced', $stats['candidates_sourced']],
        ['Pending approvals', $stats['pending_approvals']],
        ['Outreach sent', $stats['outreach_sent']],
        ['Shortlisted', $stats['candidates_shortlisted']],
    ] as [$label, $value])
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-3 text-sm font-medium">Recent candidates</div>
        <div class="divide-y divide-slate-100">
            @forelse ($recentCandidates as $c)
                <a href="{{ route('candidates.show', $c->public_id) }}" class="flex items-center justify-between px-5 py-3 hover:bg-slate-50">
                    <div>
                        <div class="font-medium">{{ $c->name }}</div>
                        <div class="text-xs text-slate-500">{{ $c->title }} @if ($c->company) · {{ $c->company }} @endif</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $c->stage }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $c->confidence_score === 'High' ? 'bg-emerald-50 text-emerald-700' : ($c->confidence_score === 'Medium' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $c->confidence_score }}</span>
                    </div>
                </a>
            @empty
                <div class="px-5 py-6 text-sm text-slate-500">No candidates yet — create a job to source.</div>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-3 text-sm font-medium">Recent jobs</div>
        <div class="divide-y divide-slate-100">
            @forelse ($recentJobs as $j)
                <a href="{{ route('jobs.show', $j->public_id) }}" class="flex items-center justify-between px-5 py-3 hover:bg-slate-50">
                    <div>
                        <div class="font-medium">{{ $j->title }}</div>
                        <div class="text-xs text-slate-500">{{ $j->seniority }} · {{ $j->work_mode }} · {{ $j->location }}</div>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $j->status }}</span>
                </a>
            @empty
                <div class="px-5 py-6 text-sm text-slate-500">No jobs yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
