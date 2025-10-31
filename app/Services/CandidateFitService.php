<?php

namespace App\Services;

use App\Contracts\LLMClientInterface;
use Illuminate\Support\Facades\Log;

class CandidateFitService
{
    public function __construct(private LLMClientInterface $llm) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(string $jobText, string $resumeText): array
    {
        try {
            $result = $this->llm->analyze($jobText, $resumeText);
        } catch (\Throwable $e) {
            Log::error('LLM evaluation failed: '.$e->getMessage());

            return $this->errorResult();
        }

        return $this->normalize($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function normalize(array $result): array
    {
        $result['fit_score'] = max(0, min(100, (int) ($result['fit_score'] ?? 0)));
        $result['strengths'] = array_values(array_map('strval', $result['strengths'] ?? []));
        $result['weaknesses'] = array_values(array_map('strval', $result['weaknesses'] ?? []));
        $result['summary'] = trim((string) ($result['summary'] ?? ''));
        $result['evidence'] = array_values(array_map('strval', $result['evidence'] ?? []));
        $result['candidate_name'] = trim((string) ($result['candidate_name'] ?? ''));
        $result['candidate_email'] = strtolower(trim((string) ($result['candidate_email'] ?? '')));

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorResult(): array
    {
        return [
            'fit_score' => 0,
            'strengths' => ['error'],
            'weaknesses' => ['error'],
            'summary' => 'error',
            'evidence' => ['error'],
            'candidate_name' => 'error',
            'candidate_email' => 'error',
        ];
    }
}
