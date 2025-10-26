<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'job_description_id',
        'filename',
        'stored_path',
        'resume_text',
        'fit_score',
        'strengths',
        'weaknesses',
        'summary',
        'evidence',
    ];

    protected $casts = [
        'fit_score' => 'integer',
        'strengths' => 'array',
        'weaknesses' => 'array',
        'evidence' => 'array',
    ];

    protected $hidden = ['stored_path', 'resume_text'];

    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class);
    }
}
