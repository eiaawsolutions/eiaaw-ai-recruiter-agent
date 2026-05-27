<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Get started — EIAAW Recruiter</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
</head>
<body class="bg-slate-50">
<div class="mx-auto max-w-2xl px-6 py-12">
    <h1 class="text-3xl font-semibold tracking-tight">Spin up your recruiting agent.</h1>
    <p class="mt-2 text-slate-600">Two minutes. One tenant, one admin user, and (optionally) a website we'll parse for your brand voice.</p>

    @if ($errors->any())
        <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('onboarding.store') }}" class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-8">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">Company / tenant name</label>
                <input name="tenant_name" required value="{{ old('tenant_name') }}" class="mt-1 w-full rounded-md border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Timezone</label>
                <input name="timezone" value="{{ old('timezone', 'Asia/Kuala_Lumpur') }}" class="mt-1 w-full rounded-md border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Admin name</label>
                <input name="admin_name" required value="{{ old('admin_name') }}" class="mt-1 w-full rounded-md border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-medium">Admin email (also tenant contact)</label>
                <input name="contact_email" type="email" required value="{{ old('contact_email') }}" class="mt-1 w-full rounded-md border-slate-300">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Admin password</label>
                <input name="admin_password" type="password" required minlength="8" class="mt-1 w-full rounded-md border-slate-300">
                <p class="mt-1 text-xs text-slate-500">≥ 8 characters. Used only for the operator console.</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Brand website (optional)</label>
                <input name="brand_url" type="url" value="{{ old('brand_url') }}" placeholder="https://yourcompany.com" class="mt-1 w-full rounded-md border-slate-300">
                <p class="mt-1 text-xs text-slate-500">We'll fetch this and extract your brand voice + tone for outreach drafts.</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Brand voice (optional, short)</label>
                <input name="brand_voice" value="{{ old('brand_voice', 'professional, warm, no hype') }}" class="mt-1 w-full rounded-md border-slate-300">
            </div>
        </div>

        <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">Step 1 of 3</p>
            <button class="rounded-md bg-slate-900 px-6 py-2 text-white">Create tenant →</button>
        </div>
    </form>
</div>
</body>
</html>
