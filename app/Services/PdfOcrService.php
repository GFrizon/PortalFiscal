<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class PdfOcrService
{
    public function extract(string $absolutePath): ?string
    {
        if (! config('services.pdf_ocr.enabled')) {
            return null;
        }

        $tempDir = storage_path('app/tmp/pdf-ocr/'.Str::uuid()->toString());
        File::ensureDirectoryExists($tempDir);

        try {
            $prefix = $tempDir.DIRECTORY_SEPARATOR.'page';
            $timeout = max(10, (int) config('services.pdf_ocr.timeout', 60));
            $maxPages = max(1, min(5, (int) config('services.pdf_ocr.max_pages', 2)));

            $render = new Process([
                (string) config('services.pdf_ocr.pdftoppm_binary', 'pdftoppm'),
                '-f',
                '1',
                '-l',
                (string) $maxPages,
                '-r',
                '220',
                '-png',
                $absolutePath,
                $prefix,
            ]);
            $render->setTimeout($timeout);
            $render->run();

            if (! $render->isSuccessful()) {
                Log::warning('Falha ao renderizar PDF para OCR.', [
                    'path' => $absolutePath,
                    'error' => trim($render->getErrorOutput()),
                ]);

                return null;
            }

            $images = File::glob($prefix.'-*.png') ?: [];
            sort($images);

            $text = '';

            foreach ($images as $image) {
                $ocr = new Process([
                    (string) config('services.pdf_ocr.tesseract_binary', 'tesseract'),
                    $image,
                    'stdout',
                    '-l',
                    (string) config('services.pdf_ocr.language', 'por'),
                    '--psm',
                    '6',
                ]);
                $ocr->setTimeout($timeout);
                $ocr->run();

                if (! $ocr->isSuccessful()) {
                    Log::warning('Falha ao ler imagem do PDF com OCR.', [
                        'path' => $absolutePath,
                        'image' => $image,
                        'error' => trim($ocr->getErrorOutput()),
                    ]);

                    continue;
                }

                $text .= "\n".$ocr->getOutput();
            }

            $text = trim($text);

            return $text !== '' ? $text : null;
        } catch (Throwable $exception) {
            Log::warning('OCR do PDF indisponivel.', [
                'path' => $absolutePath,
                'message' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }
}
