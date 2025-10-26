<?php

namespace App\Adapters;

use App\Contracts\PdfParserInterface;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class SpatiePdfToTextAdapter implements PdfParserInterface
{
    public function extractText(string $path): string
    {
        try {
            if (! is_file($path) || ! is_readable($path)) {
                throw new \RuntimeException('File not found or unreadable');
            }

            $text = Pdf::getText($path);

            return trim($text ?? '');
        } catch (\Throwable $e) {
            Log::error("PDF text extraction failed for {$path}: ".$e->getMessage());
            throw new \RuntimeException('Failed to extract text from PDF: '.$e->getMessage());
        }
    }
}
