<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class PdfOptimizationService
{
    public function binaryIsAvailable(): bool
    {
        try {
            $process = new Process([
                (string) config('invoices.pdf.optimization.binary', 'gs'),
                '--version',
            ]);

            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    public function optimize(string $diskPath, bool $force = false): array
    {
        if (! $force && ! config('invoices.pdf.optimization.enabled')) {
            return [
                'optimized' => false,
                'size' => Storage::disk('local')->size($diskPath),
                'saved_bytes' => 0,
            ];
        }

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($diskPath);
        $optimizedPath = $absolutePath.'.optimized.pdf';
        $originalSize = $disk->size($diskPath);

        try {
            $process = new Process([
                (string) config('invoices.pdf.optimization.binary', 'gs'),
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-dPDFSETTINGS='.(string) config('invoices.pdf.optimization.quality', '/ebook'),
                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',
                '-sOutputFile='.$optimizedPath,
                $absolutePath,
            ]);

            $process->setTimeout((int) config('invoices.pdf.optimization.timeout', 60));
            $process->run();

            if (! $process->isSuccessful() || ! is_file($optimizedPath)) {
                Log::warning('Otimizacao de PDF nao concluida.', [
                    'path' => $diskPath,
                    'error' => $process->getErrorOutput(),
                ]);

                @unlink($optimizedPath);

                return [
                    'optimized' => false,
                    'size' => $originalSize,
                    'saved_bytes' => 0,
                ];
            }

            $optimizedSize = filesize($optimizedPath) ?: $originalSize;
            $minSavings = (float) config('invoices.pdf.optimization.min_savings_percent', 8);
            $savingsPercent = $originalSize > 0 ? (($originalSize - $optimizedSize) / $originalSize) * 100 : 0;

            if ($optimizedSize <= 0 || $optimizedSize >= $originalSize || $savingsPercent < $minSavings) {
                @unlink($optimizedPath);

                return [
                    'optimized' => false,
                    'size' => $originalSize,
                    'saved_bytes' => 0,
                ];
            }

            rename($optimizedPath, $absolutePath);

            return [
                'optimized' => true,
                'size' => $optimizedSize,
                'saved_bytes' => $originalSize - $optimizedSize,
            ];
        } catch (Throwable $exception) {
            @unlink($optimizedPath);

            Log::warning('Falha segura ao otimizar PDF.', [
                'path' => $diskPath,
                'message' => $exception->getMessage(),
            ]);

            return [
                'optimized' => false,
                'size' => $originalSize,
                'saved_bytes' => 0,
            ];
        }
    }
}
