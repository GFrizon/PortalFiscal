<?php

namespace App\Services;

use App\Models\BusinessUnit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfStorageService
{
    public function __construct(private readonly PdfOptimizationService $optimizationService)
    {
    }

    public function store(UploadedFile $uploadedFile, ?BusinessUnit $businessUnit): array
    {
        $temporaryPath = $uploadedFile->store('notas/tmp', 'local');
        $absolutePath = Storage::disk('local')->path($temporaryPath);
        $originalSize = max((int) $uploadedFile->getSize(), Storage::disk('local')->size($temporaryPath));
        $originalHash = hash_file('sha256', $absolutePath);

        $optimization = $this->optimizationService->optimize($temporaryPath);
        $storedSize = $optimization['optimized'] ? (int) $optimization['size'] : $originalSize;

        $unitFolder = $businessUnit ? Str::slug($businessUnit->name) : 'unidade-nao-identificada';
        $storedPath = 'notas/'.now()->format('Y/m').'/'.$unitFolder.'/'.basename($temporaryPath);

        Storage::disk('local')->move($temporaryPath, $storedPath);

        return [
            'path' => $storedPath,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'original_size' => $originalSize ?: $storedSize,
            'stored_size' => $storedSize ?: $originalSize,
            'sha256' => $originalHash,
            'optimized' => $optimization['optimized'],
            'processed_at' => now(),
        ];
    }

    public function deleteIfExists(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
