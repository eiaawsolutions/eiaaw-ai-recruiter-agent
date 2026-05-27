@extends('layouts.app')
@section('title', 'New job')
@section('content')
<h1 class="mb-6 text-2xl font-semibold">New job</h1>

<form method="POST" action="{{ route('jobs.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
    @csrf
    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium">Title</label>
            <input name="title" required value="{{ old('title') }}" class="mt-1 w-full rounded-md border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium">Department</label>
            <input name="department" value="{{ old('department') }}" class="mt-1 w-full rounded-md border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium">Seniority</label>
            <select name="seniority" class="mt-1 w-full rounded-md border-slate-300">
                <option value="">—</option>
                @foreach (['intern','junior','mid','senior','staff','principal'] as $s)
                    <option value="{{ $s }}" @selected(old('seniority') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Work mode</label>
            <select name="work_mode" class="mt-1 w-full rounded-md border-slate-300">
                <option value="">—</option>
                @foreach (['remote','hybrid','onsite'] as $m)
                    <option value="{{ $m }}" @selected(old('work_mode') === $m)>{{ ucfirst($m) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Location</label>
            <input name="location" value="{{ old('location') }}" class="mt-1 w-full rounded-md border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium">Country (ISO-2)</label>
            <input name="country" maxlength="2" value="{{ old('country') }}" class="mt-1 w-full rounded-md border-slate-300 uppercase">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium">Scope / description</label>
        <textarea name="scope" rows="5" class="mt-1 w-full rounded-md border-slate-300">{{ old('scope') }}</textarea>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium">Must-haves (one per line)</label>
            <textarea name="must_haves" rows="5" class="mt-1 w-full rounded-md border-slate-300">{{ old('must_haves') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Nice-to-haves</label>
            <textarea name="nice_to_haves" rows="5" class="mt-1 w-full rounded-md border-slate-300">{{ old('nice_to_haves') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Disqualifiers</label>
            <textarea name="disqualifiers" rows="5" class="mt-1 w-full rounded-md border-slate-300">{{ old('disqualifiers') }}</textarea>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-4">
        <div>
            <label class="block text-sm font-medium">Currency</label>
            <input name="comp_currency" maxlength="8" value="{{ old('comp_currency','USD') }}" class="mt-1 w-full rounded-md border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium">Comp min</label>
            <input name="comp_min" type="number" value="{{ old('comp_min') }}" class="mt-1 w-full rounded-md border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium">Comp max</label>
            <input name="comp_max" type="number" value="{{ old('comp_max') }}" class="mt-1 w-full rounded-md border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium">Period</label>
            <select name="comp_period" class="mt-1 w-full rounded-md border-slate-300">
                @foreach (['year','month','day','hour'] as $p)
                    <option value="{{ $p }}" @selected(old('comp_period','year') === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-center gap-6 rounded-md bg-slate-50 p-4">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="auto_source" value="1" class="rounded border-slate-300">
            <span class="text-sm">Start sourcing immediately</span>
        </label>
        <div>
            <label class="block text-xs text-slate-500">Target count</label>
            <input name="source_target" type="number" value="10" min="1" max="50" class="w-24 rounded-md border-slate-300">
        </div>
    </div>

    <div class="flex justify-end">
        <button class="rounded-md bg-slate-900 px-5 py-2 text-white">Create job</button>
    </div>
</form>
@endsection
