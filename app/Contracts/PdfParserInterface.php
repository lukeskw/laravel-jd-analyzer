<?php

namespace App\Contracts;

interface PdfParserInterface
{
    public function extractText(string $path): string;
}

