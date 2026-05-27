@extends('layouts.app')
@section('title', 'Outreach')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Outreach</h1>
    <form method="GET" class="flex items-center gap-2">
        <select name="status" class="rounded-md border-slate-300 text-sm">
            <option value="">All statuses</option>
            @foreach (['drafted','pending_approval','approved','sent','delivered','opened','replied','bounced','failed','suppressed'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">Filter</button>
    </form>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Subject</th>
                <th class="px-5 py-3">To</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Created</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($messages as $m)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3"><a href="{{ route('outreach.show', $m->public_id) }}" class="font-medium">{{ $m->subject }}</a></td>
                    <td class="px-5 py-3 text-slate-600">{{ $m->candidate?->name }} @if ($m->to_address) <span class="text-slate-400">· {{ $m->to_address }}</span> @endif</td>
                    <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $m->status }}</span></td>
                    <td class="px-5 py-3 text-slate-600">{{ $m->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No outreach yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $messages->links() }}</div>
@endsection
