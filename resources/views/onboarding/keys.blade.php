@extends('layouts.app')
@section('title', 'Keys & webhooks')
@section('content')
<div class="mx-auto max-w-3xl">
    <p class="text-xs uppercase tracking-wide text-slate-500">Step 3 of 3</p>
    <h1 class="mt-1 text-2xl font-semibold">Keys & webhooks</h1>
    <p class="mt-2 text-slate-600">Mint your first API key and (optionally) register a webhook target. Secrets are shown ONCE — copy them now.</p>

    @if ($plaintext)
        <div class="mt-4 rounded border border-emerald-300 bg-emerald-50 p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-emerald-700">New API key (shown once)</div>
            <code class="mt-2 block break-all rounded bg-white px-3 py-2 text-sm">{{ $plaintext }}</code>
        </div>
    @endif

    @if ($secret)
        <div class="mt-4 rounded border border-emerald-300 bg-emerald-50 p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-emerald-700">New webhook signing secret (shown once)</div>
            <code class="mt-2 block break-all rounded bg-white px-3 py-2 text-sm">{{ $secret }}</code>
        </div>
    @endif

    <div class="mt-6 grid gap-6">
        <form method="POST" action="{{ route('onboarding.mint_key') }}" class="rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <h3 class="text-sm font-semibold">Mint an API key</h3>
            <div class="mt-3 flex gap-3">
                <input name="label" required placeholder="e.g. Production ATS integration" class="flex-1 rounded-md border-slate-300">
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white">Mint</button>
            </div>
            @if ($apiKeys->isNotEmpty())
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    @foreach ($apiKeys as $k)
                        <li class="flex items-center justify-between py-2">
                            <span>{{ $k->label }} <span class="text-xs text-slate-400">····{{ $k->last_four }}</span></span>
                            <span class="text-xs text-slate-500">{{ $k->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </form>

        <form method="POST" action="{{ route('onboarding.register_webhook') }}" class="rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <h3 class="text-sm font-semibold">Register a webhook target (optional)</h3>
            <div class="mt-3 grid gap-3">
                <input name="url" type="url" required placeholder="https://your-app.example/webhooks/recruiter" class="rounded-md border-slate-300">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @foreach ([
                        'candidate.sourced','candidate.screened','outreach.drafted',
                        'outreach.pending_approval','outreach.sent','outreach.replied',
                        'interview.proposed','candidate.shortlisted','candidate.hired',
                    ] as $evt)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="events[]" value="{{ $evt }}" class="rounded border-slate-300" checked>
                            <span>{{ $evt }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white">Register</button>
            </div>
            @if ($hooks->isNotEmpty())
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    @foreach ($hooks as $h)
                        <li class="py-2"><span class="font-medium">{{ $h->url }}</span> <span class="text-xs text-slate-400">({{ count($h->events ?? []) }} events)</span></li>
                    @endforeach
                </ul>
            @endif
        </form>

        <div class="flex justify-between">
            <a href="{{ route('onboarding.brand') }}" class="text-sm text-slate-500 hover:underline">← Back</a>
            <form method="POST" action="{{ route('onboarding.finish') }}">
                @csrf
                <button class="rounded-md bg-slate-900 px-6 py-2 text-sm text-white">Finish & open dashboard</button>
            </form>
        </div>
    </div>
</div>
@endsection
