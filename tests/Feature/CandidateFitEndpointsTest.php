<?php

use App\Contracts\PdfParserInterface;
use App\Models\Candidate;
use App\Models\JobDescription;
use Illuminate\Bus\PendingBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Use a fake local disk so tests don't write to real storage
    Storage::fake('local');

    // Bind a simple fake PDF parser to avoid external binary dependency
    app()->bind(PdfParserInterface::class, fn () => new class implements PdfParserInterface
    {
        public function extractText(string $path): string
        {
            return 'Parsed text for '.basename($path);
        }
    });
});

it('uploads a JD PDF and returns an id and title', function (): void {
    $response = $this->post(route('api.v1.jds.store'), [
        'title' => 'Senior PHP Developer',
        'job_description' => UploadedFile::fake()->create('jd.pdf', 10, 'application/pdf'),
    ]);

    $response->assertOk()
        ->assertJsonStructure(['jd_id', 'title'])
        ->assertJson(['title' => 'Senior PHP Developer']);

    // Ensure JD persisted with parsed text
    $jdId = (string) $response->json('jd_id');
    $jd = JobDescription::query()->find($jdId);
    expect($jd)->not->toBeNull();
    expect($jd->text)->toBeString();
    expect($jd->title)->toBe('Senior PHP Developer');
});

it('uploads multiple resumes for a JD and queues analysis jobs', function (): void {
    // Seed a JD
    $jd = JobDescription::query()->create([
        'title' => 'Backend Engineer',
        'filename' => 'jd.pdf',
        'stored_path' => 'uploads/jd.pdf',
        'text' => 'Backend, Laravel, PHP, PostgreSQL',
    ]);

    Bus::fake();

    $files = [
        UploadedFile::fake()->create('alice.pdf', 8, 'application/pdf'),
        UploadedFile::fake()->create('bob.pdf', 12, 'application/pdf'),
    ];

    $response = $this->post(route('api.v1.jds.resumes.store', ['jd' => $jd->id]), [
        'resumes' => $files,
    ]);

    $response->assertOk()
        ->assertJson(['jd_id' => $jd->id, 'queued_count' => 2])
        ->assertJsonStructure(['jd_id', 'queued_count', 'batch_id']);

    // Two candidates should be created for the JD
    expect(Candidate::query()->where('job_description_id', $jd->id)->count())
        ->toBe(2);

    // A single batch should be dispatched with 2 jobs
    Bus::assertBatched(function (PendingBatch $batch): bool {
        return count($batch->jobs) === 2;
    });
});

it('lists candidates sorted by fit_score and hides sensitive fields', function (): void {
    // Seed a JD and three candidates with varying fit scores
    $jd = JobDescription::query()->create([
        'title' => 'Data Engineer',
        'filename' => 'jd.pdf',
        'stored_path' => 'uploads/jd.pdf',
        'text' => 'ETL, SQL, Python',
    ]);

    $c1 = Candidate::query()->create([
        'job_description_id' => $jd->id,
        'filename' => 'c1.pdf',
        'stored_path' => 'uploads/c1.pdf',
        'fit_score' => 10,
    ]);

    $c2 = Candidate::query()->create([
        'job_description_id' => $jd->id,
        'filename' => 'c2.pdf',
        'stored_path' => 'uploads/c2.pdf',
        'fit_score' => 85,
    ]);

    $c3 = Candidate::query()->create([
        'job_description_id' => $jd->id,
        'filename' => 'c3.pdf',
        'stored_path' => 'uploads/c3.pdf',
        'fit_score' => 40,
    ]);

    $response = $this->getJson(route('api.v1.jds.candidates.index', ['jd' => $jd->id]));

    $response->assertOk();
    $data = $response->json();

    // Expect order: 85, 40, 10
    expect($data)->toBeArray();
    expect($data[0]['id'])->toBe($c2->id);
    expect($data[1]['id'])->toBe($c3->id);
    expect($data[2]['id'])->toBe($c1->id);

    // Drill into one candidate using candidateId, ensure hidden fields are not present
    $single = $this->getJson(route('api.v1.jds.candidates.index', ['jd' => $jd->id, 'candidateId' => $c2->id]));
    $single->assertOk();
    $candidate = $single->json();

    expect($candidate)->toBeArray();
    expect($candidate)->not->toHaveKey('stored_path');
    expect($candidate)->not->toHaveKey('resume_text');
});
