@extends('layouts.app')
@section('title', $candidate->name)
@section('content')
<div class="mb-6 flex items-start justify-between">
    <div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Candidate</div>
        <h1 class="text-2xl font-semibold">{{ $candidate->name }}</h1>
        <div class="mt-1 text-sm text-slate-600">{{ $candidate->title }} @if ($candidate->company) · {{ $candidate->company }} @endif</div>
        <div class="mt-2 text-xs">
            <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ $candidate->stage }}</span>
            <span class="rounded-full px-2 py-0.5 {{ $candidate->confidence_score === 'High' ? 'bg-emerald-50 text-emerald-700' : ($candidate->confidence_score === 'Medium' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $candidate->confidence_score }}</span>
            <span class="rounded-full px-2 py-0.5 {{ $candidate->lead_temperature === 'Hot' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600' }}">{{ $candidate->lead_temperature }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <form method="POST" action="{{ route('candidates.screen', $candidate->public_id) }}">
            @csrf
            <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">Screen</button>
        </form>
        <form method="POST" action="{{ route('candidates.draft', $candidate->public_id) }}">
            @csrf
            <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">Draft outreach</button>
        </form>
        <form method="POST" action="{{ route('candidates.shortlist', $candidate->public_id) }}">
            @csrf
            <button class="rounded-md bg-emerald-600 px-3 py-2 text-sm text-white">Shortlist</button>
        </form>
        <form method="POST" action="{{ route('candidates.discard', $candidate->public_id) }}">
            @csrf
            <button class="rounded-md bg-rose-600 px-3 py-2 text-sm text-white">Discard</button>
        </form>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="mb-2 text-sm font-semibold">Identity & contact</h3>
            <dl class="space-y-1 text-sm">
                <div><dt class="inline text-slate-500">Location:</dt> <dd class="inline">{{ $candidate->location ?: '—' }}</dd></div>
                <div><dt class="inline text-slate-500">Email:</dt> <dd class="inline">{{ $candidate->email ?: '— (not verified)' }}</dd></div>
                <div><dt class="inline text-slate-500">Phone:</dt> <dd class="inline">{{ $candidate->phone ?: '—' }}</dd></div>
                <div><dt class="inline text-slate-500">LinkedIn:</dt> <dd class="inline">@if ($candidate->linkedin_url)<a href="{{ $candidate->linkedin_url }}" target="_blank" rel="noopener" class="text-sky-700 underline">profile</a>@else — @endif</dd></div>
                <div><dt class="inline text-slate-500">Website:</dt> <dd class="inline">@if ($candidate->company_website)<a href="{{ $candidate->company_website }}" target="_blank" rel="noopener" class="text-sky-700 underline">website</a>@else — @endif</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="mb-2 text-sm font-semibold">Why this candidate</h3>
            <p class="text-sm text-slate-700">{{ $candidate->reason_for_fit ?: '—' }}</p>
            @if ($candidate->buying_signal)
                <div class="mt-3 rounded bg-rose-50 px-3 py-2 text-xs text-rose-800"><span class="font-medium">Buying signal:</span> {{ $candidate->buying_signal }}</div>
            @endif
        </div>
    </div>

    <div class="space-y-4 lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="mb-3 text-sm font-semibold">Verification sources ({{ $candidate->sources->count() }})</h3>
            <ul class="space-y-2 text-sm">
                @forelse ($candidate->sources as $s)
                    <li class="rounded bg-slate-50 px-3 py-2">
                        <div class="flex items-center justify-between">
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">{{ $s->kind }}</span>
                            <a href="{{ $s->url }}" target="_blank" rel="noopener" class="text-sky-700 underline">{{ \Illuminate\Support\Str::limit($s->url, 80) }}</a>
                        </div>
                        @if ($s->excerpt) <div class="mt-1 text-xs text-slate-600">{{ $s->excerpt }}</div> @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No sources recorded.</li>
                @endforelse
            </ul>
        </div>

        @if ($candidate->screening)
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Screening</h3>
                    <span class="text-2xl font-semibold">{{ $candidate->screening->overall_score }}<span class="text-sm text-slate-400">/100</span></span>
                </div>
                <p class="mt-2 text-sm text-slate-700">{{ $candidate->screening->summary }}</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <div class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">Must-haves</div>
                        <ul class="space-y-1 text-sm">
                            @foreach ($candidate->screening->must_have_matches ?? [] as $m)
                                <li class="flex gap-2">
                                    <span class="{{ ($m['match'] ?? false) ? 'text-emerald-600' : 'text-rose-600' }}">{{ ($m['match'] ?? false) ? '✓' : '✗' }}</span>
                                    <span>{{ $m['requirement'] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <div class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">Risk flags</div>
                        <ul class="space-y-1 text-sm">
                            @forelse ($candidate->screening->risk_flags ?? [] as $r)
                                <li class="text-rose-700">⚠ {{ $r }}</li>
                            @empty
                                <li class="text-slate-500">None</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @if ($candidate->interviews->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="mb-3 text-sm font-semibold">Interview slots</h3>
                <ul class="space-y-2 text-sm">
                    @foreach ($candidate->interviews as $slot)
                        <li class="flex items-center justify-between rounded bg-slate-50 px-3 py-2">
                            <div>
                                <div class="font-medium">{{ $slot->starts_at->toDayDateTimeString() }}</div>
                                <div class="text-xs text-slate-500">{{ $slot->stage }} · {{ $slot->location_kind }} @if ($slot->meeting_url) · <a href="{{ $slot->meeting_url }}" target="_blank" rel="noopener" class="text-sky-700 underline">link</a> @endif</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">{{ $slot->status }}</span>
                                @if ($slot->status === 'proposed')
                                    <form method="POST" action="{{ route('interviews.confirm', $slot->public_id) }}">
                                        @csrf
                                        <button class="rounded-md bg-emerald-600 px-3 py-1 text-xs text-white">Confirm</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($candidate->outreachMessages->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="mb-3 text-sm font-semibold">Outreach</h3>
                <ul class="space-y-2 text-sm">
                    @foreach ($candidate->outreachMessages as $m)
                        <li class="flex items-center justify-between rounded bg-slate-50 px-3 py-2">
                            <a href="{{ route('outreach.show', $m->public_id) }}" class="font-medium">{{ $m->subject }}</a>
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">{{ $m->status }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
