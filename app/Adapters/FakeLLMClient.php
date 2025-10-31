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
            'candidate_name' => $this->extractName($resumeText) ?? 'Unknown Candidate',
            'candidate_email' => $this->extractEmail($resumeText) ?? 'unknown@example.test',
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

    private function extractEmail(string $text): ?string
    {
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[0]);
    }

    private function extractName(string $text): ?string
    {
        $lines = preg_split("/(\r\n|\n|\r)/", (string) $text) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $lower = strtolower($line);
            if (str_contains($lower, '@') || str_contains($lower, 'linkedin') || str_contains($lower, 'curriculum')) {
                continue;
            }

            if (preg_match('/^[a-z\s\'.-]+$/i', $line) !== 1) {
                continue;
            }

            return mb_substr($line, 0, 120);
        }

        return null;
    }
}
