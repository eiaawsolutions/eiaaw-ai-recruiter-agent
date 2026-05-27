@extends('layouts.app')
@section('title', 'Brand profile')
@section('content')
<div class="mx-auto max-w-3xl">
    <p class="text-xs uppercase tracking-wide text-slate-500">Step 2 of 3</p>
    <h1 class="mt-1 text-2xl font-semibold">Brand profile</h1>
    <p class="mt-2 text-slate-600">If you gave us a URL on the previous step, the brand extractor is running in the background. You can re-run it any time below.</p>

    <div class="mt-6 grid gap-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h3 class="text-sm font-semibold">Current brand profile</h3>
            @if ($tenant->brand_profile)
                <pre class="mt-2 max-h-72 overflow-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($tenant->brand_profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="mt-2 text-sm text-slate-500">Nothing yet — the extractor hasn't completed or hasn't run.</p>
            @endif
            <p class="mt-2 text-xs text-slate-500">Voice: <span class="font-medium text-slate-700">{{ $tenant->brand_voice ?: '(unset)' }}</span></p>
        </div>

        <form method="POST" action="{{ route('onboarding.rerun_brand') }}" class="rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <h3 class="text-sm font-semibold">Re-run brand extraction</h3>
            <div class="mt-3 flex gap-3">
                <input name="brand_url" type="url" placeholder="https://yourcompany.com" required value="{{ data_get($tenant->brand_profile, 'source_url') }}" class="flex-1 rounded-md border-slate-300">
                <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Queue</button>
            </div>
        </form>

        <div class="flex justify-between">
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:underline">Skip</a>
            <a href="{{ route('onboarding.keys') }}" class="rounded-md bg-slate-900 px-6 py-2 text-sm text-white">Continue →</a>
        </div>
    </div>
</div>
@endsection
