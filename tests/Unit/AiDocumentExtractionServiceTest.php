<?php

namespace Tests\Unit;

use App\Services\AiDocumentExtractionService;
use App\Services\PdfExtractionService;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class AiDocumentExtractionServiceTest extends TestCase
{
    public function test_pdf_extraction_service_resolved_by_container_has_openai_fallback(): void
    {
        $service = app(PdfExtractionService::class);
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('aiExtractionService');
        $property->setAccessible(true);

        $this->assertInstanceOf(AiDocumentExtractionService::class, $property->getValue($service));
    }

    public function test_it_reads_json_from_responses_api_output_content(): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.model' => 'gpt-4o-mini',
            'services.openai.timeout' => 45,
            'services.openai.pdf_detail' => 'high',
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'invoice_number' => '14317',
                            'invoice_access_key' => null,
                            'issuer_cnpj' => '54163230000109',
                            'issuer_legal_name' => 'BAKOF PLASTICOS LTDA',
                            'recipient_cnpj' => '91967067000155',
                            'recipient_legal_name' => 'BAKOF PLASTICOS LTDA',
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $file = tempnam(sys_get_temp_dir(), 'invoice-pdf-');
        file_put_contents($file, '%PDF-1.4 fake');

        try {
            $result = app(AiDocumentExtractionService::class)->extract($file);
        } finally {
            @unlink($file);
        }

        $this->assertTrue($result['success']);
        $this->assertSame('14317', $result['invoice_number']);
        $this->assertSame('54163230000109', $result['issuer_cnpj']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && data_get($request->data(), 'input.0.content.0.detail') === 'high';
        });
    }
}
