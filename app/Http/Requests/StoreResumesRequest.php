<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResumesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'resumes' => ['required', 'array', 'min:1', 'max:20'],
            'resumes.*' => ['required', 'file', 'mimetypes:application/pdf,application/x-pdf', 'mimes:pdf', 'max:5120'],
        ];
    }
}
