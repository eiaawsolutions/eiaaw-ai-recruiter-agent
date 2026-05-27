<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Verification\LeadVerificationGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SmokeCommand extends Command
{
    protected $signature = 'recruiter:smoke {--seed : Create a demo tenant + API key for local testing}';
    protected $description = 'Smoke-test the recruiter app (DB, gate, agents config) per EIAAW pre-push policy.';

    public function handle(): int
    {
        $this->info('EIAAW Recruiter — smoke test');

        // 1. DB connectivity
        try {
            $tables = ['tenants','users','api_keys','job_postings','candidates','candidate_sources','outreach_messages','interview_slots','approvals','webhook_endpoints','webhook_deliveries','agent_runs','idempotency_keys'];
            foreach ($tables as $t) {
                if (! Schema::hasTable($t)) {
                    $this->error("Missing table: {$t}");
                    return self::FAILURE;
                }
            }
            $this->line(' ✓ schema present (' . count($tables) . ' tables)');
        } catch (\Throwable $e) {
            $this->error('DB check failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 2. Verification gate exercises the contract end-to-end
        $gate = app(LeadVerificationGate::class);
        $outcome = $gate->verifyBatch([
            [
                'name' => 'Alice Verified',
                'reason_for_fit' => 'Has 8 yrs Go and shipped a payments service.',
                'verification_sources' => [
                    ['kind' => 'linkedin', 'url' => 'https://www.linkedin.com/in/alice-verified-example'],
                ],
                'linkedin_url' => 'https://www.linkedin.com/in/alice-verified-example',
                'confidence_score' => 'High',
                'lead_temperature' => 'Cold',
            ],
            [
                'name' => 'Bob Lowconf',
                'reason_for_fit' => 'Possibly a fit.',
                'verification_sources' => [],
                'confidence_score' => 'Low',
            ],
            [
                'name' => 'Carol Guessed',
                'reason_for_fit' => 'Looks like a fit.',
                'verification_sources' => [['kind' => 'social', 'url' => 'https://x.com/carolg']],
                'linkedin_url' => 'https://www.linkedin.com/in/carolg',
                'email' => 'carol.guessed@example.com',
                'confidence_score' => 'Medium',
            ],
        ]);

        $this->line(' ✓ verification gate accepted ' . count($outcome->accepted) . ' / rejected ' . count($outcome->rejected));
        if (count($outcome->accepted) < 1 || count($outcome->rejected) < 1) {
            $this->error('Gate logic incorrect: expected at least 1 accepted and 1 rejected.');
            return self::FAILURE;
        }

        // Email-guessing strip check
        $carolish = collect($outcome->accepted)->first(fn ($r) => $r['name'] === 'Carol Guessed');
        if ($carolish && ($carolish['email'] ?? '') !== '') {
            $this->error('Gate failed to strip a guessed email.');
            return self::FAILURE;
        }
        $this->line(' ✓ guessed email stripped');

        // 3. Config sanity
        foreach (['services.anthropic.reasoning_model', 'services.recruiter.webhook_signature_header'] as $k) {
            if (! config($k)) {
                $this->error("Config missing: {$k}");
                return self::FAILURE;
            }
        }
        $this->line(' ✓ config keys resolved');

        if ($this->option('seed')) {
            $this->seedDemo();
        }

        $this->info('OK');
        return self::SUCCESS;
    }

    private function seedDemo(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Tenant',
                'contact_email' => 'demo@example.com',
                'timezone' => 'Asia/Kuala_Lumpur',
                'require_approval' => true,
            ]
        );

        $user = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'demo@example.com'],
            [
                'name' => 'Demo Operator',
                'password' => Hash::make('demo-password'),
                'role' => 'owner',
            ]
        );

        [$apiKey, $plaintext] = ApiKey::mint($tenant, 'Demo seed key', ['*']);
        $this->newLine();
        $this->info('Demo tenant seeded:');
        $this->line("  tenant slug : demo");
        $this->line("  login email : demo@example.com");
        $this->line("  password    : demo-password");
        $this->line("  API key     : {$plaintext}   <-- save this, shown once");
    }
}
