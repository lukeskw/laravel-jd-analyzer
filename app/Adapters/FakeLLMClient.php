<?php

namespace App\Adapters;

use App\Contracts\LLMClientInterface;

class FakeLLMClient implements LLMClientInterface
{
    public function analyze(string $jobText, string $resumeText): array
    {
        $jdTokens = $this->tokens($jobText);
        $resumeTokens = $this->tokens($resumeText);

        $jdUnique = array_values(array_unique(array_filter($jdTokens, fn ($t) => strlen($t) >= 4)));
        // Focus on top 20 JD tokens for a quick heuristic
        $jdSlice = array_slice($jdUnique, 0, 20);

        $present = [];
        foreach ($jdSlice as $tok) {
            if (in_array($tok, $resumeTokens, true)) {
                $present[] = $tok;
            }
        }

        $matchCount = count($present);
        $den = max(count($jdSlice), 1);
        $score = (int) round(($matchCount / $den) * 100);

        $strengths = array_map(fn ($t) => ucfirst($t), array_slice($present, 0, 5));
        $weaknesses = array_map(fn ($t) => ucfirst($t), array_slice(array_values(array_diff($jdSlice, $present)), 0, 5));

        return [
            'fit_score' => max(0, min(100, $score)),
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'summary' => sprintf('Matched %d/%d JD keywords.', $matchCount, $den),
            'evidence' => array_slice($present, 0, 5),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? '';
        $parts = preg_split('/\s+/', $text) ?: [];
        return array_values(array_filter($parts));
    }
}

