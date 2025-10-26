<?php

namespace App\Services;

use App\Contracts\LLMClientInterface;

class CandidateFitService
{
    public function __construct(private LLMClientInterface $llm)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(string $jobText, string $resumeText): array
    {
        $result = $this->llm->analyze($jobText, $resumeText);
        // Normalize & clamp per contract
        $result['fit_score'] = max(0, min(100, (int)($result['fit_score'] ?? 0)));
        $result['strengths'] = array_values(array_map('strval', $result['strengths'] ?? []));
        $result['weaknesses'] = array_values(array_map('strval', $result['weaknesses'] ?? []));
        $result['summary'] = (string)($result['summary'] ?? '');
        $result['evidence'] = array_values(array_map('strval', $result['evidence'] ?? []));
        return $result;
    }
}

