<?php

namespace App\Services\Agents;

use App\Models\AgentRun;
use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\JobPosting;
use App\Services\Verification\LeadVerificationGate;
use App\Services\Verification\VerificationOutcome;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * SourcingAgent — given a JobPosting, produces a batch of verified candidates.
 *
 * The agent prompt enforces the EIAAW Lead Generation Contract; the
 * LeadVerificationGate is a server-side belt-and-braces gate that runs
 * AFTER the agent responds and BEFORE anything is persisted.
 *
 * No guessed contact data is ever stored.
 */
class SourcingAgent extends BaseAgent
{
    public function __construct(
        AnthropicClient $client,
        private readonly LeadVerificationGate $gate,
    ) {
        parent::__construct($client);
    }

    protected function agentName(): string { return 'sourcing'; }

    /**
     * Source candidates for a JobPosting.
     *
     * @return array{accepted: Candidate[], outcome: VerificationOutcome, agent_run: AgentRun}
     */
    public function source(JobPosting $job, int $target = 10): array
    {
        return $this->track('source_candidates', $job, function (AgentRun $run) use ($job, $target) {
            $target = max(1, min(
                $target,
                (int) config('services.recruiter.max_sourced_per_job', 50),
            ));

            $model = (string) config('services.anthropic.reasoning_model', 'claude-opus-4-7');
            $run->update(['model' => $model, 'input_meta' => array_merge($run->input_meta ?? [], [
                'job_public_id' => $job->public_id,
                'target_count'  => $target,
            ])]);

            $response = $this->client->messages(
                model:       $model,
                messages:    [['role' => 'user', 'content' => $this->buildUserPrompt($job, $target)]],
                system:      $this->systemPrompt(),
                tools:       [$this->propose_candidates_tool()],
                maxTokens:   8192,
                temperature: 0.2,
            );

            $tool = AnthropicClient::extractToolUse($response, 'propose_candidates');
            $rows = $tool['candidates'] ?? [];

            $outcome = $this->gate->verifyBatch($rows);
            $accepted = $this->persist($job, $outcome->accepted);

            $usage = $response['usage'] ?? [];
            $run->update([
                'input_tokens'         => $usage['input_tokens']  ?? null,
                'output_tokens'        => $usage['output_tokens'] ?? null,
                'output_meta'          => ['returned_count' => count($rows)],
                'verification_summary' => $outcome->summary(),
                'status'               => $outcome->accepted ? AgentRun::STATUS_SUCCEEDED : AgentRun::STATUS_PARTIAL,
            ]);

            return [
                'accepted'  => $accepted,
                'outcome'   => $outcome,
                'agent_run' => $run->refresh(),
            ];
        });
    }

    /** @param array<int,array<string,mixed>> $rows  @return Candidate[] */
    private function persist(JobPosting $job, array $rows): array
    {
        $tenantId = TenantContext::id() ?? $job->tenant_id;
        $out      = [];

        DB::transaction(function () use ($job, $rows, $tenantId, &$out) {
            foreach ($rows as $row) {
                $candidate = Candidate::create([
                    'tenant_id'        => $tenantId,
                    'job_posting_id'   => $job->id,
                    'name'             => $row['name'],
                    'title'            => $row['title']            ?? null,
                    'company'          => $row['company']          ?? null,
                    'location'         => $row['location']         ?? null,
                    'country'          => $row['country']          ?? null,
                    'email'            => $row['email']            ?? '',
                    'phone'            => $row['phone']            ?? '',
                    'linkedin_url'     => $row['linkedin_url']     ?? null,
                    'company_website'  => $row['company_website']  ?? null,
                    'other_contacts'   => $row['other_contacts']   ?? null,
                    'candidate_type'   => $row['type']             ?? 'B2C',
                    'lead_temperature' => $row['lead_temperature'] ?? 'Cold',
                    'confidence_score' => $row['confidence_score'] ?? 'Medium',
                    'reason_for_fit'   => $row['reason_for_fit']   ?? null,
                    'buying_signal'    => $row['buying_signal']    ?? null,
                    'enrichment'       => $row['enrichment']       ?? null,
                    'source'           => $row['source']           ?? 'sourcing_agent',
                    'stage'            => 'sourced',
                ]);

                foreach ($row['verification_sources'] ?? [] as $src) {
                    CandidateSource::create([
                        'tenant_id'     => $tenantId,
                        'candidate_id'  => $candidate->id,
                        'kind'          => $src['kind']    ?? 'other',
                        'url'           => $src['url'],
                        'excerpt'       => $src['excerpt'] ?? null,
                        'verified_at'   => now(),
                    ]);
                }

                $out[] = $candidate;
            }
        });

        return $out;
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are EIAAW Recruiter's Sourcing Agent. You produce candidate leads that
strictly comply with the EIAAW Lead Generation Contract:

NON-NEGOTIABLE RULES
1. Never fabricate, assume, or infer data without evidence.
2. Every candidate MUST have a verifiable digital footprint.
3. Emails and phone numbers MUST NOT be guessed. If no verified email is
   visible on a credible public source, leave the email field as "".
4. If verification is weak or unclear, DISCARD the candidate — do not return.
5. Fewer high-quality candidates are better than many unverified ones.

REQUIRED VERIFICATION (>=1, prefer >=2)
- LinkedIn profile URL
- Official company website (team page, author page, listing)
- Verified social account clearly tied to the identity
- Media mention (news, interview, speaker profile)
- Public directory or marketplace listing
- A verified email published on a credible source

CLASSIFICATION
- type: B2B | B2C | B2B2C (recruiting individuals is typically B2C)
- lead_temperature: Hot (active intent signal) | Cold (good ICP fit, no intent)
- confidence_score: High | Medium | Low

MANDATORY BEFORE RETURNING ANY CANDIDATE
- Does this person have a real, traceable public presence?
- Is their association with company/role clearly proven by a source URL?
- Is ANY contact info assumed or guessed? If yes, remove the field.
- Is this candidate genuinely relevant to the job description?

If any answer is "no", DO NOT return that candidate.

Return candidates via the `propose_candidates` tool. Quality over quantity.
PROMPT;
    }

