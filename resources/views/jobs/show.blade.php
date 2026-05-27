@extends('layouts.app')
@section('title', $job->title)
@section('content')
<div class="mb-6 flex items-start justify-between">
    <div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Job</div>
        <h1 class="text-2xl font-semibold">{{ $job->title }}</h1>
        <div class="mt-1 text-sm text-slate-600">{{ $job->seniority }} · {{ $job->work_mode }} · {{ $job->location }}</div>
    </div>
    <form method="POST" action="{{ route('jobs.source', $job->public_id) }}" class="flex items-center gap-2">
        @csrf
        <input type="number" name="target" value="10" min="1" max="50" class="w-20 rounded-md border-slate-300 text-sm">
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white">Source more</button>
    </form>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-1 space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="mb-2 text-sm font-semibold">Scope</h3>
            <p class="text-sm whitespace-pre-line text-slate-700">{{ $job->scope ?: '—' }}</p>
        </div>
        @foreach ([
            ['Must-haves', $job->must_haves],
            ['Nice-to-haves', $job->nice_to_haves],
            ['Disqualifiers', $job->disqualifiers],
        ] as [$label, $items])
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="mb-2 text-sm font-semibold">{{ $label }}</h3>
                @if (! empty($items))
                    <ul class="list-disc space-y-1 pl-4 text-sm text-slate-700">
                        @foreach ($items as $i) <li>{{ $i }}</li> @endforeach
                    </ul>
                @else
                    <div class="text-sm text-slate-500">—</div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-3 text-sm font-medium">Candidates ({{ $candidates->total() }})</div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Title / Company</th>
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
                            <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $c->stage }}</span></td>
                            <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $c->confidence_score === 'High' ? 'bg-emerald-50 text-emerald-700' : ($c->confidence_score === 'Medium' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $c->confidence_score }}</span></td>
                            <td class="px-5 py-3 text-slate-600">{{ $c->sources->count() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No candidates yet — click "Source more" to start.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $candidates->links() }}</div>
    </div>
</div>
@endsection
