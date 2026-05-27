<?php

namespace App\Services\Agents;

use App\Models\AgentRun;
use App\Models\Candidate;
use App\Models\OutreachMessage;
use App\Support\TenantContext;

class DraftingAgent extends BaseAgent
{
    protected function agentName(): string { return 'drafting'; }

    public function draftOutreach(Candidate $candidate, ?string $instructions = null): OutreachMessage
    {
        return $this->track('draft_outreach', $candidate, function (AgentRun $run) use ($candidate, $instructions) {
            $tenant   = $candidate->tenant;
            $job      = $candidate->jobPosting;
            $sources  = $candidate->sources()->get(['kind', 'url', 'excerpt'])->toArray();
            $screening = $candidate->screening;

            $model = (string) config('services.anthropic.draft_model', 'claude-haiku-4-5-20251001');
            $run->update(['model' => $model]);

            $response = $this->client->messages(
                model:    $model,
                messages: [['role' => 'user', 'content' => $this->userPrompt($candidate, $sources, $screening, $instructions)]],
                system:   $this->systemPrompt($tenant, $job),
                tools:    [$this->draft_tool()],
                maxTokens: 1500,
                temperature: 0.55,
            );

            $tool = AnthropicClient::extractToolUse($response, 'submit_outreach') ?? [];

            $message = OutreachMessage::create([
                'tenant_id'      => TenantContext::id() ?? $candidate->tenant_id,
                'candidate_id'   => $candidate->id,
                'job_posting_id' => $candidate->job_posting_id,
                'channel'        => 'email',
                'direction'      => 'outbound',
                'subject'        => $tool['subject']  ?? "Opportunity: {$job->title}",
                'body'           => $tool['body']     ?? '',
                'variables'      => $tool['variables'] ?? null,
                'from_address'   => $tenant->default_outreach_from ?? config('mail.from.address'),
                'to_address'     => $candidate->email !== '' ? $candidate->email : null,
                'reply_to'       => $tenant->default_outreach_from ?? config('mail.from.address'),
                'status'         => 'drafted',
                'model_used'     => $model,
            ]);

            $stage = config('services.recruiter.require_approval', true) || $tenant->require_approval
                ? 'outreach_pending_approval'
                : 'outreach_drafted';
            $candidate->update(['stage' => $stage]);

            $usage = $response['usage'] ?? [];
            $run->update([
                'input_tokens'  => $usage['input_tokens']  ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
                'output_meta'   => ['outreach_id' => $message->id],
            ]);

            return $message;
        });
    }

    private function systemPrompt($tenant, $job): string
    {
        $voice   = $tenant->brand_voice ?: 'professional, warm, no hype';
        $sig     = $tenant->default_outreach_signature ?: $tenant->name;
        $company = $tenant->name;

        return <<<PROMPT
You are EIAAW Recruiter's Drafting Agent for {$company}. You draft outreach
to candidates the operator has approved into the pipeline. Tone: {$voice}.

Rules:
- Address the candidate by first name. Never use "Dear Candidate".
- Reference ONE specific, real detail from the candidate's verification
  sources to prove relevance — never invent claims about them.
- Subject line: <= 70 chars, no clickbait, no emojis unless the tenant's
  brand voice explicitly uses them.
- Body: 4-7 short sentences. State the role, why them, the ask (15-min chat
  or async reply). End with the signature: "{$sig}".
- Never claim a salary band the job description does not provide.
- Never imply the candidate already applied.
- No tracking pixels. No URL trackers.

Output via the `submit_outreach` tool only.
PROMPT;
    }

    private function userPrompt(Candidate $candidate, array $sources, $screening, ?string $instructions): string
    {
        $sourcesJson = json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $summary = $screening->summary ?? '';
        $score   = $screening->overall_score ?? 'n/a';
        $extra   = $instructions ? "Operator instructions:\n{$instructions}\n" : '';

        return <<<USER
JOB
Title: {$candidate->jobPosting->title}
Scope: {$candidate->jobPosting->scope}

CANDIDATE
Name: {$candidate->name}
Title: {$candidate->title}
Company: {$candidate->company}
Location: {$candidate->location}
Reason for fit: {$candidate->reason_for_fit}

SCREENING (score: {$score})
{$summary}

VERIFICATION SOURCES (anchor your one specific reference here)
{$sourcesJson}

{$extra}
Draft the outreach email via the `submit_outreach` tool.
USER;
    }

    private function draft_tool(): array
    {
        return [
            'name' => 'submit_outreach',
            'description' => 'Submit a finished outreach email draft.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'subject'   => ['type' => 'string', 'maxLength' => 90],
                    'body'      => ['type' => 'string'],
                    'variables' => ['type' => 'object'],
                ],
                'required' => ['subject', 'body'],
            ],
        ];
    }
}
