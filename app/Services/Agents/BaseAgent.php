<?php

namespace App\Services\Agents;

use App\Models\AgentRun;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

abstract class BaseAgent
{
    public function __construct(
        protected AnthropicClient $client,
    ) {}

    abstract protected function agentName(): string;

    /**
     * Wrap an agent action with timing, status tracking, and AgentRun audit row.
     *
     * @template T
     * @param  callable(AgentRun): T  $fn
     * @return T
     */
    protected function track(string $action, Model $subject, callable $fn): mixed
    {
        $tenantId = TenantContext::id() ?? $subject->tenant_id;

        $run = AgentRun::query()->withoutGlobalScopes()->create([
            'tenant_id'    => $tenantId,
            'agent'        => $this->agentName(),
            'action'       => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id'   => $subject->getKey(),
            'status'       => AgentRun::STATUS_RUNNING,
            'input_meta'   => ['subject_public_id' => $subject->public_id ?? null],
        ]);

        $start = microtime(true);
        try {
            $result = $fn($run);
            $run->update([
                'status'      => AgentRun::STATUS_SUCCEEDED,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            return $result;
        } catch (\Throwable $e) {
            $run->update([
                'status'      => AgentRun::STATUS_FAILED,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'error'       => mb_substr($e->getMessage(), 0, 4000),
            ]);
            throw $e;
        }
    }
}
