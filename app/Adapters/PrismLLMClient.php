<?php

namespace App\Adapters;

use App\Contracts\LLMClientInterface;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class PrismLLMClient implements LLMClientInterface
{
    public function analyze(string $jobText, string $resumeText): array
    {
        $jobText = $this->truncate($jobText);
        $resumeText = $this->truncate($resumeText);

        $system = "You compare a job description with a resume and output only strict JSON.\n".
            "Return keys: fit_score (0-100 integer), strengths (array of strings), weaknesses (array of strings), summary (string), evidence (array of short strings), candidate_name (string), candidate_email (string).\n".
            'Do not include commentary or markdown fences. Return the response in English, no matter the input language.';

        $user = "JOB DESCRIPTION:\n{$jobText}\n---\nRESUME:\n{$resumeText}";

        try {
            $schema = new ObjectSchema(
                name: 'candidate_fit',
                description: 'Candidate fit analysis',
                properties: [
                    new NumberSchema('fit_score', 'Integer fit score 0-100'),
                    new ArraySchema(
                        name: 'strengths',
                        description: 'Strength bullet points',
                        items: new StringSchema('item', 'A single strength item')
                    ),
                    new ArraySchema(
                        name: 'weaknesses',
                        description: 'Weakness bullet points',
                        items: new StringSchema('item', 'A single weakness item')
                    ),
                    new StringSchema('summary', 'Short summary string'),
                    new ArraySchema(
                        name: 'evidence',
                        description: 'Short evidence phrases',
                        items: new StringSchema('item', 'A short evidence item'),
                    ),
                    new StringSchema('candidate_name', 'Candidate full name if available'),
                    new StringSchema('candidate_email', 'Candidate contact email if available'),
                ],
                requiredFields: ['fit_score', 'strengths', 'weaknesses', 'summary', 'candidate_name', 'candidate_email']
            );

            // We only use Gemini per project setup
            $model = (string) config('llm.model', 'gemini-2.5-flash');

            $response = Prism::structured()
                ->using(Provider::Gemini, $model)
                ->withSystemPrompt($system)
                ->withPrompt($user)
                ->withMaxTokens((int) config('llm.max_output_tokens', 800))
                ->withProviderOptions([
                    // For Gemini, cap or disable thinking to avoid token overages
                    'thinkingBudget' => (int) config('llm.thinking_budget', 0),
                ])
                ->withSchema($schema)
                ->asStructured();

            $data = (array) ($response->structured ?? []);

            return $this->normalize($data);
        } catch (\Throwable $e) {
            Log::error('LLM analysis failed: '.$e->getMessage());

            return $this->fallback($jobText, $resumeText);
        }
    }

    private function truncate(string $text, int $max = 12000): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $result = [
            'fit_score' => max(0, min(100, (int) ($data['fit_score'] ?? 0))),
            'strengths' => array_values(array_map('strval', $data['strengths'] ?? [])),
            'weaknesses' => array_values(array_map('strval', $data['weaknesses'] ?? [])),
            'summary' => (string) ($data['summary'] ?? ''),
            'evidence' => array_values(array_map('strval', $data['evidence'] ?? [])),
            'candidate_name' => (string) ($data['candidate_name'] ?? ''),
            'candidate_email' => (string) ($data['candidate_email'] ?? ''),
        ];

        return $result;
    }

    private function fallback(string $jobText, string $resumeText): array
    {
        $jt = $this->tokens($jobText);
        $rt = $this->tokens($resumeText);
        $slice = array_slice(array_values(array_unique(array_filter($jt, fn ($t) => strlen($t) >= 4))), 0, 20);
        $present = array_values(array_intersect($slice, $rt));
        $score = (int) round((count($present) / max(count($slice), 1)) * 100);

        return [
            'fit_score' => max(0, min(100, $score)),
            'strengths' => array_map(fn ($t) => ucfirst($t), array_slice($present, 0, 5)),
            'weaknesses' => array_map(fn ($t) => ucfirst($t), array_slice(array_values(array_diff($slice, $present)), 0, 5)),
            'summary' => 'Fallback heuristic score due to JSON parse or provider error.',
            'evidence' => array_slice($present, 0, 5),
            'candidate_name' => $this->extractName($resumeText) ?? 'error',
            'candidate_email' => $this->extractEmail($resumeText) ?? 'error',
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
