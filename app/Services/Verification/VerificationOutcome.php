<?php

namespace App\Services\Verification;

class VerificationOutcome
{
    public function __construct(
        public readonly array $accepted,
        public readonly array $rejected,
    ) {}

    public function summary(): array
    {
        $reasonCounts = [];
        foreach ($this->rejected as $r) {
            foreach (($r['reasons'] ?? []) as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }
        }
        return [
            'accepted_count' => count($this->accepted),
            'rejected_count' => count($this->rejected),
            'reason_counts'  => $reasonCounts,
        ];
    }
}
