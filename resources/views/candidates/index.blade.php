@extends('layouts.app')
@section('title', 'Candidates')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Candidates</h1>
    <form method="GET" class="flex items-center gap-2">
        <select name="stage" class="rounded-md border-slate-300 text-sm">
            <option value="">All stages</option>
            @foreach (\App\Models\Candidate::STAGES as $s)
                <option value="{{ $s }}" @selected(request('stage') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">Filter</button>
    </form>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Name</th>
                <th class="px-5 py-3">Title / Company</th>
                <th class="px-5 py-3">Job</th>
                <th class="px-5 py-3">Stage</th>
                <th class="px-5 py-3">Confidence</th>
                <th class="px-5 py-3">Sources</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($candidates as $c)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3"><a href="{{ route('candidates.show', $c->public_id) }}" class="font-medium">{{ $c->name }}</a></td>
                    <td class="px-5 py-3 text-slate-600">{{ $c->title }} @if ($c->company) · {{ $c->company }} @endif</td>
                    <td class="px-5 py-3 text-slate-600">{{ $c->jobPosting?->title }}</td>
                    <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $c->stage }}</span></td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $c->confidence_score === 'High' ? 'bg-emerald-50 text-emerald-700' : ($c->confidence_score === 'Medium' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $c->confidence_score }}</span></td>
                    <td class="px-5 py-3 text-slate-600">{{ $c->sources->count() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No candidates yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $candidates->links() }}</div>
@endsection
