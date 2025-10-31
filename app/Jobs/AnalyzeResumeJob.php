<?php

namespace App\Jobs;

use App\Models\Candidate;
use App\Services\CandidateFitService;
use App\Services\PdfParserService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnalyzeResumeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $candidateId) {}

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function handle(PdfParserService $pdf, CandidateFitService $fit): void
    {
        $candidate = Candidate::query()->with('jobDescription')->find($this->candidateId);
        if (! $candidate || ! $candidate->jobDescription) {
            return;
        }

        $jdText = (string) $candidate->jobDescription->text;

        $resumePath = Storage::disk('local')->path($candidate->stored_path);
        $analysis = null;
        $resumeText = '';
        try {
            $resumeText = $pdf->parse($resumePath);
        } catch (\Throwable $e) {
            $resumeText = '';
            Log::error('Failed to parse resume PDF', [
                'candidate_id' => $this->candidateId,
                'error' => $e->getMessage(),
            ]);
            $analysis = $fit->errorResult();
        }

        if ($analysis === null) {
            try {
                $analysis = $fit->evaluate($jdText, $resumeText);
            } catch (Throwable $exception) {
                Log::error('Failed to evaluate candidate fit', [
                    'candidate_id' => $this->candidateId,
                    'error' => $exception->getMessage(),
                    'exception' => get_class($exception),
                ]);
                $analysis = $fit->errorResult();
            }
        }

        $candidate->fill([
            'resume_text' => $resumeText,
            'fit_score' => $analysis['fit_score'] ?? 0,
            'strengths' => $analysis['strengths'] ?? [],
            'weaknesses' => $analysis['weaknesses'] ?? [],
            'summary' => $analysis['summary'] ?? '',
            'evidence' => $analysis['evidence'] ?? [],
            'candidate_name' => $analysis['candidate_name'] ?? '',
            'candidate_email' => $analysis['candidate_email'] ?? '',
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $context = [
            'job' => 'AnalyzeResumeJob',
            'candidate_id' => $this->candidateId,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ];

        Log::error('Resume analysis failed', $context);
    }
}
