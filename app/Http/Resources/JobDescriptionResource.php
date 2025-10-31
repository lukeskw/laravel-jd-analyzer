<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobDescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'jd_id' => (string) $this->resource->id,
            'title' => (string) $this->resource->title,
            'candidate_count' => (int) ($this->resource->candidates_count ?? 0),
        ];
    }
}
