# EIAAW Recruiter — Standalone Autonomous Recruiting Agent

A multi-tenant, plug-into-anything autonomous recruiting agent powered by EIAAW. Sources, screens, drafts outreach, schedules interviews — and never invents a candidate.

## What it does

| Stage | Owner | Surface |
|---|---|---|
| Source candidates (verified URLs, no guessed emails) | `SourcingAgent` (Claude Opus 4.7) | `POST /api/v1/jobs/{id}/source` |
| Screen against JD with row-level citations | `ScreeningAgent` | `POST /api/v1/candidates/{id}/screen` |
| Draft brand-grounded outreach | `DraftingAgent` (Claude Haiku 4.5) | `POST /api/v1/candidates/{id}/draft` |
| Approve & send (Resend) | Human → `ResendOutreachSender` | `POST /api/v1/outreach/{id}/approve` |
| Propose interview slots from candidate reply | `SchedulingAgent` | Triggered by Resend `email.received` webhook |
| Handoff to EIAAW Workforce onboarding | `WorkforceHandoff` | `POST /api/v1/handoff/workforce/{id}` |

Every external system can integrate via REST + HMAC-signed outbound webhooks. See `docs/INTEGRATION.md` and `public/docs/openapi.yaml`.

## Non-negotiable contracts

**Lead Generation Contract** — enforced server-side by `App\Services\Verification\LeadVerificationGate` regardless of what the agent returns:

1. Every candidate has ≥1 verifiable source URL.
2. Emails / phones are never guessed. Guessed-pattern emails are stripped on the way in.
3. Low-confidence rows are discarded.
4. Hot leads must declare a `buying_signal`.
5. Every row carries a `reason_for_fit`.

**EIAAW Deploy Contract** — every production secret beyond the Infisical bootstrap creds lives in Infisical and is referenced via `secret://project/env/path/NAME` handles. `App\Providers\SecretsServiceProvider` resolves them at boot. See `.env.example`.

**Human approval gate** — no outbound message leaves a tenant without an `Approval` row created by an operator. `SendOutreachJob` is defensive: it refuses to send anything not in status `approved`.

## Multi-tenancy

Every domain table has `tenant_id` with FK + index. `App\Models\Concerns\BelongsToTenant` adds a global scope keyed off `App\Support\TenantContext`, which is bound by:

- `VerifyApiKey` middleware for `/api/v1/*` requests
- `EnforceTenantScope` middleware for UI requests (derived from the authenticated user)
- Queue jobs bind explicitly via `TenantContext::bindById($id)` before doing domain work

Creating a model without a bound tenant throws — cross-tenant writes are impossible by construction.

## Local quickstart

```bash
composer install
cp .env.example .env
php artisan key:generate
# default DB is pgsql; for local SQLite:
touch database/database.sqlite
sed -i 's/^DB_CONNECTION=pgsql/DB_CONNECTION=sqlite/' .env
sed -i 's|^DB_DATABASE=recruiter|DB_DATABASE='"$(pwd)"'/database/database.sqlite|' .env

php artisan migrate
php artisan recruiter:smoke --seed   # creates a demo tenant + prints an API key

composer dev   # serves web + queue + log tailer
```

Open `http://localhost:8000` → sign in with `demo@example.com` / `demo-password`.

## Tests

```bash
composer test
```

Coverage:

- `LeadVerificationGate` — accept/reject/strip behavior
- `InfisicalResolver` — handle parsing
- API key auth + cross-tenant isolation
- Webhook HMAC signature
- Job creation + validation

## Deploy

Railway / any Nixpacks host:

```bash
railway up
```

`nixpacks.toml` builds PHP 8.2 + composer; `Procfile` defines `web` and `worker`. Per the EIAAW Deploy Contract, set only the Infisical bootstrap env vars (`INFISICAL_APP_CLIENT_ID`, `INFISICAL_APP_CLIENT_SECRET`, `INFISICAL_PROJECT_ID`). Everything else is resolved at boot.

## Architecture (one screen)

```
┌────────────────── External system / EIAAW Workforce / Browser ──────────────────┐
│                                                                                  │
│   REST (Bearer rcr_…)          UI (session+Sanctum)         Inbound webhooks    │
│        │                              │                            │            │
└────────┼──────────────────────────────┼────────────────────────────┼────────────┘
         ▼                              ▼                            ▼
   VerifyApiKey               EnforceTenantScope            VerifyResendSignature (Svix)
         │                              │                            │
         └──────────────┬───────────────┘                            │
                        ▼                                            │
              TenantContext::bind                                    │
                        │                                            │
        ┌───────────────┼─────────────────────────┐                  │
        ▼               ▼                         ▼                  ▼
    Controllers     Queue jobs                 Approval gate    Inbound webhook ingest
        │               │                         │                  │
        ▼               ▼                         ▼                  ▼
     Eloquent     SourcingAgent           OutreachMessage        Update OutreachMessage
     (tenant-     ScreeningAgent          (status=approved)      events / replied state
      scoped)     DraftingAgent                  │                       │
                  SchedulingAgent                ▼                       │
                  → AnthropicClient        ResendOutreachSender          │
                  → LeadVerificationGate        │                        │
                                                ▼                        ▼
                                          Outbound webhooks (HMAC-SHA256 signed)
                                                │
                                                ▼
                                          DeliverWebhookJob (exp backoff retries)
```
