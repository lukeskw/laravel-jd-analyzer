<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListCandidatesRequest;
use App\Http\Requests\StoreJobDescriptionRequest;
use App\Http\Requests\StoreResumesRequest;
use App\Jobs\AnalyzeResumeJob;
use App\Models\Candidate;
use App\Models\JobDescription;
use App\Services\PdfParserService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class CandidateFitController extends Controller
{
    public function __construct(
        private PdfParserService $pdfParser,
    ) {}

    public function storeJobDescription(StoreJobDescriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $jdFile = $data['job_description'];
        $stored = $jdFile->store('uploads');

        try {
            $jdPath = Storage::disk('local')->path($stored);
            $jdText = $this->pdfParser->parse($jdPath);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to parse JD PDF. Ensure pdftotext is installed.',
                'error' => $e->getMessage(),
            ], 422);
        }

        $jd = JobDescription::create([
            'title' => (string) $data['title'],
            'filename' => $jdFile->getClientOriginalName(),
            'stored_path' => $stored,
            'text' => $jdText,
        ]);

        return response()->json(['jd_id' => $jd->id, 'title' => $jd->title]);
    }

    public function storeResumes(StoreResumesRequest $request, string $jd): JsonResponse
    {
        $data = $request->validated();

        $jdModel = JobDescription::query()->find($jd);
        if (! $jdModel) {
            return response()->json(['message' => 'JD not found'], 404);
        }

        $files = $data['resumes'] ?? [];
        $jobs = [];
        foreach ($files as $file) {
            $storedPath = $file->store('uploads');
            $candidate = Candidate::create([
                'job_description_id' => $jdModel->id,
                'filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
            ]);
            $jobs[] = new AnalyzeResumeJob($candidate->id);
        }

        $batch = null;
        if (count($jobs) > 0) {
            $batch = Bus::batch($jobs)
                ->name('Analyze resumes for JD '.$jdModel->id)
                ->allowFailures()
                ->dispatch();
        }

        return response()->json([
            'jd_id' => $jd,
            'queued_count' => count($files),
            'batch_id' => $batch?->id,
        ]);
    }

    public function listCandidates(ListCandidatesRequest $request, string $jd): JsonResponse
    {
        $data = $request->validated();
        $candidateId = $data['candidateId'] ?? null;

        try {
            if ($candidateId) {
                $candidate = Candidate::query()->where('job_description_id', $jd)->find($candidateId);
                if (! $candidate) {
                    return response()->json(['message' => 'Candidate not found'], 404);
                }

                return response()->json($candidate);
            }

            $candidates = Candidate::query()
                ->where('job_description_id', $jd)
                ->orderByDesc('fit_score')
                ->get();

            return response()->json($candidates);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Failed to list candidates. Please verify the JD identifier format.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
