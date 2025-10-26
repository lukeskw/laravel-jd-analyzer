<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCandidatesRequest extends FormRequest
{
    /**
     * Forcing validate only query string inputs for this GET endpoint.
     */
    public function validationData(): array
    {
        return $this->query->all();
    }

    public function rules(): array
    {
        return [
            'candidateId' => ['sometimes', 'string'],
        ];
    }
}