    private function buildUserPrompt(JobPosting $job, int $target): string
    {
        $must = $job->must_haves ? "Must-haves:\n- " . implode("\n- ", $job->must_haves) : '';
        $nice = $job->nice_to_haves ? "Nice-to-haves:\n- " . implode("\n- ", $job->nice_to_haves) : '';
        $disq = $job->disqualifiers ? "Disqualifiers:\n- " . implode("\n- ", $job->disqualifiers) : '';

        return <<<USER
Source up to {$target} candidates for the following role.

Role: {$job->title}
Seniority: {$job->seniority}
Work mode: {$job->work_mode}
Location: {$job->location} ({$job->country})
Department: {$job->department}

Scope:
{$job->scope}

{$must}

{$nice}

{$disq}

Constraints:
- Every candidate MUST have >=1 verification source URL.
- Never guess emails. Leave email = "" if not verifiably published.
- Discard any candidate you cannot verify.
- Prefer fewer high-confidence candidates over many low-confidence ones.

Return the result via the `propose_candidates` tool only.
USER;
    }

    /** @return array<string,mixed> */
    private function propose_candidates_tool(): array
    {
        return [
            'name'        => 'propose_candidates',
            'description' => 'Submit a batch of verified candidate leads. Each candidate MUST satisfy the EIAAW Lead Generation Contract.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'candidates' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'             => ['type' => 'string'],
                                'title'            => ['type' => 'string'],
                                'company'          => ['type' => 'string'],
                                'location'         => ['type' => 'string'],
                                'country'          => ['type' => 'string', 'description' => 'ISO 3166-1 alpha-2'],
                                'email'            => ['type' => 'string', 'description' => 'Verified email only; "" if not verified.'],
                                'phone'            => ['type' => 'string', 'description' => 'Publicly listed only; "" otherwise.'],
                                'linkedin_url'     => ['type' => 'string'],
                                'company_website'  => ['type' => 'string'],
                                'other_contacts'   => ['type' => 'object'],
                                'type'             => ['type' => 'string', 'enum' => ['B2B', 'B2C', 'B2B2C']],
                                'lead_temperature' => ['type' => 'string', 'enum' => ['Hot', 'Cold']],
                                'confidence_score' => ['type' => 'string', 'enum' => ['High', 'Medium', 'Low']],
                                'reason_for_fit'   => ['type' => 'string'],
                                'buying_signal'    => ['type' => 'string', 'description' => 'Required when lead_temperature=Hot.'],
                                'verification_sources' => [
                                    'type'  => 'array',
                                    'items' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'kind'    => ['type' => 'string'],
                                            'url'     => ['type' => 'string'],
                                            'excerpt' => ['type' => 'string'],
                                        ],
                                        'required' => ['url'],
                                    ],
                                ],
                                'enrichment' => ['type' => 'object'],
                                'source'     => ['type' => 'string'],
                            ],
                            'required' => ['name', 'reason_for_fit', 'verification_sources', 'confidence_score'],
                        ],
                    ],
                ],
                'required' => ['candidates'],
            ],
        ];
    }
}
