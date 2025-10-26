<?php

namespace App\Contracts;

interface LLMClientInterface
{
    /**
     * Analyze a resume against a job description and return a normalized array.
     *
     * Expected keys:
     * - fit_score int 0..100
     * - strengths string[]
     * - weaknesses string[]
     * - summary string
     * - evidence string[] (optional)
     *
     * @return array<string, mixed>
     */
    public function analyze(string $jobText, string $resumeText): array;
}
