<?php

namespace Tests\Unit;

use App\Models\BusinessUnit;
use App\Services\PdfExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class PdfExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_identifies_business_unit_cnpj_with_irregular_pdf_spacing(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF PLASTICOS LTDA (UNIDADE 004)',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "EMITENTE CNPJ 54.163.230/0001-09\nDESTINATARIO BAKOF CNPJ 91 . 967 . 067 / 0001 - 55\nNF 123456"
        );

        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('54163230000109', $result['issuer_cnpj']);
        $this->assertContains('91967067000155', $result['cnpjs']);
    }

    public function test_it_ignores_glued_pdf_numbers_that_are_not_valid_cnpjs(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF PLASTICOS LTDA (UNIDADE 001)',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "INSCRICAO ESTADUAL CNPJ\n799828002110 54.163.230/0001-09\n".
            "PROTOCOLO 13526293365022/07/2026\n".
            "DESTINATARIO / REMETENTE BAKOF PLASTICOS LTDA 91.967.067/0001-55"
        );

        $this->assertSame(['54163230000109', '91967067000155'], $result['cnpjs']);
        $this->assertSame('54163230000109', $result['issuer_cnpj']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
    }
}
