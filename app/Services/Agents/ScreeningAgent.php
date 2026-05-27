<?php

namespace App\Services\Agents;

use App\Models\AgentRun;
use App\Models\Candidate;
use App\Models\ScreeningResult;
use App\Support\TenantContext;

class ScreeningAgent extends BaseAgent
{
    protected function agentName(): string { return 'screening'; }

    public function screen(Candidate $candidate): ScreeningResult
    {
        return $this->track('screen_candidate', $candidate, function (AgentRun $run) use ($candidate) {
            $job   = $candidate->jobPosting;
            $model = (string) config('services.anthropic.reasoning_model', 'claude-opus-4-7');
            $run->update(['model' => $model]);

            $sources = $candidate->sources()->get(['kind', 'url', 'excerpt'])->toArray();

            $response = $this->client->messages(
                model:    $model,
                messages: [['role' => 'user', 'content' => $this->userPrompt($candidate, $sources)]],
                system:   $this->systemPrompt($job),
                tools:    [$this->screen_tool()],
                maxTokens: 4096,
                temperature: 0.0,
            );

            $tool = AnthropicClient::extractToolUse($response, 'submit_screening') ?? [];

            $result = ScreeningResult::updateOrCreate(
                [
                    'candidate_id'  => $candidate->id,
                    'job_posting_id' => $candidate->job_posting_id,
                ],
                [
                    'tenant_id'           => TenantContext::id() ?? $candidate->tenant_id,
                    'overall_score'       => max(0, min(100, (int) ($tool['overall_score'] ?? 0))),
                    'must_have_matches'   => $tool['must_have_matches']    ?? [],
                    'nice_to_have_matches' => $tool['nice_to_have_matches'] ?? [],
                    'disqualifier_hits'   => $tool['disqualifier_hits']    ?? [],
                    'risk_flags'          => $tool['risk_flags']           ?? [],
                    'summary'             => $tool['summary']              ?? null,
                    'model_used'          => $model,
                    'model_meta'          => ['usage' => $response['usage'] ?? null],
                ]
            );

            if ($candidate->stage === 'sourced') {
                $candidate->update(['stage' => 'screened']);
            }

            $usage = $response['usage'] ?? [];
            $run->update([
                'input_tokens'  => $usage['input_tokens']  ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
                'output_meta'   => ['screening_id' => $result->id, 'score' => $result->overall_score],
            ]);

            return $result;
        });
    }

    private function systemPrompt($job): string
    {
        return <<<PROMPT
You are EIAAW Recruiter's Screening Agent. You evaluate one candidate against
one job. Every claim you make MUST cite the source URL it came from
(verification_sources passed in the user message). Never invent matches.

Rules:
- For each must-have, set match=true ONLY if you can cite an evidence URL +
  exact excerpt. Otherwise match=false (do not assume).
- disqualifier_hits: list disqualifiers actually triggered, with evidence.
- risk_flags: ["short_tenure","unexplained_gap","jurisdiction_mismatch",...]
- overall_score: 0-100. Anchor: 90+ exceptional fit, 70-89 strong, 50-69 mixed,
  below 50 weak.
- summary: 2-3 sentences for a human recruiter.

Output via the `submit_screening` tool only.
PROMPT;
    }

    private function userPrompt(Candidate $candidate, array $sources): string
    {
        $job  = $candidate->jobPosting;
        $must = json_encode($job->must_haves ?? [],   JSON_UNESCAPED_SLASHES);
        $nice = json_encode($job->nice_to_haves ?? [], JSON_UNESCAPED_SLASHES);
        $disq = json_encode($job->disqualifiers ?? [], JSON_UNESCAPED_SLASHES);
        $src  = json_encode($sources,                  JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<USER
JOB
Title: {$job->title}
Scope: {$job->scope}
Must-haves: {$must}
Nice-to-haves: {$nice}
Disqualifiers: {$disq}

CANDIDATE
Name: {$candidate->name}
Title: {$candidate->title}
Company: {$candidate->company}
Location: {$candidate->location}
Reason for fit (from sourcing): {$candidate->reason_for_fit}

VERIFICATION SOURCES (cite these by URL when claiming a match)
{$src}

Produce the screening via the `submit_screening` tool.
USER;
    }

    private function screen_tool(): array
    {
        return [
            'name'        => 'submit_screening',
            'description' => 'Submit a row-cited screening result.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'overall_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'summary'       => ['type' => 'string'],
                    'must_have_matches' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'requirement'  => ['type' => 'string'],
                                'match'        => ['type' => 'boolean'],
                                'evidence_url' => ['type' => 'string'],
                                'excerpt'      => ['type' => 'string'],
                            ],
                            'required' => ['requirement', 'match'],
                        ],
                    ],
                    'nice_to_have_matches' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'requirement'  => ['type' => 'string'],
                                'match'        => ['type' => 'boolean'],
                                'evidence_url' => ['type' => 'string'],
                                'excerpt'      => ['type' => 'string'],
                            ],
                            'required' => ['requirement', 'match'],
                        ],
                    ],
                    'disqualifier_hits' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'disqualifier' => ['type' => 'string'],
                                'evidence_url' => ['type' => 'string'],
                                'excerpt'      => ['type' => 'string'],
                            ],
                            'required' => ['disqualifier'],
                        ],
                    ],
                    'risk_flags' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['overall_score', 'summary', 'must_have_matches'],
            ],
        ];
    }
}
