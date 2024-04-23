<?php

namespace App\Service;

use Smalot\PdfParser\Parser;
class PdfExtractorService{
    
    public function extractText(string $pdfFilePath): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfFilePath);

        $text = '';

        foreach ($pdf->getPages() as $page) {
            $text .= $page->getText();
        }

        return $text;
    }

}
