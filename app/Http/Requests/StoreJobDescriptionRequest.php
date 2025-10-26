<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobDescriptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('title') && $this->has('role')) {
            $this->merge(['title' => $this->input('role')]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'unique:job_descriptions,title'],
            'job_description' => ['required', 'file', 'mimetypes:application/pdf,application/x-pdf', 'mimes:pdf', 'max:5120'],
        ];
    }
}
