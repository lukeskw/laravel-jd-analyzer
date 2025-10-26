<?php

namespace App\Services;

use App\Contracts\PdfParserInterface;

class PdfParserService
{
    public function __construct(private PdfParserInterface $parser) {}

    public function parse(string $path): string
    {
        return $this->parser->extractText($path);
    }
}
