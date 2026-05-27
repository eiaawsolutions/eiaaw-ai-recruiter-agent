# Integrating EIAAW Recruiter

EIAAW Recruiter is standalone. Every company stack can integrate via three thin surfaces — there is no Workforce-specific coupling required.

## 1. REST API

**Base URL**: `https://recruiter.eiaawsolutions.com` (or self-hosted)

**Auth**: per-tenant API keys (`rcr_…`). Mint from the operator console; the plaintext is shown ONCE.

```
Authorization: Bearer rcr_abc123…
```

**Typical flow**:

```bash
# 1. Create a job
curl -X POST $BASE/api/v1/jobs \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{
    "title": "Senior Backend Engineer",
    "seniority": "senior",
    "work_mode": "remote",
    "country": "MY",
    "scope": "Lead the payments service rewrite.",
    "must_haves": ["5+ yrs Go", "designed distributed systems at scale"],
    "nice_to_haves": ["Postgres internals", "FinTech background"],
    "auto_source": true,
    "source_target": 12
  }'

# 2. Poll candidates as they arrive
curl $BASE/api/v1/candidates?job_id=<job_public_id> \
  -H "Authorization: Bearer $KEY"

# 3. Trigger screening on the ones you want
curl -X POST $BASE/api/v1/candidates/<cid>/screen \
  -H "Authorization: Bearer $KEY"

# 4. Draft outreach (queues the drafting agent)
curl -X POST $BASE/api/v1/candidates/<cid>/draft \
  -H "Authorization: Bearer $KEY" -d '{"instructions": "lead with the rewrite story"}'

# 5. Approve & send (human gate)
curl -X POST $BASE/api/v1/outreach/<oid>/approve \
  -H "Authorization: Bearer $KEY"
```

OpenAPI spec: `/docs/openapi.yaml`.

## 2. Outbound webhooks

Register an endpoint per environment:

```bash
curl -X POST $BASE/api/v1/webhook-endpoints \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"url": "https://your-app.example/webhooks/recruiter", "events": ["candidate.sourced","candidate.screened","outreach.sent","outreach.replied"]}'
```

The response contains the `secret` (shown ONCE). Every delivery sets:

```
X-EIAAW-Signature: sha256=<hex_hmac_sha256(secret, raw_body)>
X-EIAAW-Event: candidate.sourced
X-EIAAW-Delivery: <uuid>
Content-Type: application/json
```

Verify in your handler:

```php
$body = $request->getContent();
$expected = 'sha256=' . hash_hmac('sha256', $body, $YOUR_SECRET);
abort_unless(hash_equals($expected, $request->header('X-EIAAW-Signature')), 401);
```

Retries: exponential backoff up to 8 attempts (15s → ~1h). After abandonment the delivery row is preserved for re-drive from the operator UI.

## 3. Workforce-native handoff

If you're on EIAAW Workforce, `POST /api/v1/handoff/workforce/<candidate_public_id>` pushes a hired candidate into the existing OnboardingInvite flow. The call is HMAC-signed with the secret bound at `services.workforce.hmac_secret`.

## Authoring jobs from your own ATS

Send the `JobInput` schema (see `openapi.yaml`). If you want the recruiter to start sourcing immediately, set `auto_source: true`. Otherwise call `POST /jobs/<id>/source` later — useful when your ATS waits for HR approval before sourcing budget is released.

## What EIAAW Recruiter never does

- Fabricate emails or phone numbers.
- Send outreach without a recorded approval row.
- Cross-tenant data access (tenant scoping is baked into the global scope).
- Inject open- or click-tracking pixels into outbound mail.

## Email provider

Outbound mail and inbound replies both run through [Resend](https://resend.com).

- **Outbound**: `ResendOutreachSender` posts to `https://api.resend.com/emails`. Every send includes an `X-EIAAW-Outreach-Id` custom header so replies can be matched back even if the candidate's mail client mangles `In-Reply-To`.
- **Inbound + lifecycle webhook**: configure a single Resend webhook pointing at `POST https://<your-host>/webhooks/resend` with these events checked: `email.delivered`, `email.opened`, `email.clicked`, `email.bounced`, `email.complained`, `email.delivery_delayed`, **and** `email.received` (the inbound product). The endpoint verifies Svix signatures (`svix-id` / `svix-timestamp` / `svix-signature`) using the Resend signing secret resolved from `services.resend.webhook_signing_secret`.
