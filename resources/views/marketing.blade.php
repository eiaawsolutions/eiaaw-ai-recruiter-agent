<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EIAAW Recruiter — Autonomous Recruiting Agent</title>
    <meta name="description" content="Standalone autonomous recruiting agent by EIAAW. Verified candidates, human-approved outreach, plug into any company via REST + webhooks.">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded bg-slate-900 text-center font-semibold leading-8 text-white">R</div>
                <span class="font-semibold">EIAAW Recruiter</span>
            </div>
            <nav class="flex items-center gap-3 text-sm">
                <a href="/docs/openapi.yaml" class="text-slate-600 hover:text-slate-900">API</a>
                <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">Sign in</a>
                <a href="{{ route('onboarding.start') }}" class="rounded bg-slate-900 px-4 py-2 text-white">Get started</a>
            </nav>
        </div>
    </header>

    <section class="mx-auto max-w-6xl px-6 py-20">
        <h1 class="max-w-3xl text-4xl font-semibold tracking-tight md:text-5xl">An autonomous recruiting agent that never invents a candidate.</h1>
        <p class="mt-6 max-w-2xl text-lg text-slate-600">Source, screen, draft outreach, and schedule interviews — autonomously. Every candidate carries verification URLs. Every outreach passes through a human approval gate. Plug it into your stack via REST + signed webhooks, or run it standalone.</p>

        <div class="mt-10 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-sm font-medium text-slate-500">Verification-first</div>
                <p class="mt-2 text-slate-900">Server-side gate enforces ≥1 verification URL, discards low-confidence rows, strips guessed emails — before anything reaches your DB.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-sm font-medium text-slate-500">Human-in-the-loop</div>
                <p class="mt-2 text-slate-900">No outbound message leaves your tenant without an approval row. Outreach drafts are brand-grounded and cite one real source.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-sm font-medium text-slate-500">Integrate anything</div>
                <p class="mt-2 text-slate-900">REST endpoints, HMAC-signed outbound webhooks, idempotency keys, OpenAPI spec. Workforce handoff included.</p>
            </div>
        </div>

        <div class="mt-10 flex gap-3">
            <a href="{{ route('onboarding.start') }}" class="rounded-md bg-slate-900 px-5 py-3 text-white">Spin up a tenant</a>
            <a href="/docs/openapi.yaml" class="rounded-md border border-slate-300 bg-white px-5 py-3">Read API spec</a>
        </div>
    </section>
</body>
</html>
