<?php

namespace App\Services;

class AiDocumentExtractionService
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function extract(string $absolutePath): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Integracao de IA nao configurada.',
            ];
        }

        return [
            'success' => false,
            'error' => 'Integracao de IA preparada para implementacao futura.',
        ];
    }
}
